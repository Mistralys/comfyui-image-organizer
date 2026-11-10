# Architecture

Which classes exist, what each is for, and who calls whom. Signatures, per-method contracts and the reasons behind deliberate quirks are not here: they live in the source, as docblocks and comments at the site, and in the tests that pin them. Runtime paths through these classes are in [data-flows.md](data-flows.md); binding conventions are in [constraints.md](constraints.md).

All classes live under `src/ComfyUIOrganizer/` in the namespace `Mistralys\ComfyUIOrganizer`; sub-namespaces mirror the folders. Framework base classes (`X4Application`, `BasePage`, `BaseAjaxMethod`, `BaseStringPrimaryCollection`, ...) come from dependencies and are not described here.

## Module map

| Module (folder) | Classes | Role |
|---|---|---|
| Application core (`/`) | `OrganizerApp`, `OrganizerException`, `URLs`, `ComfyUI`, `SessionState`, `Builder` | `OrganizerApp` is the process-wide root: it knows the ComfyUI and image folders, the storage files under `data/`, builds the image collection and discovers pages and AJAX methods by folder scan. `Builder` holds the static entry points of the Composer scripts. `SessionState` is the only access to `$_SESSION`. `ComfyUI` locates the ComfyUI workflows folder. |
| Indexing (`/`) | `ImageIndexer`, `ImageAnalyzer`, `UpscaledPairsMigration` | `ImageIndexer` orchestrates a run over the image folder; `ImageAnalyzer` decides what happens to each image with a resolved sidecar. `UpscaledPairsMigration` is a one-off, run by `Builder`, that converts the retired index-linked upscaled pairs to `_upscaled` files; delete it after the run. |
| Index data (`/`) | `ImageIndex`, `ImageIndexEntry`, `FileIndex`, `ImageCollection`, `ImageInfo`, `ImageProperties` | `ImageIndex` and `ImageIndexEntry` are the typed round-trip form of `data/images.json`. `FileIndex` patches single entries of `data/file_index.json`. `ImageCollection` loads the index and exposes it as `ImageInfo` records; `ImageProperties` is the typed view over an image's property bag. |
| Metadata (`Metadata/`, `Metadata/Formats/`) | `MetadataRegistry`, `SidecarFormatInterface`, `BaseSidecarFormat`, `LegacySidecarFormat`, `StructuredSidecarFormat`, `ImageMetadata`, `FlowInfo`, `SamplerInfo`, `UpscaleInfo`, `LoRAUsage`, `ResolvedSidecar`, `SidecarResolution`, `MetadataVerifier`, `MetadataException` | Finds the sidecar of an image, recognizes its format and parses it into a format-independent, immutable `ImageMetadata`. |
| LoRAs (`LoRAs/`) | `LoRAsCollection`, `LoRA`, `LoRAModel`, `LoRABucket` | The hand-written LoRA reference data of `data/loras/`. |
| Workflows (`Workflows/`) | `WorkflowsCollection`, `WorkflowFile`, `WorkflowFileURL`, `WorkflowData`, `PathFixer`, `PathFixerMessage` | ComfyUI workflow files and the path fixing of their LoRA loader nodes. |
| Pages (`Pages/`) | `BaseOrganizerPage` (in `/`), `ImageBrowser`, `ImageDetails`, `IndexManagerPage`, `LoRAOverviewPage`, `WorkflowsPage` | One class per UI page; found by a folder scan. |
| AJAX (`Ajax/`, `Ajax/Methods/`) | `BaseImageMethod`, one class per action under `Methods/` (`CopyToOutputMethod`, `DeleteImageMethod`, `DeleteImagesMethod`, `FavoriteImageMethod`, `MoveImageMethod`, `SendToWebsiteMethod`, `SetCardSizeMethod`, `SetForGalleryMethod`, `SetForWebsiteMethod`, `SetLabelMethod`) | Browser actions on one image; found by a folder scan. |
| Web entry points (project root) | `index.php`, `image.php` | `index.php` runs the UI. `image.php` streams an image or thumbnail resolved through `data/file_index.json`. |
| Frontend (`js/`) | `ImageBrowser`, `ImageHandler`, `UserInterface` | Plain JavaScript classes. `ImageBrowser` sends the AJAX requests and tracks selection and filters; `ImageHandler` is one instance per image card; `UserInterface` shows the status bar. |

