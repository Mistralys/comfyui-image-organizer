# API Surface

Public signatures only, grouped by module. All classes live under `src/ComfyUIOrganizer/` in the namespace `Mistralys\ComfyUIOrganizer` (sub-namespaces mirror the folders). Framework base classes (`X4Application`, `BasePage`, `BaseAjaxMethod`, `BaseStringPrimaryCollection`, ...) come from dependencies and are not documented here.

## Top-level classes

### `OrganizerApp` — `OrganizerApp.php`
Extends `X4Application`. Process-wide singleton and access point to folders, storage files and the image collection.

```php
public function __construct(SplFileInfo|string|PathInfoInterface $comfyFolder, SplFileInfo|string|PathInfoInterface $imageFolder)
public static function create(): OrganizerApp            // built from APP_COMFYUI_FOLDER, APP_IMAGE_FOLDER
public function getComfyFolder(): FolderInfo             // ComfyUI installation folder
public function getImageFolder(): FolderInfo             // folder ComfyUI saves images to
public function getStorageFolder(): FolderInfo           // <project>/data
public function getCacheFolder(): FolderInfo             // <project>/cache
public function getStorageFile(): JSONFile               // data/images.json
public function getFileIndexFile(): JSONFile             // data/file_index.json
public function createImageCollection(): ImageCollection // cached per instance
public function resetImageCollection(): self
public function url(): URLs
public function getTitle(): string
public function getVersion(): string
public function getDefaultPageID(): ?string              // ImageBrowser::URL_NAME
public function registerPages(UserInterface $ui): void   // scans Pages/
public function registerAjaxMethods(AjaxMethods $methods): void // scans Ajax/Methods/
```

### `Builder` — `Builder.php`
Static entry points for the Composer scripts. Each bootstraps `prepend.php` once.

```php
public static function build(): void          // indexAll + detectUpscaledImages + cleanUpFolders
public static function index(): void          // indexAll + cleanUpFolders
public static function detectUpscaled(): void
public static function cleanFolders(): void
public static function updateFileIndex(): void
public static function postAutoload(): void   // clears the ClassHelper repository cache
```

### `ImageIndexer` — `ImageIndexer.php`

```php
public const int ACTION_UPDATE_PATHS, ACTION_SKIP_NO_CHECKPOINT, ACTION_IMAGE_INDEXED, ACTION_SKIP_NO_DATE
public const string INDEX_THUMBNAIL_FILE = 'thumbnailFile'
public const string INDEX_IMAGE_FILE = 'imageFile'

public function __construct(OrganizerApp $app)
public static function updateFileIndex(): void              // rewrites data/file_index.json from the collection
public function findAllImages(): array                      // recursive *.png search in the image folder
public function findFolderImages(string $folderName): array // *.png in one image subfolder
public function indexAll(): self
public function indexFolder(string $folderName): self
public function detectUpscaledImages(?string $folderName = null): self
public function detectUpscaledInFolder(string $folderName): self
public function cleanUpFolders(): self                      // deletes empty image subfolders
public function getAnalysisResults(): OperationResult_Collection
public static function resolveCheckpoint(mixed $sidecarValue, string $fallback): string // a string sidecar value wins, even when empty; any other value yields $fallback
```

The indexer keeps the loaded index as an `ImageIndex` (private) for the duration of an analysis run. `analyzeImage()` throws `OrganizerException::ERROR_CANNOT_HASH_IMAGE_FILE` when the MD5 of an image file cannot be computed.

### `ImageCollection` — `ImageCollection.php`
Extends `BaseStringPrimaryCollection`; items are `ImageInfo`.

```php
public const string REQUEST_PARAM_IMAGE_ID = 'imageID'
public function __construct(JSONFile $dataFile)
public function getDefaultID(): string
public function getFromRequest(): ?ImageInfo       // resolves the imageID request parameter
public function getFolderNames(): array            // sorted unique folder names
public function getMissingImages(): array          // index entries whose image file no longer exists
public function removeMissingImages(): self
public function deleteImage(ImageInfo $image): void // image, sidecar, thumbnail, linked low-res versions, index entry
public function save(): void                       // merges each changed image onto its stored entry, then writes the whole index
public function injectJS(UserInterface $ui, string $baseURL): string // registers JS files, returns JS object name
```

