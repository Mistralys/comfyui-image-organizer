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
public static function verifyMetadata(): bool // read-only census; false when a sidecar fails to parse
public static function postAutoload(): void   // clears the ClassHelper repository cache
```

### `ImageIndexer` — `ImageIndexer.php`

```php
public const int ACTION_UPDATE_PATHS = 180901, ACTION_SKIP_NO_CHECKPOINT = 180902, ACTION_IMAGE_INDEXED = 180903,
                 ACTION_SKIP_UNRECOGNIZED_SIDECAR = 180905, ACTION_SKIP_INVALID_SIDECAR = 180906
public const string INDEX_THUMBNAIL_FILE = 'thumbnailFile'
public const string INDEX_IMAGE_FILE = 'imageFile'

public function __construct(OrganizerApp $app)
public static function updateFileIndex(): void              // rewrites data/file_index.json from the collection
public function findAllImages(): array                      // recursive *.png search in the image folder
public function findFolderImages(string $folderName): array // *.png in one image subfolder
public function indexAll(): self
public function indexFolder(string $folderName): self
public function indexNewFolders(): self                     // indexes only image subfolders with no image in the index yet
public static function filterNewFolders(array $subFolderNames, ImageIndex $index): array // names with no entry in the index
public function detectUpscaledImages(?string $folderName = null): self
public function detectUpscaledInFolder(string $folderName): self
public function cleanUpFolders(): self                      // deletes empty image subfolders
public function getAnalysisResults(): OperationResult_Collection
```

The indexer is a thin orchestrator. It holds `MetadataRegistry::getInstance()`, the loaded index as an `ImageIndex` (private) and the run's `OperationResult_Collection`. In `analyzeImages()` (private, behind `indexAll()`, `indexFolder()` and `indexNewFolders()`) it loads the index, builds one `ImageAnalyzer` for the run, and resolves each PNG through `MetadataRegistry::resolve()`:

- Resolved sidecar: `ImageAnalyzer::analyze()` decides what happens to the image. `analyze()` throws `OrganizerException::ERROR_CANNOT_HASH_IMAGE_FILE` when the MD5 of an image file cannot be computed.
- No resolved sidecar, but unrecognized candidate files: a console skip line and an `ACTION_SKIP_UNRECOGNIZED_SIDECAR` warning. The message names only the image file name. No exception escapes the loop for such a file (e.g. a workflow export beside the PNG).
- No candidate file at all: only the console "no sidecar" line; no result entry.

When both a `-meta.json` and a workflow export exist for a PNG, the registry resolves the `-meta.json` and the export is merely reported as unrecognized. `ACTION_SKIP_UNRECOGNIZED_SIDECAR` and `ACTION_SKIP_INVALID_SIDECAR` are warnings, so `countSuccesses()` and `countNotices()` (read by `ImageBrowser::handleRefreshFolder()`) keep their meaning for legacy images. `ACTION_SKIP_NO_DATE` (180904) was removed together with the indexer's own `analyzeImage()`, `parseText()` and `resolveCheckpoint()`; the code stays unassigned, and `Tests\Metadata\MetadataExceptionTest::testErrorCodesAreUnique` guards that the constants of `MetadataException`, `OrganizerException` and `ImageIndexer` are pairwise distinct.

### `ImageAnalyzer` — `ImageAnalyzer.php`
The per-image decision logic of the indexer, on top of the metadata module. It works only on the `ImageIndex` and `OperationResult_Collection` it is given: no `OrganizerApp`, no storage file, no `data/` access. `ImageIndexer::analyzeImages()` builds one per run, after the index is loaded.

```php
public function __construct(ImageIndex $images, OperationResult_Collection $results) // the index being built and the run's results
public function analyze(FileInfo $imageFile, ResolvedSidecar $sidecar): void          // @throws OrganizerException ERROR_CANNOT_HASH_IMAGE_FILE
public static function completeProperties(array $bag, FileInfo $imageFile): array     // array<string,string> in and out
```

`analyze()` runs these steps in order:

1. Hashes the image (MD5, the entry ID). A failed `hash_file()` throws `ERROR_CANNOT_HASH_IMAGE_FILE`; its PHP warning is absorbed by a temporary error handler.
2. If the index holds an entry for the ID with `modified = true`, calls `ImageIndexEntry::syncLocation()` with the image path, the resolved sidecar's path and the folder name, adds an `ACTION_UPDATE_PATHS` notice only when a value changed, and returns. Label, favorite flag and properties stay as they are.
3. Parses the sidecar with `ResolvedSidecar::getFormat()->parse()`. A `MetadataException` with `ERROR_NO_MODEL_NAME` gives an `ACTION_SKIP_NO_CHECKPOINT` warning; any other `MetadataException` gives an `ACTION_SKIP_INVALID_SIDECAR` warning. Either way the index stays unchanged. The messages name only the image file name, never sidecar content or exception text.
4. Builds the property bag with `completeProperties()`, reads the image size, and decides "upscaled": `upscaleFactor` in the bag, a file name containing `upscale` (see the defect in `constraints.md`), or a width or height of 2000 px or more.
5. Takes the date from `ImageMetadata::getDate()` when the sidecar has one (the legacy format). Otherwise it keeps the date of a stored unmodified entry, and for a new entry uses the PNG's modification time.
6. Sets the entry to the index. An entry already stored under the ID is replaced through `ImageIndexEntry::withKnownFieldsFrom()`, so its unknown keys survive. The checkpoint is `ImageMetadata::getModelName() ?? ''`: a structured sidecar without a model gives an empty checkpoint, not a skip. Adds an `ACTION_IMAGE_INDEXED` success.

`completeProperties()` always sets the folder name from the image's parent folder (taken with `basename(dirname())`, not `FileInfo::getFolder()`, which throws for a folder named like a file, e.g. `SD1.5`). It sets seed, batch number, test number and test name from the file name `<testNr>-<testName>-seed<seed>-<imageNr>` only where the bag lacks the key. A name without a `seed` part gives an empty test name.

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
public function getMetadata(): ImageMetadata       // lazy; see below
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
                    KEY_WEBSITE_IMAGE_ID, KEY_MODEL_NAME, KEY_VAE_NAME, KEY_CLIP_NAMES, KEY_FLOW_TYPE, KEY_IS_PREVIEW,
                    KEY_WORKFLOW_VERSION
// KEY_VAE_NAME = 'VAEName', the key the legacy sidecar's custom_text uses
// KEY_FLOW_TYPE = 'flowType', KEY_IS_PREVIEW = 'isPreview', KEY_WORKFLOW_VERSION = 'workflowVersion'

public function __construct(ImageInfo $image, ArrayDataCollection $data, Closure $modifiedCallback)
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
public function getFlowType(): ?string             // the `flowType` key; null when absent
public function getWorkflowVersion(): ?int         // the `workflowVersion` key; null when absent or empty
public function isPreview(): bool                  // the `isPreview` key; false when absent
public function getUpscaledImage(): ?ImageInfo
public function setUpscaledImage(ImageInfo $image): self
public function getWebsiteImageID(): ?string
public function setWebsiteImageID(string $imageID): self
```