## Who calls whom

### Indexing and the index

- `Builder`, `IndexManagerPage` and `ImageBrowser` (per-folder refresh) start an indexing run through `ImageIndexer`.
- `ImageIndexer` loads `data/images.json` into an `ImageIndex`, builds one `ImageAnalyzer` for the run, and resolves each PNG through `MetadataRegistry::resolve()`. It reads no sidecar itself.
- `ImageAnalyzer` receives the `ResolvedSidecar`, calls its format's `parse()`, and writes `ImageIndexEntry` records into the `ImageIndex`. It knows nothing of `OrganizerApp` or `data/`.
- `ImageCollection::writeFileIndex()` writes `data/file_index.json`, the lookup `image.php` serves from; `ImageIndexer` rebuilds it, moves and deletes patch single entries with `updateFileIndexEntries()`.
- `ImageCollection` loads the same `ImageIndex` and exposes each entry as an `ImageInfo` through `ImageInfo::fromEntry()`; `ImageInfo::toEntry()` and `ImageCollection::save()` carry changes back. `ImageInfo` hands out an `ImageProperties` view over its property bag.
- Pages and AJAX methods get the `ImageCollection` from `OrganizerApp::createImageCollection()`.

### Metadata module

- `MetadataRegistry` discovers the `SidecarFormatInterface` classes in `Metadata/Formats/` and tries them in priority order: `StructuredSidecarFormat` before `LegacySidecarFormat`.
- The consumers of the module are `ImageIndexer` (`resolve()` per image), `ImageAnalyzer` (the resolved format's `parse()`), `MetadataVerifier` (`resolve()` and `parse()` for the census run by `Builder::verifyMetadata()`) and `ImageInfo::getMetadata()` (`parseFile()`).
- The module depends on none of the indexer, the pages or `ImageInfo`; it uses `AppUtils`, `OrganizerException` and the `ImageProperties` key constants only.

### Web layer

- `OrganizerApp::registerPages()` and `registerAjaxMethods()` find the page and method classes by scanning `Pages/` and `Ajax/Methods/`; nothing is registered by hand.
- Every page except `ImageDetails` extends `BaseOrganizerPage`, which provides the shared navigation; `URLs` builds the page URLs.
- Every image-bound AJAX method extends `BaseImageMethod`, which resolves the image through `ImageCollection::getFromRequest()`. `ImageCollection::injectJS()` hands the method names and payload keys to the frontend.
- `ImageBrowser` keeps its UI state through `SessionState`.

### LoRAs and workflows

- `LoRAsCollection` loads every `*.json` under `data/loras/` into `LoRA` records, each with its `LoRAModel` variants; `LoRABucket` groups them for display.
- `ImageProperties::getLoRASummary()` and `PathFixer` both read `LoRAsCollection`.
- `WorkflowsPage` lists `WorkflowFile` records from `WorkflowsCollection` (folder from `ComfyUI`), links to the fix action through `WorkflowFileURL`, runs `PathFixer`, and renders its `PathFixerMessage` records.
- `PathFixer` edits a workflow only through `WorkflowData`.

## Where the details are

| Looking for | Look at |
|---|---|
| A signature | The class in `src/ComfyUIOrganizer/` |
| Why a method behaves oddly, or what must not be "fixed" | The docblock or comment at the site |
| A known defect or missing capability | The `// TODO:` at the site, and the known-gap list in [constraints.md](constraints.md) |
| A claim that is enforced | The test in `tests/`; tests that pin a known defect have `Characterisation` in their name |
| Runtime paths, data stores and their shapes | [data-flows.md](data-flows.md) |