The collection holds the loaded `data/images.json` as an `ImageIndex` (private). `registerItems()` builds one `ImageInfo` per `ImageIndex::getEntries()` entry through `ImageInfo::fromEntry()`. `save()` takes the stored entry for each changed image and applies `ImageIndexEntry::withKnownFieldsFrom($image->toEntry())`, so unknown keys on the stored entry survive; an image with no stored entry is added as its plain `toEntry()` result. The write goes through `ImageIndex::save()`, which rewrites every entry, not only the changed ones. `deleteImage()` removes the entry with `ImageIndex::remove()`; the removal is persisted by the `save()` that follows. `getFromRequest()` validates the `imageID` request parameter as MD5 and reads it with `getString()`; an absent or invalid value yields `null`.

### `ImageInfo` — `ImageInfo.php`
Implements `StringPrimaryRecordInterface`. One indexed image. The ID is the MD5 hash of the PNG file.

```php
public const string KEY_ID, KEY_IMAGE_FILE, KEY_SIDECAR_FILE, KEY_DATE, KEY_CHECKPOINT, KEY_UPSCALED,
                    KEY_IMAGE_SIZE, KEY_MODIFIED, KEY_LABEL, KEY_PROPERTIES
public const int THUMBNAIL_WIDTH = 540
public bool $dataChanged

public function __construct(string $id, FileInfo $imageFile, JSONFile $sidecarFile, Microtime $date, string $checkpoint,
                            bool $upscaled, bool $modified, string $label, array $imageSize, ArrayDataCollection $properties)
public static function fromEntry(ImageIndexEntry $entry): ImageInfo
public static function createThumbnail(FileInfo $sourceImage, FileInfo $thumbnailFile): void
public function toEntry(): ImageIndexEntry         // known fields only; unknown index keys stay on the stored entry
public function serialize(): array                 // toEntry()->toArray(); the browser payload / index shape

public function getID(): string
public function prop(): ImageProperties
public function getProperties(): ImageProperties
public function getImageFile(): FileInfo
public function getSidecarFile(): JSONFile
public function getThumbnailFile(): FileInfo       // cache/<id>-540.jpg
public function getPSDFile(): FileInfo             // sibling .psd
public function getJPGFile(): FileInfo             // sibling .jpg
public function getWebsiteImage(): FileInfo        // sibling _websiteImage.png
public function getImageSize(): array              // ['width' => int, 'height' => int]
public function getCheckpoint(): string
public function getDate(): Microtime
public function getLabel(): string
public function setLabel(string $label): self
public function isUpscaled(): bool
public function isDataChanged(): bool
public function getURL(): string                   // image.php?imageID=<id>
public function getThumbnailURL(): string
public function getViewDetailsURL(array $params = array()): string
public function getSearchWords(): array            // lowercased search tokens
public function getSettingsHash(): string          // md5 over the generation settings
public function getOutputFileName(): string
public function findLowResVersions(): array        // images whose upscaledImage points at this one
public function registerLowResImage(ImageInfo $image): void
public function moveToFolder(string $folderName): self
public function copyToOutput(): void
public function save(): self
public function injectJS(UserInterface $ui, string $objName): void
public function getUpscalingBadge(): string
public function getEditedBadge(): string
```

### `ImageProperties` — `ImageProperties.php`
Typed view over the free-form property bag stored under an image's `properties` key. Setters mark the owning `ImageInfo` as changed.

