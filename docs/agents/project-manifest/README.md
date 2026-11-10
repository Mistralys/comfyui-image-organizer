# Project Manifest — ComfyUI Image Organizer

A navigational map of the codebase for AI agent sessions. It describes where things live and how they connect; the source remains the authority on behavior, and the docblocks and tests at each site hold the contracts and the reasons behind deliberate quirks.

**Project:** `mistralys/comfyui-image-organizer` — a PHP tool that organizes and manages a collection of ComfyUI-generated images. It reads the JSON sidecar files written by the "Save Image Extended" ComfyUI node, indexes the images together with the metadata built in the author's workflows (`docs/workflows/`), and serves a local web UI for browsing, flagging, moving and publishing them.

## Documents

| Document | Contents |
|---|---|
| [tech-stack.md](tech-stack.md) | Runtime, dependencies, framework base, architectural pattern, tooling, generated and git-ignored paths |
| [folder-overview.md](folder-overview.md) | What each top-level and `src/` folder is for, and which are generated or runtime data; not a file tree |
| [architecture.md](architecture.md) | Module map and who calls whom across the classes in `src/ComfyUIOrganizer/`; points to the source for signatures and per-method contracts |
| [data-flows.md](data-flows.md) | Main paths through the system: indexing, browsing, AJAX actions, workflow path fixing |
| [constraints.md](constraints.md) | Conventions and gotchas that bind changes to the code, including where contracts and rationale are written — the only imperative document |
| [curation-log.md](curation-log.md) | Standing decisions about how this manifest is arranged, plus the history of curation passes |

`curation-log.md` covers standing decisions as well as verification history; open it first when a section looks deliberately absent or oddly arranged.

There is no file-tree document: `folder-overview.md` explains what each folder is for instead of listing files. The generated and git-ignored paths are listed in [tech-stack.md](tech-stack.md).

## Where to Start

- Finding where something lives, or deciding where a new file belongs: `folder-overview.md`, then the module map in `architecture.md`.
- Changing how images are discovered or what is stored per image: `data-flows.md` (Indexing), then the indexing, index-data and metadata rows of the module map in `architecture.md`, then `ImageIndexer`, `ImageAnalyzer`, `ImageInfo` and `ImageProperties` in the source.
- Adding a page or an AJAX action: `constraints.md` (auto-registration rules), then the web-layer collaboration in `architecture.md`.
- Anything involving LoRA data or workflow path fixing: `constraints.md` (Workflows and LoRA files), then the LoRAs and Workflows rows of `architecture.md`.
- Parsing a sidecar into a format-independent result: `constraints.md` (Sidecar metadata module), then the Metadata module section of `architecture.md`, then the docblocks in `src/ComfyUIOrganizer/Metadata/`.
- Reading or writing a JSON file (image index, LoRA files, workflows): `constraints.md` (the DTO boundary rule under Index integrity), then `ImageIndex` / `ImageIndexEntry` and `Workflows\WorkflowData` in the source.

## Related Project Files

- `README.md` (project root) — user-facing description, including the sidecar text-property syntax and LoRA path-fixing requirements.
- `changelog.md` (project root) — release history.
- `config.dist.php` — documented template for the git-ignored `config.php`.
