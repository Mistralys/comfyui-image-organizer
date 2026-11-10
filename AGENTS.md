# AGENTS.md — ComfyUI Image Organizer

> **This file is the operating manual for AI agents entering this codebase.**
> Read it in full before making any changes.

---

## 1. Project Manifest — Start Here!

The project manifest lives in `docs/agents/project-manifest/`. It is the canonical description of the codebase; this file routes to it and states what to do about it.

| Document | Path | Purpose |
|----------|------|---------|
| Manifest Index | `docs/agents/project-manifest/README.md` | Entry point: project summary, document list, where to start for common tasks |
| Tech Stack | `docs/agents/project-manifest/tech-stack.md` | Runtime, dependencies, framework base, architectural pattern, tooling, generated and git-ignored paths, CTX tooling |
| Architecture | `docs/agents/project-manifest/architecture.md` | Module map of `src/ComfyUIOrganizer/` and who calls whom; signatures and per-method contracts are in the source |
| Data Flows | `docs/agents/project-manifest/data-flows.md` | Main paths through the system: indexing, browsing, AJAX actions, workflow path fixing |
| Constraints | `docs/agents/project-manifest/constraints.md` | Binding conventions and gotchas, including known defects in the code and where contracts and rationale are written |
| Curation Log | `docs/agents/project-manifest/curation-log.md` | Standing decisions about how the manifest is arranged, and the history of curation passes |
| Folder Structure (CTX-generated) | `.context/project-folder-structure.md` | Directory structure; generated from `context.yaml`, not part of the manifest and never edited by hand |
| README | `README.md` | User-facing description, including the sidecar text-property syntax |
| Changelog | `changelog.md` | Release history |
| FLUX Prompting Guide | `docs/flux-prompting-guide.md` | Reference for writing prompts for FLUX.1 Dev in local ComfyUI; not part of the manifest and independent of the application code |
| FLUX Prompt Generation Skill | `.github/skills/flux-prompt-generation/SKILL.md` | Agent skill that converts an image prompt (SDXL-style keywords, negative prompts) into a FLUX-compatible prompt; supplements the FLUX Prompting Guide |

### Ingestion Path

1. **Read** `constraints.md` — the rules and known defects that bind any change.
2. **Read** `tech-stack.md` — runtime, dependencies, and the page / AJAX pattern.
3. **Read** `architecture.md` and `data-flows.md` for the area you are changing.
4. **Then** read the source files for it; a class's or method's contract is the docblock at the site.

---

## 2. Manifest Maintenance Rules

When a change is made to the codebase, the documents below must be updated in the same change.

| Change Made | Documents to Update |
|---|---|
| Class, method, or constant added, removed, or its contract changed | The docblock or one-line comment at the site, in the same diff; the manifest lists no signatures |
| Class or module added, removed, or its collaborators changed | `architecture.md` |
| File or folder added, moved, or renamed | Regenerate the CTX output in `.context/`; `tech-stack.md` if the change affects generated or git-ignored paths |
| Indexing, browsing, AJAX, or workflow-fixing behavior changed | `data-flows.md` |
| Dependency, runtime requirement, Composer script, or tooling changed | `tech-stack.md`, `composer.json` |
| Convention introduced or changed, or a known defect fixed or discovered | `constraints.md` |
| Sidecar syntax, configuration, or other user-facing behavior changed | `README.md`, `changelog.md`; `config.dist.php` for configuration |

`curation-log.md` is written by the Manifest Curator only. Never edit it directly.

---

## 3. Efficiency Rules — Search Smart

- **Finding files?** Check `.context/project-folder-structure.md` FIRST.
- **Looking for which class does what, or who calls whom?** Check `architecture.md` FIRST.
- **Looking for a class or method signature or contract?** Read the docblock in the source; the manifest does not carry it.
- **Understanding how a feature flows through the system?** Check `data-flows.md` FIRST.
- **Unsure whether something is allowed, or why it is done a certain way?** Check `constraints.md` FIRST.
- **A manifest section looks deliberately absent or oddly arranged?** Check `curation-log.md` FIRST.
- **Asked to convert, rewrite, or generate an image prompt for FLUX?** Use the FLUX Prompt Generation Skill, and consult `docs/flux-prompting-guide.md` for detail the skill does not carry.
- **Only then** read source files.

---

## 4. Failure Protocol & Decision Matrix

| Scenario | Action | Priority |
|---|---|---|
| Ambiguous requirement | Consult `constraints.md` for intent; use the most restrictive interpretation | MUST |
| Manifest/code conflict over a convention or cross-class structure | Trust the manifest, then flag the code for fix and report the conflict | MUST |
| Docblock/code conflict over one class's or method's contract | Check the tests that pin it, fix whichever of code and docblock is wrong in the same change, and report the conflict | MUST |
| Missing documentation | Flag the gap; do not invent facts | MUST |
| Change touches something `constraints.md` lists as a known defect, or a `// TODO:` marks one at the site | Read the entry first, do not build on the defective behavior, and report it | MUST |
| Change adds a page or AJAX action | Follow the registration rules in `constraints.md`; do not wire it in by hand | MUST |
| Change would edit third-party code under `vendor/` | Do not. Work around it in `src/` or raise it with the user | MUST |
| Untested code path | `constraints.md` lists the parts without automated tests. Add a PHPUnit test where practical, otherwise proceed with caution and add a manual test recommendation | SHOULD |
| Manifest entry found to be wrong while working | Fix a small error in place; otherwise report it rather than working around it | SHOULD |

---

## 5. Project Stats

| Property | Value |
|---|---|
| **Language** | PHP (strict types), effective minimum 8.4 |
| **Architecture** | Server-rendered local web UI on the X4 application framework; page classes + AJAX method classes discovered by folder scan; collection/record model over JSON-file storage |
| **Frontend** | Plain JavaScript classes, no bundler |
| **Package Manager** | Composer (classmap autoloading) |
| **Build Tool** | None; Composer scripts only |
| **Test Framework** | PHPUnit 12 (`tests/`, `phpunit.xml`); static analysis with PHPStan level 9 (`phpstan.neon`) |
| **License** | MIT |

### Composer Scripts

| Script | Purpose |
|---|---|
| `composer test` | Run the full PHPUnit suite |
| `composer test-file -- <path>` | Run a single test file |
| `composer test-suite -- <name>` | Run a named test suite |
| `composer test-filter -- <pattern>` | Run tests matching a name filter |
| `composer test-group -- <group>` | Run tests in a group |
| `composer analyze` | Run PHPStan |
| `composer analyze-save` | Run PHPStan, writing output to `phpstan-result.txt` |
| `composer analyze-clear` | Clear the PHPStan result cache |
| `composer verify-metadata` | Read-only census of every image's sidecar (format counts, no-sidecar / unrecognized / no-model-name / failure lists); exits 1 only on parse failures |
