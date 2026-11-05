# Key Data Flows

Descriptive paths through the system. Every type named here is listed in [api-surface.md](api-surface.md).

## Data stores

| Store | Written by | Read by | Shape |
|---|---|---|---|
| ComfyUI output folder (`*.png` + sidecar) | ComfyUI ("Save Image Extended" node) | `ImageIndexer`, through `MetadataRegistry` | Legacy sidecar (`<name>.json`): one top-level key (a date string) whose value holds `checkpoint`, `custom_text` and other fields. Structured sidecar (`<name>-meta.json` or `<name>-meta_<nr>.json`): `image`, `prompt` and `sampler` objects plus optional `model`, `flow`, `upscale`, `loras`. A workflow export (`nodes` and `links`) may sit beside a PNG; it is never indexed |
| `data/images.json` | `ImageIndexer`, `ImageCollection::save()` | `ImageCollection`, `ImageIndexer` | Object keyed by image ID; each value is the output of `ImageInfo::serialize()` (`ImageIndexEntry::toArray()`), plus any unknown keys the stored entry carried. Both writers go through `ImageIndex`, which writes every entry. Legacy entries that lack `label` or `modified` gain explicit defaults (`""` / `false`) on the first save, so that save produces a one-time, large diff |
| `data/file_index.json` | `ImageIndexer::updateFileIndex()` | `image.php` | Object keyed by image ID with `imageFile` and `thumbnailFile` paths |
| `data/backup/images-<timestamp>.json` | `ImageIndexer` (before each analysis run) | manual recovery | Copy of `images.json` |
| `data/loras/**/*.json` | author, by hand | `LoRAsCollection` | Object keyed by LoRA ID; each entry is either nested (a `models` object keyed by model name, each with `file`, `source`, `triggerWords`) or flat (a single model described by the entry itself) |
| `cache/<id>-540.jpg` | `ImageInfo::createThumbnail()` (called by `image.php`) | `image.php` | JPEG thumbnail |
| `output-images/` | `ImageInfo::copyToOutput()` | author | Copied PNG |

## 1. Indexing

Triggered by `composer build` / `index` (via `Builder`), by the Index Manager page (`IndexManagerPage`), or by the browser's per-folder refresh (`ImageBrowser`).

```
Builder::build() | Builder::index() | IndexManagerPage (refresh) | ImageBrowser (refresh a folder)
  → ImageIndexer::indexAll() / indexFolder()
      → findAllImages() / findFolderImages()         (*.png under OrganizerApp::getImageFolder())
      → load data/images.json into an ImageIndex (a backup copy is taken first; a non-array entry throws ERROR_INVALID_INDEX_ENTRY)
      → one ImageAnalyzer per run (built after the load, which replaces the index object)
      → for each PNG: MetadataRegistry::resolve(png) → SidecarResolution
          → no sidecar resolved, unrecognized candidates exist: console skip line + ACTION_SKIP_UNRECOGNIZED_SIDECAR warning (file name only)
          → no sidecar resolved, no candidate file: console "no sidecar" line only
          → sidecar resolved (a structured -meta.json wins over a legacy .json and a workflow export): ImageAnalyzer::analyze(png, sidecar)
              → ID = MD5 of the PNG file (ERROR_CANNOT_HASH_IMAGE_FILE when the hash cannot be computed)
              → ID already indexed with modified = true: ImageIndexEntry::syncLocation() updates only image path, sidecar path, folder name
              → otherwise: format->parse() the sidecar (ERROR_NO_MODEL_NAME: ACTION_SKIP_NO_CHECKPOINT warning;
                any other MetadataException: ACTION_SKIP_INVALID_SIDECAR warning; the image is left out either way),
                complete the index-property bag from the file name / folder, read the image size, decide "upscaled",
                take the date from the sidecar (legacy) or the PNG's modification time (structured), and store a new ImageIndexEntry
                (the checkpoint is ImageMetadata::getModelName() ?? ''); an entry already stored under the ID is replaced by
                withKnownFieldsFrom(), so its unknown keys survive
      → write data/images.json through ImageIndex::save(), reset the collection
      → ImageIndexer::updateFileIndex() → data/file_index.json
  → cleanUpFolders()                                 (build/index only; deletes empty image subfolders)
```

