# Tech Stack & Patterns

## Runtime

- **Language:** PHP, strict types in every source file. The code uses typed class constants (`const string`), `new Foo()->method()` without wrapping parentheses and `array_any()`, which places the effective minimum at PHP 8.4 (declared in `composer.json` as `"php": ">=8.4"`).
- **Extensions:** `ext-mbstring`, `ext-curl` (declared in `composer.json`). Thumbnail creation and image-size reading go through `ImageHelper` from the transitive `application-utils-image` package.
- **Server:** a local webserver serving the project folder (the URL is set by `APP_WEBROOT_URL` in `config.php`); `index.php` and `image.php` are the web entry points. No database.
- **Persistence:** JSON files under `data/`, plus generated JPEG thumbnails under `cache/`.
- **Generated and git-ignored paths:** `vendor/`, `cache/`, `config.php`, `data/images.json`, `data/file_index.json`, `data/backup/`, the PNG files in `output-images/`, `phpstan-result.txt` (written by `composer analyze-save`) `.phpunit.cache/` and `.phpunit.result.cache`. Files under `data/loras/` are read at runtime whether tracked or created locally.
- **Frontend:** server-rendered HTML with inline PHP templates; plain JavaScript classes (no bundler) loaded from `js/`; Bootstrap 5 and Font Awesome come from `vendor/` via the UI framework.

## Dependencies

Declared in `composer.json`:

| Package | Role |
|---|---|
| `mistralys/application-utils-core` | File/folder helpers (`FileInfo`, `FolderInfo`, `JSONFile`), `ConvertHelper`, `ClassHelper`, `Request`, `ArrayDataCollection`, `Microtime`, collections base classes |
| `mistralys/application-utils-collections` | `BaseStringPrimaryCollection` and related collection base classes |
| `mistralys/x4-core` | Application/UI framework: `X4Application`, `UserInterface`, `BasePage`, `BaseAjaxMethod`, `AjaxMethods`, `DataGrid`, `Console` |

Transitive packages of note (resolved in `composer.lock`): `mistralys/application-utils-image` (`ImageHelper`), `mistralys/application-localization` (`t()` / `pt()` / `pts()`), `mistralys/html_quickform2`, `twbs/bootstrap`, `fortawesome/font-awesome`, `components/jquery`.

Development dependencies (`require-dev`):

| Package | Role |
|---|---|
| `phpunit/phpunit` | Test runner (`^12.0`) |
| `phpstan/phpstan` | Static analysis (`^2.1`) |
| `phpstan/phpstan-phpunit` | PHPStan rules and extension for PHPUnit tests (`^2.0`) |
| `roave/security-advisories` | Conflict-only package (`dev-latest`) that blocks installing dependency versions with known security advisories |

The Composer package `type` is `tool`, `minimum-stability` is `dev` with `prefer-stable`, and the license is MIT.

## Architecture

- **Application skeleton:** `OrganizerApp` extends the framework's `X4Application` and is used as a process-wide singleton via `OrganizerApp::create()`. The framework constructor starts a PHP session when running under a web server (the CLI gets an in-memory array), which the browser page uses for UI state.
- **Page + AJAX pattern:** each UI page is a class in `Pages/`; each AJAX action is a class in `Ajax/Methods/`. Both sets are discovered by scanning their folders (`ClassHelper::findClassesInRepository`), not by explicit registration. AJAX calls are GET requests against the current page URL with an `ajax=<MethodName>` parameter.
- **Collection / record pattern:** `ImageCollection`, `LoRAsCollection` and `WorkflowsCollection` extend `BaseStringPrimaryCollection`; their records (`ImageInfo`, `LoRA`, `WorkflowFile`) implement `StringPrimaryRecordInterface`.
- **JSON-file storage:** the index (`data/images.json`) is loaded wholesale into `ImageCollection`, mutated through `ImageInfo`/`ImageProperties`, and rewritten by `ImageCollection::save()`.
- **CLI build tasks:** `Builder` exposes static methods wired to Composer scripts; they bootstrap the app through `prepend.php` and delegate to `ImageIndexer`.

## Tooling

- **Package manager / task runner:** Composer. Application scripts: `build`, `index`, `detect-upscaled`, `clean-folders`, `update-file-index`, and the `post-autoload-dump` hook (clears the class-discovery cache). Tooling scripts: `test`, `test-file`, `test-suite`, `test-filter`, `test-group` (PHPUnit through `php vendor/bin/phpunit`) and `analyze`, `analyze-save`, `analyze-clear` (PHPStan through `php vendor/bin/phpstan`; `analyze-save` writes its output to `phpstan-result.txt`).
- **Autoloading:** classmap over `src/`, which is why new classes require a `composer dump-autoload`.
- **Testing:** PHPUnit 12, configured in `phpunit.xml` (bootstrap `vendor/autoload.php`, one test suite named `default` over `tests/`, coverage source `src/`). `tests/` holds tests for parts of the code (the image index, `ImageInfo`, LoRAs, session state and the workflow classes `WorkflowData` and `PathFixerMessage`).
- **Static analysis:** PHPStan at level 9, configured in `phpstan.neon` (includes the `phpstan-phpunit` extension; analyses `src` and `tests`; scans `config.dist.php` and `prepend.php` for constants).
- **CI:** none is configured in the repository.
- **Context documentation:** the CTX Generator, configured by `context.yaml` at the project root, writes bundled context documents to `.context/` (folder structure, README/AGENTS/changelog, the manifest, Composer configuration, entry points, and per-module architecture for the core, Ajax, LoRAs, Pages, Workflows and frontend code).
- **IDE:** a `.idea/` folder is present in the working tree; it is not part of the application.

## Integrations

- **ComfyUI installation (filesystem):** the app reads the ComfyUI folder tree — the output folder holding images and sidecars, `models/loras`, and `user/default/workflows` — through paths set in `config.php`.
- **Personal website gallery (HTTP, optional):** the "Send to website" feature POSTs JSON via cURL to an API URL set in `config.php`.
