# Constraints & Conventions

This document is the manifest's only binding one. Conventions below were verified against the code at the commit recorded in [curation-log.md](curation-log.md); where a section describes a known defect rather than a rule, it says so.

## Contracts and rationale live with the code

- Write a method's or class's contract, its rationale and its "watch out" as a docblock or one-line comment at the site, and change it in the same diff as the code. Do not restate it in the manifest.
- Enforce a claim with a test where that is practical; a prose note is the fallback.
- Record a defect or missing capability as a `// TODO:` at the site, and add it to the known-gap list below when agents must be warned before touching the area. Keep history ("was removed together with ...") in Git, not in comments or the manifest.
- Do not list signatures in the manifest. [architecture.md](architecture.md) holds the module map and the cross-class collaboration; this document holds only the conventions that bind several classes.

## Environment

- Target PHP 8.4 or newer. The code uses typed class constants, `new Foo()->method()` without parentheses and `array_any()`, and `composer.json` declares `"php": ">=8.4"`.
- Keep `config.php` out of version control and derive it from `config.dist.php`. Its constants live in the namespace `Mistralys\ComfyUIOrganizer\Config` and are imported with `use const`.
- Treat `APP_WEBSITE_API_URL` as optional and personal; code outside the "Send to website" feature must not depend on it.
- Bootstrap every entry point (web script, Composer script) through `prepend.php`. Do not construct `OrganizerApp` before it has run.

## Class discovery and autoloading

- Add a new page as a class in `src/ComfyUIOrganizer/Pages/` and a new AJAX action as a class in `src/ComfyUIOrganizer/Ajax/Methods/`. Do not register them by hand; `OrganizerApp` finds them by scanning those folders.
- Keep non-page classes out of `Pages/`: the page scan instantiates every class it finds there and requires a `BasePage` instance. The AJAX scan registers only classes in `Ajax/Methods/` that implement `AjaxMethodInterface` and ignores the rest.
- Run `composer dump-autoload` after adding, moving or renaming a class. Autoloading is classmap-based, and the `post-autoload-dump` hook (`Builder::postAutoload`) clears the class-discovery cache in `cache/`.
- Give every AJAX method a unique `METHOD_NAME`, and return it from `getID()`.
- Expose any AJAX method that the browser UI calls in the map built by `ImageCollection::injectJS()` and handle it in `js/ImageBrowser.js`; the JS side reads method names and payload keys from that map instead of hard-coding them.

## Image identity and the sidecar contract

- Identify an image by the MD5 hash of its PNG file. Request parameters carrying an image ID must be validated as MD5 (`setMD5()`), as `ImageCollection::getFromRequest()` and `image.php` do.
- Pair every PNG with a JSON sidecar in the same folder: a legacy sidecar of the same base name, or a structured `<name>-meta.json` / `<name>-meta_<nr>.json`. `MetadataRegistry::resolve()` finds it; the indexer never builds a sidecar path itself. PNGs without a recognized sidecar are skipped, and a sidecar that exists but no format recognizes (e.g. a workflow export) is reported as a warning.
- Expect a legacy sidecar to hold a single top-level key (the date) whose value contains `checkpoint` and `custom_text` (`/` separators in the date are normalized to `-`). An image with neither a `checkpoint` nor a `modelName` property is skipped. A structured sidecar has no date; a missing model name gives an empty checkpoint, not a skip.
- Write sidecar `custom_text` in the project syntax: `-name:value` pairs, hyphen as separator, colon before the value, and backticks around any value that contains a hyphen or colon.
- Keep ComfyUI image file names in the form `<testNr>-<testName>-seed<seed>-<imageNr>.png` (hyphens or underscores as separators). The indexer derives test number, test name, seed and batch number from the name only when the sidecar properties lack them.
- Treat each immediate subfolder of the image folder as one image group. The "folder name" property is the name of the image's parent folder, and moving an image targets a sibling folder.
- Store LoRA weights in the image property bag under the LoRA's ID as key. `ImageProperties::getLoRASummary()` recognizes LoRAs only by that match.

## Index integrity