```php
public const string KEY_TEST_NAME, KEY_TEST_NUMBER, KEY_SEED, KEY_FOLDER_NAME, KEY_IMG_BATCH_NR, KEY_FAVORITE,
                    KEY_FOR_WEBSITE, KEY_FOR_GALLERY, KEY_UPSCALED_IMAGE, KEY_CFG, KEY_PROMPT_NEGATIVE,
                    KEY_PROMPT_POSITIVE, KEY_SAMPLER, KEY_SAMPLER_STEPS, KEY_SCHEDULER, KEY_FACEFIX,
                    KEY_WEBSITE_IMAGE_ID, KEY_MODEL_NAME, KEY_VAE_NAME, KEY_CLIP_NAMES

public function __construct(ImageInfo $image, ArrayDataCollection $data, callable $modifiedCallback)
public function serialize(): array                 // key-sorted copy of the property bag

public function getSeed(): int
public function getCFG(): float
public function getPromptPositive(): string
public function getPromptNegative(): string
public function getSampler(): string
public function getSamplerSteps(): int
public function getScheduler(): string
public function getFolderName(): string
public function setFolderName(string $folderName): self
public function getTestName(): string
public function getTestNumber(): int
public function getBatchNumber(): int
public function getLoRASummary(): string           // "<loraID> <weight>, ..." for LoRA IDs present in the bag
public function isFavorite(): bool
public function setFavorite(bool $enabled): self
public function isForGallery(): bool
public function setForGallery(bool $enabled): self // enabling also enables favorite
public function isForWebsite(): bool
public function setForWebsite(bool $enabled): self // enabling also enables gallery (and thereby favorite)
public function isFacefix(): bool
public function getUpscaledImage(): ?ImageInfo
public function setUpscaledImage(ImageInfo $image): self
public function getWebsiteImageID(): ?string
public function setWebsiteImageID(string $imageID): self
```

### `ImageIndexEntry` — `ImageIndexEntry.php`
Typed round-trip record for one entry of `data/images.json`. Keys it does not model ("unknown keys", e.g. the legacy `forGallery`) are kept and written back, so a load-and-save cycle loses nothing. Persisted key names reuse the `ImageInfo::KEY_*` constants. Used by `ImageInfo` (`fromEntry()` / `toEntry()`), through `ImageIndex` by `ImageCollection`, and by `ImageIndexer`, which builds new entries and merges them onto stored ones.

```php
public function __construct(string $id, string $imageFile, string $sidecarFile, string $date, string $checkpoint,
                            bool $upscaled, bool $modified, string $label,
                            array $imageSize,   // array{width:int,height:int}
                            array $properties,  // array<int|string,mixed>
                            array $extra = array()) // array<string,mixed>: unknown top-level keys
public static function fromArray(array $data): self  // lenient: missing or malformed fields fall back to '' / false / 0 / []
public function toArray(): array                     // known keys in ImageInfo::serialize() order, then the unknown keys
public function syncLocation(string $imageFile, string $sidecarFile, string $folderName): bool // mutates; true when a value changed
public function withKnownFieldsFrom(ImageIndexEntry $source): self // copy with the source's known fields and this entry's unknown keys

public function getID(): string
public function getImageFile(): string
public function getSidecarFile(): string
public function getDate(): string                    // ISO date string
public function getCheckpoint(): string
public function isUpscaled(): bool
public function isModified(): bool
public function getLabel(): string
public function getImageSize(): array                // ['width' => int, 'height' => int]
public function getProperties(): array
public function getExtra(): array                    // unknown keys
```

`syncLocation()` compares the image file, the sidecar file and the `folder` property (`ImageProperties::KEY_FOLDER_NAME`), updates only what differs (an empty or missing stored folder name counts as different), and leaves `modified`, `label` and all other properties untouched. `toArray()` merges the unknown keys with `array_merge()`, which would renumber integer-like unknown keys; real index data has none.

### `ImageIndex` — `ImageIndex.php`
The set of `ImageIndexEntry` records of `data/images.json`, keyed by image ID. Held by `ImageCollection` and `ImageIndexer`.

```php
public static function fromArray(array $data): self       // throws OrganizerException::ERROR_INVALID_INDEX_ENTRY (179403) when an entry is not an array
public static function load(JSONFile $file): self
public function save(JSONFile $file): void                // writes toArray()
public function toArray(): array                          // array<string,array<string,mixed>>
public function get(string $id): ?ImageIndexEntry
public function has(string $id): bool
public function set(ImageIndexEntry $entry): self         // adds, or replaces the entry stored under the same ID
public function remove(string $id): self
public function getEntries(): array                       // array<string,ImageIndexEntry>
public function count(): int
```