`$modifiedCallback` is a `Closure`, not any `callable`; `ImageInfo` passes a first-class callable. `getWorkflowVersion()` accepts an int or a numeric string (`'32.7'` and `'1e3'` are cast to `32` and `1000`) and returns `null` for any other type, a float included; `0` is returned as `0`. There is one version key, `workflowVersion`, for both sidecar formats, and no `flowVersion` key or fallback getter. `flowType` and `isPreview` are new keys that only `StructuredSidecarFormat` puts in its index-property bag; the indexer stores the bag as it is, so they reach the index for structured sidecars only. A legacy sidecar never has them, so `getFlowType()` is `null` and `isPreview()` is `false` for legacy images.

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
public function getFolderNames(): array                     // string[]: folders with at least one entry (the `folder` property)
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
| `IndexManagerPage` | `BaseOrganizerPage` | `index-manager` | `REQUEST_PARAM_CLEAN_FOLDERS`, `_DETECT_UPSCALED`, `_REFRESH`, `_DISCOVER_FOLDERS` |
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
public const string KEY_LABEL, KEY_CATEGORY, KEY_MODELS, KEY_DESCRIPTION

/** @param LoRAModel[] $models */
public function __construct(string $id, string $label, string $category, array $models, string $description = '')

/** @throws OrganizerException ERROR_INVALID_LORA_ENTRY when $entry is not an array */
public static function fromJSONEntry(string $id, mixed $entry): LoRA   // nested `models` shape or flat shape

