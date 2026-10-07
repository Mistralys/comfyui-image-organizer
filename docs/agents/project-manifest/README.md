# Project Manifest — ComfyUI Image Organizer

A navigational map of the codebase for AI agent sessions. It describes where things live and how they connect; the source remains the authority on behavior.

**Project:** `mistralys/comfyui-image-organizer` — a PHP tool that organizes and manages a collection of ComfyUI-generated images. It reads the JSON sidecar files written by the "Save Image Extended" ComfyUI node, indexes the images together with the metadata built in the author's workflows (`docs/workflows/`), and serves a local web UI for browsing, flagging, moving and publishing them.

## Documents

| Document | Contents |
|---|---|
| [tech-stack.md](tech-stack.md) | Runtime, dependencies, framework base, architectural pattern, tooling |
| [api-surface.md](api-surface.md) | Public signatures of every class in `src/ComfyUIOrganizer/`, grouped by module |
| [data-flows.md](data-flows.md) | Main paths through the system: indexing, browsing, AJAX actions, workflow path fixing |
| [constraints.md](constraints.md) | Conventions and gotchas that bind changes to the code — the only imperative document |
| [curation-log.md](curation-log.md) | Standing decisions about how this manifest is arranged, plus the history of curation passes |

`curation-log.md` covers standing decisions as well as verification history; open it first when a section looks deliberately absent or oddly arranged.

There is no file-tree document: the project is CTX-enabled, and the generated `.context/project-folder-structure.md` holds the directory structure. The generated and git-ignored paths are listed in [tech-stack.md](tech-stack.md).

## Where to Start

- Changing how images are discovered or what is stored per image: `data-flows.md` (Indexing), then `ImageIndexer`, `ImageInfo` and `ImageProperties` in `api-surface.md`.
- Adding a page or an AJAX action: `constraints.md` (auto-registration rules), then the Pages and Ajax sections of `api-surface.md`.
- Anything involving LoRA data or workflow path fixing: `constraints.md` (LoRA data shape), then the LoRAs and Workflows sections of `api-surface.md`.

## Related Project Files

- `README.md` (project root) — user-facing description, including the sidecar text-property syntax and LoRA path-fixing requirements.
- `changelog.md` (project root) — release history.
- `config.dist.php` — documented template for the git-ignored `config.php`.
- `context.yaml` and `.context/` — CTX Generator configuration and its generated context documents (folder structure, manifest bundle, per-module architecture).