`fromArray()` keys each entry by the entry's own `id` value and ignores the outer JSON key, so an entry stored under a key that differs from its `id` is written back under its `id`. Numeric IDs become integer array keys.

### `SessionState` — `SessionState.php`
Static typed accessor for `$_SESSION`; the only code that touches it directly. A value that is absent or has the wrong type yields the default.

```php
public static function getString(string $key, string $default = ''): string // default unless the stored value is a string
public static function getBool(string $key): bool                            // true only when the stored value is boolean true
public static function set(string $key, string|bool $value): void
```

### `ComfyUI` — `ComfyUI.php`

```php
public static function create(): self
public function getWorkflowsFolder(): FolderInfo   // <ComfyUI>/user/default/workflows, must exist
```

### `URLs` — `URLs.php`

```php
public function __construct(UserInterface $ui)
public function browser(): string
public function indexManager(array $params = array()): string
public function workflows(array $params = array()): string
public function loras(array $params = array()): string
```

### `BaseOrganizerPage` — `BaseOrganizerPage.php`
Abstract; extends `BasePage`. Provides the shared top navigation (Browser, Index manager, Workflows, LoRAs) through `getNavItems(): array`.

### `OrganizerException` — `OrganizerException.php`
Extends `AppUtils\BaseException`.

```php
public const int ERROR_CANNOT_SAVE_INEXISTENT_IMAGE = 179401
public const int ERROR_CANNOT_MOVE_FILE_EXISTS = 179402
public const int ERROR_INVALID_INDEX_ENTRY = 179403
public const int ERROR_INVALID_LORA_ENTRY = 179404
public const int ERROR_CANNOT_HASH_IMAGE_FILE = 179405
```

## Ajax — `Ajax/`

### `Ajax\BaseImageMethod`
Abstract; extends `BaseAjaxMethod`. Resolves the image from the `imageID` request parameter and answers with the image's current flags.

```php
public const int ERROR_IMAGE_NOT_FOUND = 179301
public const string REQUEST_PARAM_FOR_GALLERY = 'forGallery'
public const string REQUEST_PARAM_FOR_WEBSITE = 'forWebsite'
public const string REQUEST_PARAM_FAVORITE = 'favorite'
public const string REQUEST_PARAM_LABEL = 'label'
public const string RESPONSE_STATUS = 'status'
// subclasses implement: getID(): string, processImage(ImageInfo $image): never; may override preProcessImage(ImageInfo): void
```

### `Ajax\Methods\*`
Each class defines `METHOD_NAME` (the value passed as the `ajax` parameter) and returns it from `getID()`.

| Class | `METHOD_NAME` | Extends | Additional request/response constants |
|---|---|---|---|
| `CopyToOutputMethod` | `CopyToOutput` | `BaseImageMethod` | — |
| `DeleteImageMethod` | `DeleteImage` | `BaseImageMethod` | — |
| `FavoriteImageMethod` | `FavoriteImage` | `BaseImageMethod` | — |
| `MoveImageMethod` | `MoveImage` | `BaseImageMethod` | `REQUEST_PARAM_FOLDER_NAME = 'folderName'`, `ERROR_EMPTY_FOLDER_NAME = 179601` |
| `SendToWebsiteMethod` | `SendToWebsite` | `BaseImageMethod` | `ERROR_IMAGE_ALREADY_EXISTS = 182301`, `ERROR_WEBSITE_IMAGE_NOT_FOUND = 182302`, `REQUEST_KEY_*` (image, properties, filename, group, galleryID), `RESPONSE_KEY_IMAGE_ID`; public `sendRequest(ImageInfo $image): void` |
| `SetCardSizeMethod` | `SetCardSize` | `BaseAjaxMethod` | `REQUEST_PARAM_CARD_SIZE = 'cardSize'` |
| `SetForGalleryMethod` | `SetForGallery` | `BaseImageMethod` | — |
| `SetForWebsiteMethod` | `SetForWebsite` | `BaseImageMethod` | — |
| `SetLabelMethod` | `SetLabel` | `BaseImageMethod` | — |
| `SetUpscaledImageMethod` | `SetUpscaledImage` | `BaseImageMethod` | `REQUEST_PARAM_UPSCALED_ID = 'upscaledID'`, `ERROR_UPSCALED_IMAGE_NOT_FOUND = 179501`, `RESPONSE_UPSCALED_*` (id, favorite, forGallery, forWebsite, label) |