public function getID(): string
public function getLabel(): string
public function getCategory(): string
public function getDescription(): string           // entry-level `description` key; '' when absent
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

## Metadata — `Metadata/`

The sidecar metadata module: a format-independent, immutable description of one image's sidecar, the contract a sidecar format implements, and the registry that finds and parses a sidecar. Namespace `Mistralys\ComfyUIOrganizer\Metadata`. This module holds the value objects, the format interface, the shared base class and `MetadataRegistry`; concrete formats live in `Metadata/Formats/` (see the interface description).

### `Metadata\MetadataException`
Extends `OrganizerException`.

```php
public const int ERROR_NO_MODEL_NAME = 183001
public const int ERROR_INVALID_STRUCTURE = 183002
```

### `Metadata\ImageMetadata`
Final, immutable result of parsing a sidecar. No setters; every property is `readonly`. Build it with named arguments: every constructor parameter except `$formatID` defaults to `null` (or an empty array), so a field a format does not supply is `null` by construction. `null` means "the sidecar does not say" and is distinct from an empty value.

```php
public function __construct(
    string $formatID,
    ?Microtime $date = null, ?string $modelName = null, ?string $vaeName = null, ?string $clipNames = null,
    ?string $seed = null, ?int $width = null, ?int $height = null, ?float $cfg = null,
    ?string $positivePrompt = null, ?string $negativePrompt = null,
    ?string $testName = null, ?int $testNumber = null, ?int $batchNumber = null,
    ?string $folderName = null, ?string $filePrefix = null, ?bool $embedFlow = null, ?string $loraTriggers = null,
    ?FlowInfo $flow = null, ?SamplerInfo $sampler = null, ?UpscaleInfo $upscale = null,
    ?array $loras = null,            // LoRAUsage[]|null: null = format cannot say, [] = none used
    array $indexProperties = array(),// array<string,string>: the flat property bag persisted in the index
    array $rawData = array())        // array<int|string,mixed>: the decoded sidecar

public function getFormatID(): string
public function getDate(): ?Microtime
public function getModelName(): ?string
public function getVaeName(): ?string
public function getClipNames(): ?string            // raw string as stored in the sidecar
public function getSeed(): ?string                 // a string: seeds can exceed PHP_INT_MAX
public function getWidth(): ?int
public function getHeight(): ?int
public function getCFG(): ?float
public function getPositivePrompt(): ?string
public function getNegativePrompt(): ?string
public function getTestName(): ?string
public function getTestNumber(): ?int
public function getBatchNumber(): ?int
public function getFolderName(): ?string
public function getFilePrefix(): ?string
public function isEmbedFlow(): ?bool
public function getLoRATriggers(): ?string         // raw string as stored in the sidecar
public function getFlow(): FlowInfo                // never null; its fields may be
public function getSampler(): SamplerInfo          // never null; its fields may be
public function getUpscale(): UpscaleInfo          // never null; its fields may be
public function getLoRAs(): ?array                 // LoRAUsage[]|null
public function getIndexProperties(): array        // array<string,string>
public function getRawData(): array                // array<int|string,mixed>
```

### Sub-objects
Final, immutable value objects. Every constructor parameter is optional and defaults to `null` (except `LoRAUsage::$id`); every getter is nullable except `LoRAUsage::getID()`.

