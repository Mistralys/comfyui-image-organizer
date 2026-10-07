# Curation Log

Why this manifest looks the way it does, and when it was last verified.
Read freely — Standing Decisions explains the deliberate gaps and conventions.
Written by the Manifest Curator only; no other agent edits this file.

## Standing Decisions

| Date | Decision | Rationale |
|---|---|---|
| 2026-10-07 | The manifest has no `file-tree.md`. | The project is CTX-enabled (`context.yaml` at the root); the CTX Generator already produces the directory structure in `.context/project-folder-structure.md`, so a hand-maintained tree would duplicate it. The user approved removal. The list of generated and git-ignored paths, the one fact unique to the old tree, now lives in `tech-stack.md`. |

## History

### 2026-10-07 · Update · Curator v1.6.2

**Scope:** `tech-stack.md` and `constraints.md` only, reconciled against `composer.json`, `phpstan.neon`, `phpunit.xml`, `.gitignore` and `tests/`, following the Composer Curator's tooling changes. The other documents name no tooling and were not re-verified.
**Commit:** 1081830 (working tree uncommitted: the tooling files above are modified or untracked; no file under `src/`, `js/` or `css/` was touched).
**Changes:** `tech-stack.md` gained the `require-dev` packages, the new Composer scripts, PHPUnit and PHPStan configuration entries, and the new git-ignored paths (`phpstan-result.txt`, `.phpunit.cache/`); the "no tests / CI / static analysis" line was replaced. `constraints.md` replaced the "No automated tests" known gap with "No tests written yet", added a PHPStan-baseline known gap, and gained a short Testing and static analysis section.
**Notes:** `composer analyze` was run read-only and exits with errors against current `src/` (level 6, no baseline), which is why the PHPStan baseline is recorded as a known gap. The reverted-decision search and changed-code intersection covered no commits, since `HEAD` equals the previous pass's commit. Adjacent Document Check: the root `AGENTS.md` Failure Protocol row "Untested code path" still says "The project has no automated test suite" (owner: AGENTS.md Curator); the root `README.md` makes no claim about tests or tooling and is consistent.

### 2026-10-07 · Update · Curator v1.6.2

**Scope:** `README.md` index, `tech-stack.md`, and removal of `file-tree.md`, following the audit entry below. The remaining documents were already verified in that audit and left untouched.
**Commit:** 1081830 (working tree uncommitted; see the audit entry).
**Changes:** Removed `file-tree.md` and its index row; added a note to the index pointing to the CTX-generated folder structure; added the `context.yaml` / `.context/` entries to the index's related files; added the generated and git-ignored path list and the CTX tooling line to `tech-stack.md`. The CTX delegation ran after these edits.
**Notes:** The Create entry below states "No CTX configuration exists"; that was true then and was superseded by the CTX setup added afterwards. The root `AGENTS.md` still routes agents to `file-tree.md` (see the audit entry's adjacent findings).

### 2026-10-07 · Audit · Curator v1.6.2

**Scope:** Whole manifest, checked against `src/ComfyUIOrganizer/` signatures and constants, `composer.json` / `composer.lock`, `config.dist.php`, `prepend.php`, `.gitignore`, `data/loras/`, `js/` classes, `context.yaml` and `.context/`, plus the Adjacent Document Check.
**Commit:** 1081830 (working tree uncommitted: modified `README.md`, `composer.json`, `composer.lock`, `.idea/workspace.xml`; untracked `AGENTS.md`, `CLAUDE.md`, `context.yaml`, `.context/`, `data/loras/nsfw.json` and the manifest itself). No source file under `src/`, `js/` or `css/` differed from that commit.
**Changes:** none — audit only. Fixes followed in the Update entry above.
**Findings:** 0 high, 2 medium, 1 low in the manifest; 1 high in adjacent documents. Medium: `file-tree.md` present although `context.yaml` now makes the project CTX-enabled; `tech-stack.md` did not mention the CTX tooling and the generated/git-ignored paths lived only in `file-tree.md`. Low: the Create entry's "No CTX configuration exists" statement was outdated (history entries are never rewritten). High (adjacent): `AGENTS.md` lists `file-tree.md` and tells agents to check it first when finding files. `README.md` is consistent with the manifest (PHP 8.4, extensions, Composer scripts).
**Notes:** No signature, constant, data-flow or constraint claim was found wrong; the emphatic claims (only `updateFileIndex()` writes `file_index.json`, `LoRAReferenceRenderer` has no callers, reversed `str_contains()` arguments, flat vs nested LoRA shape) were re-verified. The reverted-decision search and changed-code intersection covered no commits, since `HEAD` equals the previous pass's commit; the working-tree diffs (`composer.json` gaining `"php": ">=8.4"`, README restructuring) agree with the manifest.

### 2026-10-07 · Create · Curator v1.6.2

**Scope:** Whole manifest — all source in `src/ComfyUIOrganizer/`, the web entry points, `prepend.php`, `config.dist.php`, `composer.json`/`composer.lock`, `js/`, `.gitignore`, and the data-folder layout. The vendor framework (`x4-core`) was read only for session handling and AJAX dispatch. `docs/templates/` and `docs/workflows/` were annotated from folder contents, not analysed in depth.
**Commit:** 1081830 (the working tree held uncommitted changes: a modified `.idea/workspace.xml` and an untracked `data/loras/nsfw.json`; neither affects documented source).
**Changes:** Created `README.md`, `tech-stack.md`, `file-tree.md`, `api-surface.md`, `data-flows.md`, `constraints.md` and this log. No CTX configuration exists, so `file-tree.md` is included and no CTX delegation ran.
**Notes:** The reverted-decision search found no revert commits in the repository history, so it produced no leads. `constraints.md` records one known code/data mismatch (nested `models` structure in `data/loras/loras.json` versus the flat keys read by `LoRA`) and one unfinished class (`LoRAReferenceRenderer`) as defects, not as conventions. The Adjacent Document Check does not belong to Create mode; the project root has no `AGENTS.md`, and the root `README.md` was not compared against the manifest.