## Pages — `Pages/`

All expose `URL_NAME` (the `page` request parameter value) and implement the `BasePage` hooks (`getID()`, `getTitle()`, `getNavTitle()`, ...).

| Class | Extends | `URL_NAME` | Public constants beyond `URL_NAME` |
|---|---|---|---|
| `ImageBrowser` | `BaseOrganizerPage` | `image-browser` | `REQUEST_PARAM_UPSCALED`, `_FAVORITES`, `_GALLERY`, `_WEBSITE`, `_FOLDER_NAME`, `_REMOVE_MISSING`; `CARD_SIZE_*` (s, m, l, xl); `RESULT_REFRESHED = 181001`. Static `getCardSizes(): array`, `setCardSize(string $size): void` |
| `ImageDetails` | `BasePage` | `image-details` | `REQUEST_PARAM_RESET_WSIMGID`, `REQUEST_PARAM_SEND_TO_WEBSITE` |
| `IndexManagerPage` | `BaseOrganizerPage` | `index-manager` | `REQUEST_PARAM_CLEAN_FOLDERS`, `_DETECT_UPSCALED`, `_REFRESH` |
| `LoRAOverviewPage` | `BaseOrganizerPage` | `loras` | — (filter parameter `category_filter`) |
| `WorkflowsPage` | `BaseOrganizerPage` | `workflows` | `REQUEST_PARAM_FIX_PATHS = 'fix-paths'`, `COL_LABEL`, `COL_MODIFIED` |

## LoRAs — `LoRAs/`

### `LoRAs\LoRAsCollection`
Extends `BaseStringPrimaryCollection`; items are `LoRA`, loaded from every `*.json` under `data/loras/`.

```php
public static function getInstance(): self
public function getDefaultID(): string
public function getCategories(): array             // sorted unique, non-empty categories
public function getDataFiles(): array              // JSONFile[]
public function getAllAsBucket(): LoRABucket
```

### `LoRAs\LoRA`
Implements `StringPrimaryRecordInterface`. Holds a list of `LoRAModel` records; the per-model getters below aggregate over them.

```php
public const string KEY_LABEL, KEY_CATEGORY, KEY_MODELS

/** @param LoRAModel[] $models */
public function __construct(string $id, string $label, string $category, array $models)

/** @throws OrganizerException ERROR_INVALID_LORA_ENTRY when $entry is not an array */
public static function fromJSONEntry(string $id, mixed $entry): LoRA   // nested `models` shape or flat shape

public function getID(): string
public function getLabel(): string
public function getCategory(): string
public function getModels(): array                 // LoRAModel[]
public function getFiles(): array                  // non-empty official file names of all models
public function getModelNames(): array             // non-empty model names
public function getSource(): string                // source of the first model that has one
public function getNotes(): string                 // first non-empty value across models
public function getWeight(): string                // first non-empty value across models
public function getTriggerWords(): array           // string[]; de-duplicated union across models, first-seen order
public function getTriggerWordsFlattened(): string
public function getLabelLinkedSource(): string     // HTML link to getSource()
```

### `LoRAs\LoRAModel`
Read-only value object for one model variant of a LoRA (e.g. `SDXL 1.0`).

```php
public const string KEY_FILE, KEY_SOURCE, KEY_TRIGGER_WORDS, KEY_MODEL, KEY_NOTES, KEY_WEIGHT
public function __construct(string $modelName, string $source, string $file, array $triggerWords, string $notes, string $weight)
public static function fromArray(string $modelName, array $data): self
public function getModelName(): string
public function getSource(): string
public function getFile(): string                  // official file name
public function getTriggerWords(): array           // string[]
public function getNotes(): string
public function getWeight(): string
```

### `LoRAs\LoRABucket`
Extends `GenericStringPrimaryBasket` (accepts `LoRA` items only).

```php
public function getModels(): array                 // sorted unique model names (LoRA::getModelNames())
public function getCategorized(): array            // category => LoRA[]
public function getByModel(string $modelName): array   // case-insensitive match on model names
public function getByModelAsBucket(string $modelName): LoRABucket
```

