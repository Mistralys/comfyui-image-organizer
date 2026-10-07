# Constraints & Conventions

This document is the manifest's only binding one. Conventions below were verified against the code at the commit recorded in [curation-log.md](curation-log.md); where a section describes a known defect rather than a rule, it says so.

## Environment

- Target PHP 8.4 or newer. The code uses typed class constants, `new Foo()->method()` without parentheses and `array_any()`, and `composer.json` declares `"php": ">=8.4"`.
- Keep `config.php` out of version control and derive it from `config.dist.php`. Its constants live in the namespace `Mistralys\ComfyUIOrganizer\Config` and are imported with `use const`.
- Treat `APP_WEBSITE_API_URL` as optional and personal; code outside the "Send to website" feature must not depend on it.
- Bootstrap every entry point (web script, Composer script) through `prepend.php`. Do not construct `OrganizerApp` before it has run.

## Class discovery and autoloading

- Add a new page as a class in `src/ComfyUIOrganizer/Pages/` and a new AJAX action as a class in `src/ComfyUIOrganizer/Ajax/Methods/`. Do not register them by hand; `OrganizerApp` finds them by scanning those folders.
- Keep non-page classes out of `Pages/` and non-method classes out of `Ajax/Methods/`: the page scan instantiates every class it finds there and requires a `BasePage` instance.
- Run `composer dump-autoload` after adding, moving or renaming a class. Autoloading is classmap-based, and the `post-autoload-dump` hook (`Builder::postAutoload`) clears the class-discovery cache in `cache/`.
- Give every AJAX method a unique `METHOD_NAME`, and return it from `getID()`.
- Expose any AJAX method that the browser UI calls in the map built by `ImageCollection::injectJS()` and handle it in `js/ImageBrowser.js`; the JS side reads method names and payload keys from that map instead of hard-coding them.

## Image identity and the sidecar contract

- Identify an image by the MD5 hash of its PNG file. Request parameters carrying an image ID must be validated as MD5 (`setMD5()`), as `ImageCollection::getFromRequest()` and `image.php` do.
- Pair every PNG with a JSON sidecar of the same base name in the same folder. PNGs without a sidecar are skipped by the indexer.
- Expect each sidecar to hold a single top-level key (the date) whose value contains `checkpoint` and `custom_text` (`/` separators in the date are normalized to `-`). An image with neither a `checkpoint` nor a `modelName` property is skipped.
- Write sidecar `custom_text` in the project syntax: `-name:value` pairs, hyphen as separator, colon before the value, and backticks around any value that contains a hyphen or colon.
- Keep ComfyUI image file names in the form `<testNr>-<testName>-seed<seed>-<imageNr>.png` (hyphens or underscores as separators). The indexer derives test number, test name, seed and batch number from the name only when the sidecar properties lack them.
- Treat each immediate subfolder of the image folder as one image group. The "folder name" property is the name of the image's parent folder, and moving an image targets a sibling folder.
- Store LoRA weights in the image property bag under the LoRA's ID as key. `ImageProperties::getLoRASummary()` recognizes LoRAs only by that match.

## Index integrity

- Persist every change to indexed images through `ImageCollection::save()`. Mutating `ImageInfo` or `ImageProperties` marks the image as changed and sets its `modified` flag, and `save()` writes only changed entries.
- Preserve the `modified` flag semantics: the indexer never re-analyzes an image whose index entry has `modified = true`. For such entries it updates only the image path, sidecar path and folder name.
- Keep the favorite → gallery → website cascade: enabling "for website" enables "for gallery", and enabling "for gallery" enables "favorite". Disabling does not cascade.
- Treat a regular/upscaled pair as one unit. Images that have an upscaled replacement are hidden in the browser; moving, deleting or flagging the upscaled image also acts on its low-resolution versions (`findLowResVersions()`), and `registerLowResImage()` merges flags and label between the pair.
- Link upscaled images automatically only for pairs: `detectUpscaledImages()` ignores any settings-hash group that does not contain exactly two images.
- Refuse to overwrite on move: `moveToFolder()` throws `OrganizerException::ERROR_CANNOT_MOVE_FILE_EXISTS` when the target file exists, and copies before deleting so a failed save does not lose the image.
- Validate folder names for moves with `^[A-Za-z0-9_-]+$` (`MoveImageMethod`).
- Take a backup of `data/images.json` before each indexing run; `ImageIndexer` does this into `data/backup/`. Do not remove that step.
- Keep `data/file_index.json` in step with the index. `image.php` resolves every image and thumbnail request through it, and only `ImageIndexer::updateFileIndex()` writes it (run by every indexing pass and by `composer update-file-index`). Moves and deletes performed through the UI do not rewrite it.
- Serve a `.jpg` sibling instead of the `.png` when one exists (`image.php`); the JPG is the edited version. The `.psd` and `_websiteImage.png` siblings are located by replacing the `.png` extension of the image path.
- Name thumbnails `cache/<id>-<width>.jpg`; they are regenerated when the source file is newer.

