# Curation Log

Why this manifest looks the way it does, and when it was last verified.
Read freely — Standing Decisions explains the deliberate gaps and conventions.
Written by the Manifest Curator only; no other agent edits this file.

## Standing Decisions

| Date | Decision | Rationale |
|---|---|---|
| 2026-10-07 | The manifest has no `file-tree.md`. | A hand-maintained file tree goes stale on every added or renamed file. The user approved its removal on 2026-10-07 (then because the CTX Generator produced the folder structure); the CTX tooling has since been removed (see 2026-11-10 below) and the decision to keep the file-tree dropped stands. The list of generated and git-ignored paths, the one fact unique to the old tree, lives in `tech-stack.md`. |
| 2026-11-10 | The CTX Generator tooling is removed from the project (`context.yaml` and `.context/` are deleted and will not return), and the manifest carries no CTX references. `folder-overview.md` replaces the CTX-generated folder structure: it explains what each folder is for and marks generated, git-ignored and runtime-data folders, without listing files or counts. | The user removed the tooling. A role-per-folder overview stays correct as files come and go, where a tree does not, and root `AGENTS.md` routes "where does something live" questions to it. |
| 2026-11-10 | The manifest has no signature listing: `api-surface.md` was retired and replaced by `architecture.md` (module map plus who calls whom). Contracts, rationale and "watch out" notes of a single class or method live as docblocks or comments at the code site, as tests where practical, and as `// TODO:` comments for defects; `constraints.md` carries a section stating this convention. | Signatures are derivable from the source and go stale fastest. A contract kept in a separate file is not seen, or updated, by an agent editing the method, whereas a docblock is read and changed in the same diff. Cross-class flow has no single home in the code and changes slowly, so it stays a document. The user approved the file name `architecture.md` and the convention. |

## History

### 2026-11-10 · Update · Curator v1.10.1

**Scope:** CTX removal and the new `folder-overview.md`: the `README.md` index, `tech-stack.md` (generated-paths line, tooling list), the `Metadata/` entry of `constraints.md`, and a search of the whole project outside `vendor/` for `context.yaml`, `.context` and CTX. The folder overview was checked against the real directory tree, `.gitignore`, `prepend.php` and the folder-scan sites; the rest of the manifest was not re-verified.
**Commit:** db7fc86 (working tree uncommitted: `AGENTS.md` modified, `context.yaml` deleted, untracked `data/loras/nsfw.json` and `docs/prompt-examples/`). The only commit since the previous entry's 2b666ba is db7fc86, which holds the docblock and test additions that entry already covered.
**Changes:** Created `folder-overview.md` and indexed it in `README.md`; removed the CTX note, related-files entry and tooling bullet; replaced the "no file-tree because CTX" note with a pointer to `folder-overview.md`; added a "finding where something lives" start point; dropped the `context.yaml` sentence from `constraints.md`; reworded the 2026-10-07 Standing Decision and added the CTX-removal decision.
**Notes:** The reverted-decision search found no revert commits. History entries below still mention CTX; they record what was true at the time and are not rewritten. `.context/` was never in the generated-paths list of `tech-stack.md`, so that list needed no removal.

### 2026-11-10 · Update · Curator v1.10.1

**Scope:** Two follow-ups to the entry below: the test-coverage sentence of `tech-stack.md` and the two overlapping "no automated test" entries of `constraints.md`. Nothing else was re-verified.
**Commit:** 2b666ba (working tree uncommitted, as in the entry below).
**Changes:** `tech-stack.md` no longer enumerates the tested areas; it says the tests mirror `src/` and points to the known gaps. `constraints.md` merged the `PathFixer::fixPaths()` entry into "Parts without automated tests", which now also notes that `ImageIndexer::filterNewFolders()` has its own test (checked in `tests/ImageIndexerTest.php`).
**Notes:** The reverted-decision search and changed-code intersection covered no new commits, since `HEAD` equals the previous entry's commit.

### 2026-11-10 · Update · Curator v1.10.1

**Scope:** The restructure only: retirement of `api-surface.md`, the new `architecture.md`, the `README.md` index, the link line of `data-flows.md`, and every `constraints.md` entry that restated a single-site contract or was contradicted by the code. `tech-stack.md`, the bodies of `data-flows.md` and the remaining `constraints.md` entries were not re-verified.
**Commit:** 2b666ba (working tree uncommitted: comment, docblock and test additions in `src/` and `tests/` from the migration, plus the earlier untracked `.context/`, `data/loras/nsfw.json` and `docs/prompt-examples/`).
**Changes:** Removed `api-surface.md`; created `architecture.md`; added the "Contracts and rationale live with the code" section to `constraints.md` and shrank the Metadata and Workflows sections to cross-cutting rules; extended and corrected the known-gap list; dropped a current-state remark from `data-flows.md`.
**Notes:** The Developer agent added the docblocks, TODOs and tests and checked each migrated note against the code, which found manifest claims that were wrong and are now corrected: `ImageCollection::save()` was said to write only changed entries (it re-derives changed entries but writes the whole index); the upscaled-pair rule named "flagging" as acting on low-resolution versions (it does not); `FolderInfo` was said to throw for dotted folder names and `getSearchWords()` to be at risk (it throws only when the folder does not exist); the plural `files` LoRA gap (no entry in `data/loras/` uses it); the `"flags":[]` string-value case of the path fixer (impossible, since JSON escapes quotes; the real defect is a replacement at any depth); the `Ajax/Methods/` scan (it filters by `AjaxMethodInterface` and ignores other classes); and the absolute claims that formats read only through the readers and use `isObjectSection()` everywhere (neither holds). One PHPStan error that already existed (`ImageInfo::getWebsiteBadge()`, `if.alwaysTrue`) was recorded as a known gap because it contradicts the "free of errors" rule. Known-gap entries stay in `constraints.md` in addition to their TODOs at the site, because root `AGENTS.md` routes agents to them; the user has not yet ruled on that. The reverted-decision search over 1081830..HEAD found no revert commits. The changed-code intersection was not run over that range: it spans many commits, the Metadata-module work among them, that landed without a log entry, so this pass does not vouch for the manifest as a whole. The `FolderInfo` comments in `ImageAnalyzer` and `StructuredSidecarFormat` are still imprecise (see above) and were left to the Developer. A full Audit pass is the next step. Adjacent Document Check: root `AGENTS.md` still routes agents to `api-surface.md` in four places and its "Manifest/code conflict" rule needs a second look (owner: AGENTS.md Curator); root `README.md` makes no manifest claim and is consistent.

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