| Class | Constructor parameters | Getters |
|---|---|---|
| `Metadata\FlowInfo` | `?string $typeName, ?int $version, ?bool $preview, ?bool $negativePromptEnabled` | `getTypeName(): ?string`, `getVersion(): ?int`, `isPreview(): ?bool`, `isNegativePromptEnabled(): ?bool` |
| `Metadata\SamplerInfo` | `?string $name, ?string $scheduler, ?int $steps, ?int $previewSteps, ?int $refinerSteps` | `getName()`, `getScheduler()`, `getSteps()`, `getPreviewSteps()`, `getRefinerSteps()` |
| `Metadata\UpscaleInfo` | `?float $factor, ?int $steps, ?float $calculatedFactor, ?bool $upscaled, ?string $modelName` | `getFactor()`, `getSteps()`, `getCalculatedFactor()`, `isUpscaled()`, `getModelName()` |
| `Metadata\LoRAUsage` | `string $id, ?float $strength, ?string $triggerWords` | `getID(): string`, `getStrength(): ?float`, `getTriggerWords(): ?string` |

`UpscaleInfo::getFactor()` is only the configured setting; `isUpscaled()` says whether the image was upscaled.

### `Metadata\SidecarFormatInterface`
One sidecar schema family. Implementations live in `Metadata/Formats/` and are discovered by `MetadataRegistry` through a folder scan; the interface declares exactly these methods.

```php
public function getID(): string
public function getPriority(): int                 // lower numbers are tried first
public function getSidecarCandidates(FileInfo $image): array // string[]: candidate sidecar paths, best match first
public function detect(ArrayDataCollection $data): bool      // whether the decoded content belongs to this format
public function parse(ArrayDataCollection $data): ImageMetadata // @throws MetadataException
```

### `Metadata\BaseSidecarFormat`
Abstract; implements `SidecarFormatInterface`. Provides the lenient readers below, all `protected`. Each reads one key of an `ArrayDataCollection` and returns `null` both for an absent key and for a value of another type, so "absent" stays distinguishable from "empty" (`''`, `0`, `false`, `[]`).

```php
protected function readString(ArrayDataCollection $data, string $key): ?string  // strings only
protected function readInt(ArrayDataCollection $data, string $key): ?int         // ints only; 5.0 yields null
protected function readFloat(ArrayDataCollection $data, string $key): ?float     // floats, plus ints widened to float
protected function readBool(ArrayDataCollection $data, string $key): ?bool       // bools only
protected function normalizeFloat(float|int $value): string                      // (string)round($value, 4)
protected function isObjectSection(ArrayDataCollection $data, string $key): bool // true for a JSON object, the empty object included; false for a list, a scalar or an absent key
```

`readInt()` is strict while `readFloat()` widens, because JSON writers emit `4` for `4.0` but a whole float is never a valid integer field. `normalizeFloat()` strips floating-point noise (`6.200000000000001` gives `'6.2'`, `0.4000000000000001` gives `'0.4'`); it rounds to 4 decimals, so `-0.0` gives `'-0'`, values below 0.00005 collapse to `'0'`, and `NAN` / `INF` pass through.

### Resolution value objects

```php
// Metadata\ResolvedSidecar — final; a sidecar file with the format that recognized it
public function __construct(JSONFile $sidecar, SidecarFormatInterface $format, ArrayDataCollection $data)
public function getSidecar(): JSONFile
public function getFormat(): SidecarFormatInterface
public function getData(): ArrayDataCollection     // the decoded content

// Metadata\SidecarResolution — final; the outcome of looking for one image's sidecar
public function __construct(?ResolvedSidecar $sidecar = null, array $unrecognizedCandidates = array()) // JSONFile[]
public function getSidecar(): ?ResolvedSidecar
public function isResolved(): bool                 // false when built without a sidecar
public function getUnrecognizedCandidates(): array // JSONFile[]: existing candidates no format recognized
```

### `Metadata\MetadataVerifier`
Read-only census of how the sidecars of a set of images resolve and parse. Non-final class; it holds only the registry, so it does not touch the index, the application or any file other than reading the candidates through the registry.