## Workflows and LoRA files

- Match LoRAs to files by the official file name (the name given by the download site). Keep that name intact as a suffix when renaming local `.safetensors` files; the `PathFixer` matches the lowercase end of the local file name.
- Limit `PathFixer` rewrites to `LoraLoaderModelOnly` nodes, first widget value. Back up the workflow (`.json.bak.<timestamp>`) before saving a change, and restore `{}` for empty `flags`, `config` and `properties` after writing, as ComfyUI requires objects there.
- Read workflows from `<ComfyUI>/user/default/workflows`, not from this repository's `docs/workflows/`; the latter holds reference copies only.
- Keep LoRA IDs unique across all JSON files under `data/loras/`; they are loaded into one collection.

### Known gaps in the code (do not rely on)

- **LoRA data shape:** `data/loras/loras.json` has been restructured to nest per-model details (`source`, `file`, `triggerWords`) under a `models` object keyed by model name. `LoRA` still reads those values as flat keys (`file`, `source`, `model`, `triggerWords`), so for entries in the nested shape those getters return empty values, and `PathFixer` reports "no file specified" for them. A locally created file such as `nsfw.json` uses the flat shape. Resolve this before changing LoRA reading or path fixing.
- **Upscale detection by name:** in `ImageIndexer::analyzeImage()` the file-name check calls `str_contains()` with its arguments reversed, so it effectively never matches. Upscaled status comes from the `upscaleFactor` property or from a dimension of at least 2000 px.
- **No tests written yet:** PHPUnit is configured but `tests/` is empty, so nothing verifies the code automatically. Verify behavior manually through the UI and the Composer scripts until tests exist.

## Testing and static analysis

- Put PHPUnit tests in `tests/` and run them through the `composer test*` scripts; PHPStan analyses `tests/` as well as `src/`.
- Keep `composer analyze` (level 8, no baseline file) free of errors. Fix the cause rather than suppressing it: type arrays with PHPDoc value types, and do not add `@phpstan-ignore` comments.
- `phpstan.neon` lists `config.dist.php` and `prepend.php` under `scanFiles` so PHPStan knows the `Config\APP_*`, `CACHE_FOLDER` and `OUTPUT_FOLDER` constants; add any new global constant file there.
- Do not commit `phpstan-result.txt` or `.phpunit.cache/`; both are git-ignored tool output.

## UI and frontend

- Wrap user-visible strings in the localization functions (`t()`, `pt()`, `pts()`); both the `AppLocalize` and `AppUtils` namespaces are in use.
- Render pages as inline PHP templates inside `_render()`; use `OutputBuffering` when HTML must be returned as a string.
- Keep browser UI state (card size, filter toggles, active folder) in `$_SESSION`, and validate request values against fixed enums as `ImageBrowser` does.
- Make AJAX methods answer through `sendSuccess()` / `sendError()` (never plain output). `BaseImageMethod::sendSuccess()` re-reads the image to attach its flags, so an action that deletes the image must call `$this->methods->sendSuccess()` instead, as `DeleteImageMethod` does.
- Keep JavaScript as plain classes loaded through `UserInterface::addInternalJS()`; there is no build step.
