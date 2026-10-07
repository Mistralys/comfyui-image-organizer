# Key Data Flows

Descriptive paths through the system. Every type named here is listed in [api-surface.md](api-surface.md).

## Data stores

| Store | Written by | Read by | Shape |
|---|---|---|---|
| ComfyUI output folder (`*.png` + same-named `*.json` sidecar) | ComfyUI ("Save Image Extended" node) | `ImageIndexer` | Sidecar: one top-level key (a date string) whose value holds `checkpoint`, `custom_text` and other fields |
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
      → for each PNG: look for the sidecar *.json with the same name
          → none: skipped
          → ID = MD5 of the PNG file (ERROR_CANNOT_HASH_IMAGE_FILE when the hash cannot be computed)
          → ID already indexed with modified = true: ImageIndexEntry::syncLocation() updates only image path, sidecar path, folder name
          → otherwise: read the sidecar's date entry (must be an array), parse `custom_text` into properties once,
            take the checkpoint from the sidecar (resolveCheckpoint(): a string wins even when empty, anything else
            falls back to the model name from `custom_text`), fall back to the file name for
            test number / test name / seed / batch number, read the image size, decide "upscaled",
            and store a new ImageIndexEntry (skipped when no checkpoint or modelName is found);
            an entry already stored under the ID is replaced by withKnownFieldsFrom(), so its unknown keys survive
      → write data/images.json through ImageIndex::save(), reset the collection
      → ImageIndexer::updateFileIndex() → data/file_index.json
  → cleanUpFolders()                                 (build/index only; deletes empty image subfolders)
```

Upscaled-image detection (`detectUpscaledImages`, run by `build` and the Index Manager):

```
ImageCollection → group images by ImageInfo::getSettingsHash()
  → groups of exactly two images (one regular, one upscaled)
      → regular ImageProperties::setUpscaledImage(upscaled) → ImageCollection::save()
```

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
