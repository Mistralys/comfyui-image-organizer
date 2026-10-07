# Synthesis: PHPStan Level 9 Data Shapes

**Plan:** `2026-10-07-phpstan-level-9-data-shapes` | **Work packages:** 10/10 COMPLETE | **Release:** 1.1.0

### Outcome Summary

PHPStan was raised from level 8 to level 9, removing all 21 findings where untyped values flowed into typed positions, with no baseline file and no ignore comments. Typed data objects now guard the image index, LoRA definitions and workflow files, session and request reads go through typed accessors, and unknown JSON keys survive a load and save. LoRA definitions in the nested `models` format are now readable, a stored-XSS finding in the path-fixer notices was fixed during the work, and the test suite grew from none to 56 tests; the indexer, `PathFixer::fixPaths()` and `ImageBrowser` remain largely untested.

### Metrics

| Metric | Result |
|---|---|
| Work packages | 10 of 10 COMPLETE, all stages PASS (no missing stages) |
| PHPStan | Level 8: 0 findings; level 9: 21 findings at baseline, 0 at the end (47 files) |
| PHPUnit (final) | 56 tests, 72,079 assertions, 0 failures; random-order run also passes |
| Security | 1 Medium finding (A03, WP-005), remediated and re-audited to PASS; 0 open |
| Blocking review issues | 0 across all code reviews |
| Real-data validation | `data/images.json` (3,208 entries, 590 with extra keys) round-trips with identical key order; 84 real LoRAs (12 multi-model) load |
| Version | 1.1.0 (minor) |

**Pipeline passes:** every WP passed implementation, QA, code-review and documentation. The five WPs with a security stage (WP-004, 005, 006, 008, 009) all ended in PASS. WP-010 also passed release-engineering.

**Failed metrics and blockers:** the only FAIL in the ledger is the first security-audit of WP-005 (stored XSS, A03, Medium). Details:
- **Cause:** a crafted workflow widget value reached the path-fixer result message unescaped.
- **Remediation:** `PathFixerMessage` now HTML-escapes its string `sprintf` arguments (`ENT_QUOTES|ENT_SUBSTITUTE`, UTF-8) while templates stay trusted. `PathFixerMessageTest` covers it.
- **Result:** the re-audit closed the finding.

### Strategic Recommendations

- **DTO boundary rule.** Typed objects at JSON file boundaries replace `mixed` reads. They own known fields and re-emit unknown keys, so user data is preserved. The rule is recorded in `constraints.md` (flagged a gold nugget by the Reviewer).
- **`WorkflowData` as a verbatim owner.** It exposes one intent-level mutation, `rewriteFirstWidgetString()`, instead of reference mutation of nested third-party JSON. This is the pattern for any future edit of ComfyUI workflow files.
- **Single access point for the session.** All `$_SESSION` access is confined to `SessionState` and wrong-typed values fall back to defaults. Keep it that way.
- **Escape at the message boundary.** Untrusted values go into `PathFixerMessage` as arguments and templates stay trusted HTML. Any new notice source must follow this, because the page joins messages unescaped.
- **Typed accessors over casts.** `getString()` and `getKey()` solved the level 9 findings without casts, `@var` overrides or ignores. This is the way to stay at level 9.

### Code Insights

**Developer**
- WP-002: the first tests in the repo set the `tests/` conventions (namespace `Tests`, final classes, private static fixtures, attribute data providers).
- WP-003: `getLoRASummary()` narrows keys with `is_string()`, so an all-digit LoRA ID (int array key) would be dropped; unlikely in practice.
- WP-004: `getModelNames()` skips empty names, so LoRAs without a model name no longer add `''` to `LoRABucket::getModels()`.
- WP-006: the `resolve*` methods in `ImageBrowser` write to the session on every render (write-on-read, repeated three times).
- WP-008: `loadIndex()` copies the backup before loading, so a backup exists even when an invalid entry makes the load throw.
- WP-009: `ImageCollection::save()` rewrites the whole index on every call, even when nothing changed (pre-existing).

**QA**
- WP-002: `ImageIndex::fromArray()` keys entries by the entry's own id (the outer key is ignored), and `toArray()` uses `array_merge`, which renumbers integer-like unknown keys. Neither is pinned by a test.
- WP-007: no test exercises `registerItems()`, so a runtime break stayed invisible to the suite until WP-009 landed.
- WP-008: only `resolveCheckpoint()` is unit-tested. The modified-path messages, the `hash_file()` failure branch and the merge in `analyzeImage()` need the app bootstrap and have no test.
- WP-009: the first save after deploy normalizes 958 entries without `label` and 889 without `modified`, producing a large one-time diff in `images.json`.
- WP-005: the `json_encode` round trip turns `1.0` into `1` (pre-existing).