- Persist every change to indexed images through `ImageCollection::save()`. Mutating `ImageInfo` or `ImageProperties` marks the image as changed and sets its `modified` flag; what `save()` re-derives and what it writes is in its docblock.
- Preserve the `modified` flag semantics: the indexer never re-analyzes an image whose index entry has `modified = true`. For such entries it updates only the image path, sidecar path and folder name.
- Keep the favorite → gallery → website cascade: enabling "for website" enables "for gallery", and enabling "for gallery" enables "favorite". Disabling does not cascade.
- Treat a regular/upscaled pair as one unit. Images that have an upscaled replacement are hidden in the browser; moving or deleting the upscaled image also acts on its low-resolution versions (`findLowResVersions()`), and registering a low-resolution image (`registerLowResImage()`) merges flags and label between the pair.
- Link upscaled images automatically only for pairs: `detectUpscaledImages()` ignores any settings-hash group that does not contain exactly two images.
- Refuse to overwrite on move: `moveToFolder()` throws `OrganizerException::ERROR_CANNOT_MOVE_FILE_EXISTS` when the target file exists, and copies before deleting so a failed save does not lose the image.
- Validate folder names for moves with `^[A-Za-z0-9_-]+$` (`MoveImageMethod`).
- Keep unknown index keys when the indexer re-analyzes an image: replace a stored entry through `ImageIndexEntry::withKnownFieldsFrom()`, never with a freshly built entry. New entries are written with explicit `modified` and `label` keys.
- Read every JSON file into a typed data object at the point of reading and pass that object on, not the decoded array: `ImageIndex` / `ImageIndexEntry` for `data/images.json`, `LoRA` / `LoRAModel` for `data/loras/*.json`, `WorkflowData` for workflow files. A data object that is written back (`ImageIndexEntry`, `WorkflowData`) must re-emit every key it does not model, so unknown keys survive a round trip.
- Take a backup of `data/images.json` before each indexing run; `ImageIndexer` does this into `data/backup/`. Do not remove that step.
- Keep `data/file_index.json` in step with the index. `image.php` resolves every image and thumbnail request through it, and only `ImageIndexer::updateFileIndex()` writes it (run by every indexing pass and by `composer update-file-index`). Moves and deletes performed through the UI do not rewrite it.
- Serve a `.jpg` sibling instead of the `.png` when one exists (`image.php`); the JPG is the edited version. The `.psd` and `_websiteImage.png` siblings are located by replacing the `.png` extension of the image path.
- Name thumbnails `cache/<id>-<width>.jpg`; they are regenerated when the source file is newer.

## Sidecar metadata module (`Metadata/`)

- Treat `ImageMetadata` and its sub-objects as immutable value objects, and keep `null` ("the sidecar does not say") distinct from an empty value. Their docblocks state the details.
- Prefer the `BaseSidecarFormat` readers over reads from the raw array in a format, and run any float that becomes a string through `normalizeFloat()`. The readers' strictness and the rounding rules are in their docblocks.
- Throw `MetadataException` from `SidecarFormatInterface::parse()`, not the base `OrganizerException`. Its codes are in the `183xxx` range; allocate new ones from it. Integer error and action constants of `MetadataException`, `OrganizerException` and `ImageIndexer` must stay pairwise distinct, and `180904` (the retired `ACTION_SKIP_NO_DATE`) stays unassigned; `MetadataExceptionTest::testErrorCodesAreUnique` reads them by reflection and fails on a clash.
- Put a sidecar format in `Metadata/Formats/` as a concrete class implementing `SidecarFormatInterface`. Do not register a format by hand: `MetadataRegistry::create()` scans the folder, and `composer dump-autoload` makes a new class visible. `MetadataRegistryTest` pins that every class in the folder is a concrete format.
- Keep `Metadata/` free of dependencies on the indexer, the browser and `ImageInfo`; it uses only `AppUtils`, `OrganizerException` and the `ImageProperties` key constants. `MetadataModuleDependenciesTest` enforces the import list.
- Keep `resolveCheckpoint()` and `parseText()` on `Formats\LegacySidecarFormat` as the only copies; the indexer has none. The per-image rules live only in `ImageAnalyzer::analyze()`, which `ImageIndexer::analyzeImages()` calls for every resolved sidecar. Do not re-add per-image logic or a `str_replace('.png', '.json', ...)` sidecar lookup to the indexer.
- Keep the legacy sidecar path byte-identical to the retired indexer behaviour. its quirks are deliberate; the notes at `LegacySidecarFormat` and `ImageAnalyzer::analyze()` say which, and tests under `tests/` pin them where practical.
- Treat every value taken from a sidecar (prompts, model, clip and LoRA names, folder name, file prefix, and the index values derived from them: checkpoint, test name, seed) as unsanitized text. HTML-escape it at output, and never build file paths from it.
- Read extra sidecar data (anything the index does not store) through `ImageInfo::getMetadata()`, not by adding keys to `images.json`.

## Workflows and LoRA files

- Match LoRAs to files by the official file name (the name given by the download site). Keep that name intact as a suffix when renaming local `.safetensors` files; the `PathFixer` matches the lowercase end of the local file name.
- Limit `PathFixer` rewrites to the first widget value of `LoraLoaderModelOnly` nodes. The backup and object-restoration rules are in its docblock.
- Mutate a decoded workflow only through `WorkflowData` (it keeps the whole document verbatim); do not loop over the raw decoded array by reference.
- Pass every untrusted value (workflow values, file names, LoRA IDs) to `PathFixerMessage` as an argument, never inside the template. `WorkflowsPage` does not escape the messages again, so a new message source must follow the same rule; the escaping details are in the class docblock.
- Read workflows from `<ComfyUI>/user/default/workflows`, not from this repository's `docs/workflows/`; the latter holds reference copies only.
- Keep LoRA IDs unique across all JSON files under `data/loras/`; they are loaded into one collection.

### Known gaps in the code (do not rely on)

Each gap also has a `// TODO:` at its site; the entry here is the index agents are routed to.

