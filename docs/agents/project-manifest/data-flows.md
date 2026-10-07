# Key Data Flows

Descriptive paths through the system. Every type named here is listed in [api-surface.md](api-surface.md).

## Data stores

| Store | Written by | Read by | Shape |
|---|---|---|---|
| ComfyUI output folder (`*.png` + same-named `*.json` sidecar) | ComfyUI ("Save Image Extended" node) | `ImageIndexer` | Sidecar: one top-level key (a date string) whose value holds `checkpoint`, `custom_text` and other fields |
| `data/images.json` | `ImageIndexer`, `ImageCollection::save()` | `ImageCollection`, `ImageIndexer` | Object keyed by image ID; each value is the output of `ImageInfo::serialize()` |
| `data/file_index.json` | `ImageIndexer::updateFileIndex()` | `image.php` | Object keyed by image ID with `imageFile` and `thumbnailFile` paths |
| `data/backup/images-<timestamp>.json` | `ImageIndexer` (before each analysis run) | manual recovery | Copy of `images.json` |
| `data/loras/**/*.json` | author, by hand | `LoRAsCollection` | Object keyed by LoRA ID |
| `cache/<id>-540.jpg` | `ImageInfo::createThumbnail()` (called by `image.php`) | `image.php` | JPEG thumbnail |
| `output-images/` | `ImageInfo::copyToOutput()` | author | Copied PNG |

## 1. Indexing

Triggered by `composer build` / `index` (via `Builder`), by the Index Manager page (`IndexManagerPage`), or by the browser's per-folder refresh (`ImageBrowser`).

```
Builder::build() | Builder::index() | IndexManagerPage (refresh) | ImageBrowser (refresh a folder)
  → ImageIndexer::indexAll() / indexFolder()
      → findAllImages() / findFolderImages()         (*.png under OrganizerApp::getImageFolder())
      → for each PNG: look for the sidecar *.json with the same name
          → none: skipped
          → ID = MD5 of the PNG file
          → ID already indexed with modified = true: only update image path, sidecar path, folder name
          → otherwise: parse `custom_text` into properties, fall back to the file name for
            test number / test name / seed / batch number, read the image size, decide "upscaled",
            and store a new entry (skipped when no checkpoint or modelName is found)
      → write data/images.json (a backup copy is taken first), reset the collection
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
      → filters / folder / card size resolved from request parameters, remembered in $_SESSION
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
- **Card size** (`SetCardSizeMethod`): stores the size in `$_SESSION` through `ImageBrowser::setCardSize()`; does not involve an image.

## 4. LoRA reference and workflow path fixing

```
LoRAOverviewPage → LoRAsCollection::getInstance() → every *.json under data/loras/ → LoRA records → DataGrid (filter: category_filter)

WorkflowsPage → WorkflowsCollection::create() → ComfyUI::getWorkflowsFolder() (*.json) → WorkflowFile records
  "fix paths" link → WorkflowFileURL::fixPaths() → WorkflowsPage (REQUEST_PARAM_FIX_PATHS)
    → PathFixer::fixPaths()
        → index of *.safetensors files under PathFixer::getLoRAFolder(), matched to LoRA::getFile() by lowercase name / suffix
        → rewrite widgets_values[0] of nodes of type LoraLoaderModelOnly with the path relative to the loras folder
        → when anything changed: write a timestamped .bak copy, save the JSON, restore `{}` for empty flags/config/properties
    → messages (PathFixerMessage) shown after redirect
```

`ImageProperties::getLoRASummary()` also uses `LoRAsCollection`: property keys in an image that equal a LoRA ID are treated as that LoRA's weight.

## 5. Bootstrap

```
index.php / image.php / Builder::init()
  → prepend.php: vendor/autoload.php, config.php, Request base URL (APP_WEBROOT_URL),
                 constants CACHE_FOLDER and OUTPUT_FOLDER, ClassHelper cache folder = cache/
  → OrganizerApp::create() → X4Application constructor (starts a PHP session under a web server, uses an in-memory
                              array instead under the CLI; initializes localization)
```