The skip results are warnings, so `ImageBrowser::handleRefreshFolder()`, which reads `countSuccesses()` and `countNotices()`, reports legacy images as before. The per-image decisions live in `ImageAnalyzer`, which works without `OrganizerApp` and is covered by PHPUnit; `ImageIndexer::analyzeImages()` itself is not.

Upscaled-image detection (`detectUpscaledImages`, run by `build` and the Index Manager):

```
ImageCollection → group images by ImageInfo::getSettingsHash()
  → groups of exactly two images (one regular, one upscaled)
      → regular ImageProperties::setUpscaledImage(upscaled) → ImageCollection::save()
```

Metadata census (`composer verify-metadata`, run by hand; separate from indexing):

```
Builder::verifyMetadata()
  → every *.png under APP_IMAGE_FOLDER (no OrganizerApp, no data/ access)
  → MetadataVerifier::census(): MetadataRegistry::resolve() per image, then the resolved format's parse()
      → no candidate file: noSidecar   | candidates but none recognized (also malformed JSON): unrecognized
      → ERROR_NO_MODEL_NAME: noModelName | any other MetadataException: failures | success: counted per format ID
  → prints the counts and the file names of the four lists; returns false (Composer exit 1) only when failures is not empty
```

The census writes nothing and does not read `data/images.json`.

## 2. Browsing

```
index.php → OrganizerApp::create()->createUI(...)->display()
  → page selected by the `page` request parameter (default: ImageBrowser::URL_NAME)
  → ImageBrowser::preRender()
      → OrganizerApp::createImageCollection()  → ImageCollection::registerItems()
         (entries whose image file is gone are set aside as "missing", not displayed)
      → ImageCollection::injectJS()            (loads the JS files, emits AJAX method names + payload keys)
      → filters / folder / card size resolved from request parameters, remembered in the session (via SessionState)
  → ImageBrowser::_render() → one card per image that has no upscaled replacement
       thumbnail <img> → image.php?imageID=<id>&thumbnail=yes
```

Image delivery:

```
image.php?imageID=<id>[&thumbnail=yes]
  → OrganizerApp::getFileIndexFile() lookup (404 if the ID is absent)
  → a sibling .jpg replaces the .png source when it exists
  → thumbnail: created with ImageInfo::createThumbnail() when missing or older than the source
  → ImageHelper::displayImage()
```

The details page (`ImageDetails`) shows one image from `ImageCollection::getFromRequest()`, with actions to reset the stored website image ID and to send the image to the website.

Lazy metadata path (nothing in the browser or the pages calls it yet):

```
ImageInfo::getMetadata()
  → first call: MetadataRegistry::getInstance()->parseFile(<stored sidecar file>) → ImageMetadata, cached on the ImageInfo instance
  → later calls: the cached object
  → a missing or unrecognized sidecar throws MetadataException
```

The path reads the sidecar only. It never writes `data/images.json`, never calls `setDataChanged()` and never sets `modified`.

## 3. AJAX image actions

```
ImageBrowser.js (class ImageBrowser).SendRequest()
  → fetch GET  <page URL>&ajax=<METHOD_NAME>&imageID=<id>&<action parameters>
  → BasePage (framework) reads the `ajax` parameter on the active page and calls process() on the registered method
    whose ID matches (methods registered by OrganizerApp::registerAjaxMethods())
  → BaseImageMethod::preProcess() creates the ImageCollection
  → BaseImageMethod::_process() resolves the image (error 179301 when missing)
      → preProcessImage() → processImage()
          → mutate through ImageInfo / ImageProperties → ImageCollection::save()
          → sendSuccess(message, payload)  (payload always includes favorite, forGallery, forWebsite, label)
  → JS updates the card from the response
```

