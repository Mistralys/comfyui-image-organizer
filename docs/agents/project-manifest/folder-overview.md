# Folder Overview

What each folder is for. This is a map of roles, not a file tree: it names no individual source files beyond the entry points, and the classes inside each `src/` folder are described in [architecture.md](architecture.md). Runtime, dependencies and tooling are in [tech-stack.md](tech-stack.md).

The **Status** column marks how a folder relates to Git and to the running application:

- **source** — hand-written and tracked.
- **runtime data** — written by the application or by the user's own workflow while it runs.
- **generated** — rebuilt by a command and git-ignored; safe to delete and regenerate.

## Project root

The root holds the web and CLI entry points and the configuration files; everything else is a folder below.

| Entry point | Role |
|---|---|
| `index.php` | Web entry point; runs the UI. |
| `image.php` | Web entry point; streams an image or thumbnail. |
| `prepend.php` | Bootstrap shared by the web entry points and the Composer CLI tasks; loads the autoloader and `config.php` and defines the cache and output folder constants. |
| `config.dist.php` | Documented template for the git-ignored, per-machine `config.php`. |

## Top-level folders

| Folder | Status | Role |
|---|---|---|
| `src/` | source | All PHP application code, in one namespaced module tree under `ComfyUIOrganizer/`; see below. |
| `js/` | source | Plain JavaScript classes for the browser UI, loaded directly with no bundler. |
| `css/` | source | The application stylesheet; the Bootstrap and Font Awesome styles come from `vendor/`. |
| `tests/` | source | PHPUnit tests. The layout mirrors `src/ComfyUIOrganizer/`; `fixtures/` holds the sample sidecar files the metadata tests parse. |
| `docs/` | source | Documentation and reference material; see below. |
| `data/` | runtime data | Persistence for the application; see below. |
| `cache/` | generated | Thumbnails created on demand for the image browser. Git-ignored. |
| `output-images/` | runtime data | Target of the "copy to output" action, which places a selected image here under its output file name. The PNG files are git-ignored. |
| `vendor/` | generated | Composer dependencies, including the X4 UI framework and its Bootstrap assets. Git-ignored; never edited. |
| `.github/` | source | Agent skills (`skills/`), among them the FLUX prompt-generation skill. Independent of the application code. |
| `.idea/` | IDE settings | IDE project settings; not part of the application. |

Further root-level items — `.git/`, `.gitignore`, `.gitattributes` and the PHPUnit result cache — are repository and tooling housekeeping. The git-ignored paths in full are listed in [tech-stack.md](tech-stack.md).

## `data/`

| Path | Status | Role |
|---|---|---|
| `data/images.json` | runtime data | The image index: one record per image, written by indexing and by UI actions. Git-ignored. |
| `data/file_index.json` | runtime data | Lookup file written by indexing, through which `image.php` resolves every request. Git-ignored. |
| `data/backup/` | runtime data | Timestamped copies of the image index, taken before an indexing run rewrites it. Git-ignored. |
| `data/loras/` | source / runtime data | Hand-written LoRA reference files, one JSON file per set. Read at runtime whether tracked or created locally. |

How these files are written is in [data-flows.md](data-flows.md).

## `docs/`

| Folder | Role |
|---|---|
| `docs/agents/` | Agent-facing material: this manifest (`project-manifest/`) and implementation plans (`plans/`). Ledger working files inside it are git-ignored. |
| `docs/workflows/` | Reference copies of the author's ComfyUI workflows that write the metadata this tool reads. The application reads workflows from the ComfyUI installation, not from here. |
| `docs/templates/` | Reusable ComfyUI workflow modules, each with a short description. |
| `docs/prompt-examples/` | Example image prompts, grouped by model family. |

`docs/flux-prompting-guide.md` sits directly in `docs/` and is independent of the application code.

## `src/ComfyUIOrganizer/`

The root of the module tree holds the application core and the indexing and image-data classes. Each sub-folder is a sub-namespace of the same name, and its role is the module row in [architecture.md](architecture.md).

| Folder | Role |
|---|---|
| `Ajax/` | Base class for browser actions on one image. |
| `Ajax/Methods/` | One class per AJAX action; discovered by a folder scan, not registered by hand. |
| `LoRAs/` | LoRA reference data: the classes that load and group the files in `data/loras/`. |
| `Metadata/` | Sidecar handling: locating a sidecar and parsing it into a format-independent result. |
| `Metadata/Formats/` | One class per supported sidecar format; discovered by a folder scan. |
| `Pages/` | One class per UI page; discovered by a folder scan. |
| `Workflows/` | ComfyUI workflow files and the path fixer that repairs LoRA paths in them. |

The folder scans make the folder contents part of the application's behavior; the registration rules are in [constraints.md](constraints.md).