- **Unsupported `files` key:** `LoRA::fromJSONEntry()` reads only the singular `file` key per model, so an entry that lists several files under a plural `files` array would get no file for path fixing. No entry in `data/loras/` uses that key at present.
- **Silent file-name collisions:** in `PathFixer::buildFileIndex()` two LoRAs sharing the same official file name overwrite each other in the known-file map without a warning.
- **Upscale detection by name:** the file-name check calls `str_contains()` with its arguments reversed, so it effectively never matches. The defect is in `ImageAnalyzer::analyze()`; `ImageAnalyzerTest::testUpscaleFlagCharacterisation` pins it. Fixing the check needs its own plan, which also updates that test.
- **Whole-file restoration in `fixPaths()`:** the `{}` restoration is a `str_replace` over the written file's text, so every `flags`, `config` or `properties` key holding an empty array becomes an object, at any depth and not only on nodes.
- **Candidate read in `MetadataRegistry`:** a UTF-8 BOM makes a sidecar unrecognized, a directory named like a candidate raises a PHP warning, and there is no `is_file()` guard or size cap. A characterisation test pins the BOM case.
- **Unescaped sidecar-derived output:** `ImageBrowser` and `ImageDetails` echo some sidecar-derived values (checkpoint, test name, folder name) without HTML-escaping them.
- **Parts without automated tests:** `ImageIndexer` (its per-image logic is covered through `ImageAnalyzer` and `filterNewFolders()` by its own test, but the sidecar branching in `analyzeImages()` is not), `PathFixer::fixPaths()` and `ImageBrowser` need the application bootstrap and are untested. Verify them manually through the UI and the Composer scripts; for `fixPaths()`, run it end to end on a copy of a workflow. `WorkflowData` and `PathFixerMessage` are covered by `tests/Workflows/`.
- **Corrupt PNG aborts a run:** `ImageHelper::getImageSize()` raises an uncaught `ImageHelper_Exception` for an unreadable PNG in `ImageAnalyzer::analyze()`; a characterisation test pins it.
- **PHPStan reports one error:** `ImageInfo::getWebsiteBadge()` tests `isForWebsite()` again inside an `if` that already tested it, which level 9 flags as `if.alwaysTrue`. The "free of errors" rule below is not met until it is fixed.

## Testing and static analysis

- Put PHPUnit tests in `tests/` and run them through the `composer test*` scripts; PHPStan analyses `tests/` as well as `src/`. Tests are `final` classes extending `TestCase` in the namespace `Mistralys\ComfyUIOrganizer\Tests`, with sub-namespaces mirroring the `src/` folders (`Tests\LoRAs`, `Tests\Metadata`, `Tests\Workflows`); tests that touch files write to `sys_get_temp_dir()`, never to `data/`.
- Tests that need image files (`ImageAnalyzerTest`) create a unique folder under `sys_get_temp_dir()` and remove it in `tearDown()`, generate PNGs with GD, and call `Console::setEnabled(false)` in `setUp()` and `Console::setEnabled(true)` in `tearDown()` so the indexer's console output stays out of the test run. They run without `OrganizerApp`.
- Keep `composer analyze` (level 9, no baseline file) free of errors. Fix the cause rather than suppressing it: type arrays with PHPDoc value types, and do not add `@phpstan-ignore` comments.
- Name a test that pins a known defect as a characterisation test (`...Characterisation`), as `testUpscaleFlagCharacterisation` does. When the defect is fixed, update the test in the same change.
- `phpstan.neon` lists `config.dist.php` and `prepend.php` under `scanFiles` so PHPStan knows the `Config\APP_*`, `CACHE_FOLDER` and `OUTPUT_FOLDER` constants; add any new global constant file there.
- Do not commit `phpstan-result.txt`, `.phpunit.cache/` or `.phpunit.result.cache`; all are git-ignored tool output.
- `tests/fixtures/sidecars/` holds verbatim copies of real sidecars (plus one synthetic `workflow-export.json`) and the `*.expected-bag.json` oracles for the legacy format; `tests/fixtures/sidecars/README.md` lists the sources. Never edit, reformat or re-save them: they are CRLF, and `.gitattributes` marks `tests/fixtures/** -text` so Git does not normalize the line endings.

## UI and frontend

- Wrap user-visible strings in the localization functions (`t()`, `pt()`, `pts()`); both the `AppLocalize` and `AppUtils` namespaces are in use.
- Render pages as inline PHP templates inside `_render()`; use `OutputBuffering` when HTML must be returned as a string.
- Keep browser UI state (card size, filter toggles, active folder) in the session, and validate request values against fixed enums as `ImageBrowser` does. Read and write the session only through `SessionState`; do not access `$_SESSION` directly. A stored value of the wrong type reads as the default, so store strings and booleans only.
- Make AJAX methods answer through `sendSuccess()` / `sendError()` (never plain output). `BaseImageMethod::sendSuccess()` re-reads the image to attach its flags, so an action that deletes the image must call `$this->methods->sendSuccess()` instead, as `DeleteImageMethod` does.
- Keep JavaScript as plain classes loaded through `UserInterface::addInternalJS()`; there is no build step.