Action-specific behavior:

- **Move** (`MoveImageMethod`): `ImageInfo::moveToFolder()` copies image and sidecar into a sibling folder, saves the collection, then deletes the originals; linked low-resolution versions follow.
- **Delete** (`DeleteImageMethod`): `ImageCollection::deleteImage()` removes image, sidecar, thumbnail, index entry and linked low-resolution versions.
- **Set upscaled** (`SetUpscaledImageMethod`): `ImageProperties::setUpscaledImage()`; `ImageInfo::registerLowResImage()` then merges favorite/gallery/website/label flags between the pair.
- **Send to website** (`SendToWebsiteMethod`): requires the `_websiteImage.png` sibling; POSTs base64 image plus properties (checkpoint, sampler/scheduler, steps, CFG, seed, LoRA summary, prompt) to the configured website API, then stores the returned image ID in `ImageProperties::setWebsiteImageID()`. `ImageDetails` can invoke `sendRequest()` directly, outside AJAX.
- **Copy to output** (`CopyToOutputMethod`): `ImageInfo::copyToOutput()` writes `<label or id>-<test number>-<batch number>.png` into `output-images/`, replacing an existing file.
- **Copy path** (client-side only, no AJAX): the "More" menu item carries the absolute image path in `data-path` (rendered by `ImageBrowser::renderImageMenu()`); `ImageBrowser.CopyPath()` writes it to the clipboard and shows a status message.
- **Card size** (`SetCardSizeMethod`): stores the size in the session through `ImageBrowser::setCardSize()` (which uses `SessionState`); does not involve an image.

## 4. LoRA reference and workflow path fixing

```
LoRAOverviewPage → LoRAsCollection::getInstance() → every *.json under data/loras/
  → LoRA::fromJSONEntry() per entry (nested or flat shape → LoRAModel list; a non-array entry throws ERROR_INVALID_LORA_ENTRY)
  → LoRA records → DataGrid (filter: category_filter)

WorkflowsPage → WorkflowsCollection::create() → ComfyUI::getWorkflowsFolder() (*.json) → WorkflowFile records
  "fix paths" link → WorkflowFileURL::fixPaths() → WorkflowsPage (REQUEST_PARAM_FIX_PATHS)
    → PathFixer::fixPaths()
        → buildFileIndex(): known-file map from LoRA::getFiles() (every model's file; a LoRA without any file gets a "has no file specified" warning),
          matched to the *.safetensors files under PathFixer::getLoRAFolder() by lowercase name / suffix
        → WorkflowData::fromArray(<decoded workflow>); no `nodes` array → return without any message
        → WorkflowData::rewriteFirstWidgetString('LoraLoaderModelOnly', resolvePath):
          the first widgets_values entry of each such node (when a non-empty string) is replaced by the path
          relative to the loras folder; returns the number of values changed
        → count > 0: write a timestamped .bak copy, save WorkflowData::toArray(), restore `{}` for empty flags/config/properties
        → count = 0: "No changes necessary" notice; no backup, no write
    → messages (PathFixerMessage) shown after redirect
```

The message templates are trusted HTML; `PathFixerMessage` HTML-escapes every string argument (widget value, LoRA file name, LoRA ID, backup name) when it is built. `WorkflowsPage` joins the messages into the success message without further escaping, so every message source must pass untrusted values as arguments.

`ImageProperties::getLoRASummary()` also uses `LoRAsCollection`: property keys in an image that equal a LoRA ID are treated as that LoRA's weight.

## 5. Bootstrap

```
index.php / image.php / Builder::init()
  → prepend.php: vendor/autoload.php, config.php, Request base URL (APP_WEBROOT_URL),
                 constants CACHE_FOLDER and OUTPUT_FOLDER, ClassHelper cache folder = cache/
  → OrganizerApp::create() → X4Application constructor (starts a PHP session under a web server, uses an in-memory
                              array instead under the CLI; initializes localization)
```