## Workflows — `Workflows/`

### `Workflows\WorkflowsCollection`
Extends `BaseStringPrimaryCollection`; items are `WorkflowFile`, one per `*.json` in the ComfyUI workflows folder.

```php
public static function create(): self
public function getDefaultID(): string
public function getWorkflowFiles(): array          // JSONFile[]
```

### `Workflows\WorkflowFile`
Implements `StringPrimaryRecordInterface`. The ID is a short hash of the file's base name.

```php
public function __construct(JSONFile $file)
public function getID(): string
public function getLabel(): string
public function getFile(): JSONFile
public function getModifiedDate(): DateTime
public function getLabelURLFixPaths(): string      // HTML link to the fix-paths action
public function url(): WorkflowFileURL
```

### `Workflows\WorkflowFileURL`

```php
public const string REQUEST_PRIMARY = 'workflow'
public function __construct(WorkflowFile $file)
public function base(array $params = array()): string
public function fixPaths(): string
```

### `Workflows\WorkflowData`
Round-trip owner of one decoded ComfyUI workflow document. It holds the whole document verbatim, so unknown keys, non-array node entries, empty arrays and the node order survive `toArray()` unchanged. The constructor is private.

```php
public static function fromArray(array $data): self   // array<int|string,mixed>; the decoded workflow JSON
public function hasNodes(): bool                      // true when the `nodes` key holds an array
public function toArray(): array                      // the full document, array<int|string,mixed>
public function rewriteFirstWidgetString(string $nodeType, Closure $resolver): int // Closure(string):string
```

`rewriteFirstWidgetString()` visits every node whose `type` equals `$nodeType` and passes the first `widgets_values` entry to the resolver. It skips nodes that are not arrays, have no string `type`, have a missing or non-array `widgets_values`, or whose first value is not a non-empty string. It replaces only that first value (other node types, widget values and node keys stay as they were) and returns the number of values whose resolved result differs from the original; a resolver returning the same string counts as unchanged. Returns `0` when there are no nodes.

### `Workflows\PathFixer`

```php
public function __construct(JSONFile $workflowFile)
public function getLoRAFolder(): FolderInfo        // <ComfyUI>/models/loras
public function fixPaths(): self                   // rewrites LoraLoaderModelOnly paths through WorkflowData
public function getMessages(): array               // PathFixerMessage[]
```

### `Workflows\PathFixerMessage`
Implements `StringableInterface`. Rendered as HTML by `WorkflowsPage`.

```php
public const string TYPE_ERROR, TYPE_WARNING, TYPE_SUCCESS, TYPE_NOTICE
public function __construct(string $type, string|StringableInterface $message, string|int|float ...$args)
public function render(): string                   // "<Type label>: <message>"
public function __toString(): string
```

The `$message` template is trusted HTML and is not escaped. The constructor applies `sprintf()` with `$args` and first HTML-escapes every string argument with `htmlspecialchars()` (`ENT_QUOTES | ENT_SUBSTITUTE`, UTF-8); integer and float arguments are left as they are. Pass any untrusted value (workflow widget values, file names, LoRA IDs) as an argument, never inside the template. Arguments must not be escaped beforehand, or they are escaped twice.

## Web entry points (non-class)

| File | Behavior |
|---|---|
| `index.php` | `OrganizerApp::create()->createUI(APP_WEBROOT_URL, APP_WEBROOT_URL.'/vendor')->display()` |
| `image.php` | Query: `imageID` (MD5, required), `thumbnail` (boolean). Responds 404 when the ID is missing or not in `file_index.json`; otherwise streams the image or thumbnail |

## Frontend classes — `js/`

| Class | Role |
|---|---|
| `ImageBrowser` | Constructed by the inline script emitted from `ImageCollection::injectJS()`; sends the AJAX requests (`SendRequest`), tracks selection, filtering and move targets |
| `ImageHandler` | One instance per image card (`RegisterImage`); search matching, selection and flag state |
| `UserInterface` | Static `ShowStatus(message)` for the timed status bar |