```php
public function __construct(MetadataRegistry $registry)
/** @param FileInfo[] $images */
public function census(array $images): array // array{formats: array<string,int>, noSidecar: string[], unrecognized: string[], noModelName: string[], failures: string[]}
```

`census()` resolves each image through the registry and sorts it into exactly one outcome. The lists hold image file names, not paths:

- `noSidecar`: no candidate file exists.
- `unrecognized`: candidate files exist but no format recognized any of them (this includes a sidecar that is not valid JSON, whose root is not an object, or a workflow export).
- `noModelName`: the format's `parse()` threw `MetadataException::ERROR_NO_MODEL_NAME`. Reported, not a failure; it is a known state of legacy sidecars.
- `failures`: `parse()` threw any other `MetadataException`.
- `formats`: parsed images counted per `ImageMetadata::getFormatID()`, sorted by format ID.

Only `MetadataException` is caught per image; any other throwable from a format aborts the census.

`Builder::verifyMetadata()` (Composer script `verify-metadata`) finds all `*.png` files under `APP_IMAGE_FOLDER` (recursive, without `OrganizerApp::create()` or any `data/` access), runs the census with `MetadataRegistry::getInstance()` and prints the count per format ID and the four lists. It returns `false` only when `failures` is not empty, which Composer turns into exit status 1; a callable's `int` return would be ignored.

### `Metadata\Formats\LegacySidecarFormat`
Extends `BaseSidecarFormat`. The legacy sidecar: a JSON file whose first top-level key is the generation date, holding the properties and a `custom_text` string of `-name:value` pairs.

```php
public const string FORMAT_ID = 'legacy'
public const int PRIORITY = 200

public function getSidecarCandidates(FileInfo $image): array   // [<png path without a trailing .png, case-insensitive>.json]
public function detect(ArrayDataCollection $data): bool
public function parse(ArrayDataCollection $data): ImageMetadata // @throws MetadataException
public static function resolveCheckpoint(mixed $sidecarValue, string $fallback): string // a string sidecar value wins, even when empty; any other value yields $fallback
```