**Security Auditor**
- No open findings. WP-004 recorded three Low/Info observations: an href scheme allow-list, path-index key use, and DTO boundary posture.

**Reviewer**
- WP-003: loose `in_array()` against LoRA IDs could be made strict (non-behavioral).
- WP-010: the `@var callable` property doc in `ImageProperties` could become a typed `Closure` property (pre-existing).
- WP-010: `.phpunit.result.cache` was untracked and not git-ignored. Release Engineering added it to `.gitignore`.

**Release Engineer**
- Minor bump to 1.1.0: the nested LoRA format is a new capability and nothing public was removed or changed in data format.

**Documentation**
- The manifest (`api-surface.md`, `data-flows.md`, `constraints.md`, `tech-stack.md`, README), `AGENTS.md` and the changelog were brought in line, and stale "no tests" and "level 8" statements were removed.

### Deferred & Follow-Up Items

**Deferred (intentionally postponed)**

| Source | Agent | Item | Priority / rationale |
|---|---|---|---|
| WP-010 | Release Engineer | Regenerate `.context/` with the CTX generator and tag/publish 1.1.0 | User action; commit the untracked new files (`src/ImageIndex*.php`, `SessionState.php`, `LoRAModel.php`, `WorkflowData.php`, `tests/`) first |
| Project | Planner | Structured-sidecar plan edits `ImageProperties.php` and replaces the interim sidecar read in `ImageIndexer` (WP-008) | Merge friction only |
| WP-008, 005, 009, 006 | QA | Tests for the indexer (modified path, hash failure, merge), `PathFixer::fixPaths()`, `ImageCollection` and `ImageBrowser` resolve methods | Medium for the indexer; needs bootstrap or an injectable temp `JSONFile`; recorded as a gap in `constraints.md` |
| WP-002 | QA | Tests pinning lenient malformed-field behaviour, re-keying by entry id, and `array_merge` renumbering | Low |
| WP-004 | QA | Tests for `LoRABucket::getModels()/getByModel()`, non-array `models` or model entries, and `LoRAsCollection` file loading (flat-shape `nsfw.json`) | Low |
| WP-003 | Reviewer | Make `in_array()` strict in `getLoRASummary()` | Low |
| WP-010 | Developer / Reviewer | Replace the `@var callable` property doc in `ImageProperties` with a typed `Closure` property | Low |
| WP-009 | Developer | Skip the index write in `ImageCollection::save()` when nothing changed | Low; I/O reduction |
| WP-006 | Developer | Remove write-on-read from the `ImageBrowser::resolve*` methods | Low; code smell |
| WP-005 | QA | `PathFixerMessageTest` cases for invalid UTF-8 and a literal `%` in an argument | Low; manual probe passed |
| Project | Documentation | WP-003 documentation pipeline declared no `artifacts.files_modified` | Low; traceability warning |

**Out of scope (known gaps kept as-is)**

| Source | Item | Note |
|---|---|---|
| WP-004 | The plural `files` key (e.g. `realPussyPeach`) is not read, so its files are never used for path fixing | Pre-existing data gap; in `constraints.md` |
| WP-004 | `PathFixer::buildFileIndex()` silently overwrites LoRAs that share a file name | In `constraints.md` |
| WP-005 | Empty-object restoration in `fixPaths()` is a whole-file `str_replace` and could rewrite a matching string value | Pre-existing; in `constraints.md` |
| WP-008 | `str_contains('upscale', $baseName)` has reversed arguments in `ImageIndexer::analyzeImage()`, and `$testName` may be undefined when there is no `seed` part | Pre-existing; the first is in `constraints.md` |
| WP-006 | `resolveActiveCardSize()` returns a stored session size without re-validating it against the card-size enum | Unchanged behaviour |

### Next Steps

1. **Release housekeeping:** commit the new files, regenerate `.context/`, and tag 1.1.0.
2. **Test the untested parts:** add tests for `ImageIndexer`, `PathFixer::fixPaths()`, `ImageCollection` and `ImageBrowser` using an injectable temp `JSONFile` or a bootstrap-backed fixture. The merge and sync decisions in the indexer matter most, because they carry the data-preservation guarantee.
3. **Fix the known defects:** the plural `files` key, file-name collisions in `buildFileIndex()`, the reversed `str_contains`, and the whole-file `{}` restoration.
4. **Structured-sidecar plan:** reconcile with the interim sidecar read in `ImageIndexer` and the `ImageProperties.php` edits.
5. **Small cleanups:** typed `Closure` property, strict `in_array()`, and skipping no-op index writes.