- `detect()` looks at the first top-level key only; further keys are ignored. It accepts a file whose first value is an object (an empty one included) and rejects an empty file, a first value that is not an object (a non-empty list counts as not an object), a structured-shaped file (`image`, `prompt` and `sampler` objects) and a workflow export (`nodes` and `links`).
- `parse()` returns the `custom_text` bag untouched from `ImageMetadata::getIndexProperties()`, so legacy indexing stays byte-identical. Typed getters filled: model name (checkpoint, else the bag's model name), VAE name, clip names and LoRA trigger strings (raw), positive / negative prompt (the bag, else the top-level `positive_prompt` / `negative_prompt`), date (the key, `/` replaced by `-`), `FlowInfo::getVersion()` (`workflowVersion`), `UpscaleInfo::getFactor()` (`upscaleFactor`) and `getModelName()` (`upscale_model`). Everything else, including seed, CFG and size, stays `null`; `getLoRAs()` is `null`.
- `parse()` throws `MetadataException::ERROR_NO_MODEL_NAME` when the resolved model name is empty (a model named `"0"` counts as empty), and `ERROR_INVALID_STRUCTURE` when the first key is not a date string holding an object; a `Microtime_Exception` from a non-date key is converted to the latter.

### `Metadata\Formats\StructuredSidecarFormat`
Extends `BaseSidecarFormat`. The structured sidecar: a JSON file with the `image`, `prompt` and `sampler` objects plus optional `model`, `flow`, `upscale` and `loras` sections. Every section and key is optional; what is missing is `null` in the metadata and absent from the property bag.

```php
public const string FORMAT_ID = 'structured'
public const int PRIORITY = 100

public function getSidecarCandidates(FileInfo $image): array   // see below
public function detect(ArrayDataCollection $data): bool        // image, prompt and sampler are all objects
public function parse(ArrayDataCollection $data): ImageMetadata // lenient: never throws
```

- Candidates: `x-nr1_00001_.png` gives `x-nr1-meta.json`, then `x-nr1-meta_00001.json`; a name without the trailing `_<digits>_` counter gives `<name>-meta.json` only. The folder comes from `dirname()`, not `FileInfo::getFolder()`, because `FolderInfo` throws for a folder name that looks like a file name (e.g. `SD1.5`).
- `parse()` reads the model name from `model.prettyName` (`null` without a `model` section; the skip rule for a missing model belongs to the caller), `getDate()` is always `null`, and `getLoRAs()` is `null` without a `loras` section, `[]` for an empty one. Scalar entries in `loras` (`_collectedTriggerWords`, `loraTriggers`) yield no `LoRAUsage`; `getLoRATriggers()` is the top-level `loraTriggers` string. `getRawData()`, `embedFlow`, `UpscaleInfo::getCalculatedFactor()`, and `SamplerInfo` preview / refiner steps are exposed.
- `getIndexProperties()` maps onto the existing bag keys: `testName`, `testNumber`, `imgBatchNr`, `seed`, `imgCFG`, `imgWidth`, `imgHeight`, `promptPositive`, `promptNegative`, `sampler`, `samplerSteps`, `scheduler`, one key per LoRA ID (its strength), `upscaleFactor`, `workflowVersion`, `flowType` and `isPreview`. All values are strings; a key whose source is absent is omitted.

### `Metadata\MetadataRegistry`
Knows the sidecar formats and finds the sidecar of an image by recognizing the content of its candidate files. Non-final class with a private constructor; formats are sorted by `getPriority()` (lower first), so `structured` precedes `legacy`.

```php
public static function create(): self                          // scans Metadata/Formats/ (uncached) and instantiates every SidecarFormatInterface class
public static function getInstance(): self                     // one shared instance per process, built by create()
public static function createWithFormats(array $formats): self // SidecarFormatInterface[]; exactly these formats, no scan
public function getFormats(): array                            // SidecarFormatInterface[] in priority order
public function resolve(FileInfo $image): SidecarResolution
public function parseFile(JSONFile $file): ImageMetadata      // @throws MetadataException (ERROR_INVALID_STRUCTURE) if no format recognizes the content
```

- Discovery uses `ClassHelper::findClassesInFolder()` on `Metadata/Formats/` directly, not the class cache folder that only `prepend.php` sets up, so the registry also works under the plain `vendor/autoload.php` bootstrap (PHPUnit). A new format needs no registration; run `composer dump-autoload` so the classmap contains it.
- `resolve()` walks the formats in priority order and reads each existing candidate file once. A candidate is accepted only when the format's `detect()` recognizes its content; the first accepted candidate wins, but the walk continues so that every other existing candidate no format recognized is reported in `SidecarResolution::getUnrecognizedCandidates()`, also when a sidecar was resolved (e.g. a workflow export beside a `-meta.json`). A PNG without any candidate file gives an unresolved result and an empty list.
- A candidate that is not readable JSON, or whose root is not an object (scalar, `null`, or a list), is treated as unrecognized and raises no exception. The list check runs before the data is wrapped in `ArrayDataCollection`, which would present a list's indexes as string keys. An empty object is accepted as a root and left to `detect()`.
- `parseFile()` is the lazy path for one known file: it applies the first format whose `detect()` accepts the content and returns that format's `parse()` result.
- Known limits: a UTF-8 BOM before the JSON makes the file unrecognized, and a directory named like a candidate (`dir.json/`) triggers a PHP warning from the file read before it is reported as unrecognized. The read has no `is_file()` guard and no size cap.

The consumers of the module are `ImageIndexer` (calls `resolve()` per image and hands the result to `ImageAnalyzer`), `ImageAnalyzer` (takes a `ResolvedSidecar` and calls its format's `parse()`), `MetadataVerifier` (calls `resolve()`) and `ImageInfo::getMetadata()` (calls `parseFile()`). The browser does not read `getMetadata()` yet.

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
