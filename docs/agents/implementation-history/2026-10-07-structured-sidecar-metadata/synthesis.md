# Synthesis — Structured Sidecar Metadata

**Plan:** `2026-10-07-structured-sidecar-metadata`
**Status:** COMPLETE — 11 of 11 work packages passed every active pipeline stage (implementation, QA, security-audit where active, code-review, release-engineering where active, documentation). No FAIL pipelines and no rework cycles.

### Outcome Summary

The organizer now indexes both the legacy date-keyed sidecar and the new structured `-meta.json` sidecar through a new `Metadata` module. The module has a format interface, two format classes (`LegacySidecarFormat`, `StructuredSidecarFormat`), a folder-scan `MetadataRegistry` and an immutable `ImageMetadata`. The per-image indexer logic was extracted into a testable `ImageAnalyzer`. Real-data verification showed 0 bag and 0 checkpoint mismatches against the pre-change oracle of 3,445 legacy sidecars, and the release was versioned 1.2.0. The reversed `str_contains('upscale', $baseName)` defect was kept on purpose, to leave legacy indexing byte-identical.

### Metrics

| Measure | Result |
|---|---|
| Work packages | 11 / 11 COMPLETE, 0 missing stages |
| Test suite | 56 tests at baseline, 153 tests / 76,289 assertions at the end (ImageIndexerTest was deleted in WP-010; peak was 156), no warnings |
| Static analysis | PHPStan level 9, 0 errors, no baseline, no ignore comments (71 files) |
| Security audits | 5 audits (WP-003, 004, 005, 007, 010), all PASS, no Critical, High or Medium findings |
| Dependency audit | `composer audit`: no advisories, no new dependencies |
| Legacy regression oracle | 3,445 / 3,445 sidecars detected; 5 workflow exports rejected; 0 bag mismatches; 0 checkpoint mismatches; 235 `NO_MODEL_NAME` equal to the 235 empty-checkpoint oracle entries |
| `composer verify-metadata` (real folder) | 4,112 PNGs: legacy 3,210, structured 113, no sidecar 554, unrecognized 0, no model name 235, parse failures 0 (QA re-run after one new image: 4,113 PNGs, structured 114) |
| Index comparison | 886 compared entries all equal, 0 mismatches (2,322 of 3,208 are modified entries that are never re-analyzed) |
| Data safety | SHA256 of `data/images.json`, `data/file_index.json` and the 3 backups identical before and after verification |
| Version | 1.2.0 (`OrganizerApp::getVersion()` raised from 1.0.0) |

**Failed metrics, blockers, security concerns:** none blocking. Open concerns are listed under Deferred & Follow-Up Items (output-escaping sinks and the registry hardening gaps).

### Strategic Recommendations

- **Characterise before refactoring.** WP-001 captured real-sidecar fixtures, expected bags and a 3,450-candidate population oracle from the unmodified code before anything moved. WP-011 then proved byte-identical legacy behaviour against that oracle. Reuse this pattern for any future indexer change.
- **Keep the oracle until the next legacy change.** It lives only in `sys_get_temp_dir()/sidecar-population-oracle.json` and cannot be regenerated once `parseText()` has moved. Refresh it deliberately before touching `LegacySidecarFormat::parseText()`.
- **Single-pass resolution.** `MetadataRegistry::resolve()` uses a path-keyed read cache and reports unrecognized candidates without a second I/O pass (reviewer "gold nugget").
- **Code-uniqueness guard.** `MetadataExceptionTest` uses reflection to check error-code uniqueness across `MetadataException`, `OrganizerException` and `ImageIndexer`, including the retired 180904. Keep it as the standard guard for new error ranges.
- **Index coverage gap.** 2,322 of 3,208 index entries are modified and never re-analyzed, so only the oracle covers them. A permanent regression command was considered and not recommended; a deliberate re-index is the only way to exercise them.
- **Fixture integrity.** `.gitattributes` now has `tests/fixtures/** -text` so the CRLF fixtures survive `autocrlf`.

### Code Insights

**Developer**
- `ImageProperties::getLoRASummary()` uses non-strict `in_array()` against LoRA IDs (WP-006).
- `LegacySidecarFormat` keeps the `empty($modelName)` check, so a model literally named `"0"` counts as missing (verbatim legacy parity, WP-003).
- A LoRA whose ID collides with a core bag key (for example `seed`) is silently dropped from the bag, or leaks in when the core value is absent (WP-004).
- Folder names are derived with `dirname()`/`basename()` because `FileInfo::getFolder()` throws on names like `SD1.5` (WP-004, WP-007). `ImageInfo::getSearchWords()` and `moveToFolder()` still use `getFolder()->getName()` (WP-008).
- `Builder::verifyMetadata()` returns `bool`, because Composer ignores an int return and maps `false` to exit 1 (WP-009).
- `MetadataRegistry::readObject()` uses `json_decode(..., JSON_THROW_ON_ERROR)` plus `is_array()` rather than `JSONFile::getData()`, to stay clean at PHPStan level 9 without ignores (WP-005).
- `MetadataVerifier` catches only `MetadataException` per image, so any other throwable aborts the census, and its lists hold bare file names (WP-009).
- `LoRATest::testTrackedLoraFileMatchesRawJson` reads the live, user-edited `data/loras/loras.json` and flaked once on the baseline run (WP-001).

**QA**
- Corrupt PNGs raise an uncaught `ImageHelper_Exception` from `getImageSize()`, the same as legacy and untested (WP-007).
- A UTF-8 BOM before the JSON makes a sidecar unrecognized, and a directory named like a candidate emits a PHP warning (WP-005).
- `normalizeFloat` edge outputs: `-0.0` gives `'-0'`, values below 0.00005 collapse to `'0'`, and NAN/INF pass through (WP-002, WP-004).
- `ImageProperties::getWorkflowVersion()`: a float yields null, while `'32.7'` and `'1e3'` are cast (WP-006).
- No automated tests cover `analyzeImages()`, `Builder::verifyMetadata()` exit status, a malformed-JSON sidecar in `ImageInfoTest`, or an `SD1.5` folder in the candidate and `completeProperties()` tests.

**Security Auditor**
- Sidecar-derived strings are unsanitized. `ImageBrowser.php` (about L591-596) and `ImageDetails.php` (about L153/L166) echo checkpoint, test name, seed and folder name unescaped. They are now reachable through `model.prettyName` and `image.seriesName` of the structured sidecar (flagged high in WP-007, re-confirmed in WP-010).
- There is no sidecar file-size cap and no `is_file()` guard in the registry. `findFolderImages` validation was noted as a hardening item (WP-010).

**Reviewer**
- `ImageMetadata` sub-object properties were made `readonly` as a fix-forward (WP-002).
- `.gitattributes` `tests/fixtures/** -text` was added as a fix-forward (WP-001).
- Minor style items: `$analysisResults` is declared mid-class in `ImageIndexer`, and the console skip line uses `getBaseName()` while the warning uses `getName()`. `call_user_func()` on a `Closure` in `ImageProperties::handleModified` could be a direct call.

**Release Engineer**
- Minor bump to 1.2.0 (backwards-compatible capability). The user must regenerate `.context/`, and tag and publish 1.2.0 and the 1.1.0 housekeeping themselves.
- `context.yaml`'s 1.0.0 is the CTX MCP server version and is unrelated.

**Documentation**
- `docs/workflows/readme.md` said the indexer did not read `-meta.json` files; it was corrected.
- The README scripts table does not list the test and analyze scripts (they appear only in `AGENTS.md`).

### Deferred & Follow-Up Items

| # | Type | Source | Agent | Description | Priority / rationale |
|---|---|---|---|---|---|
| 1 | Deferred | WP-007, WP-010, WP-011 | Developer / Reviewer | Reversed `str_contains('upscale', $baseName)` in `ImageAnalyzer::analyze()`. Preserved for byte-identical legacy indexing and pinned by `testUpscaleFlagCharacterisation`. It needs its own plan, because fixing it changes indexing. | Medium |
| 2 | Deferred | WP-007, WP-010 (Security Auditor) | Security Auditor | Escape checkpoint, test name, seed and folder name at output in `ImageBrowser.php` and `ImageDetails.php`. Recorded as a rule in `constraints.md` but not fixed. | High (WP-007); WP-010 audit classed it as non-blocking hardening |
| 3 | Deferred | WP-005, WP-010 | Reviewer / Security Auditor | Registry hardening: strip a leading BOM, add an `is_file()` guard, cap sidecar size in `MetadataRegistry::readObject()`/`resolve()`. Recorded as known gaps in `constraints.md`. | Low; the reviewer asked to address this before bulk indexing relies on `resolve()` |
| 4 | Deferred | WP-004, WP-008, WP-010 | Developer / Documentation | `ImageInfo::getSearchWords()` and `moveToFolder()` still use `getFolder()->getName()`, which throws for folder names like `SD1.5`; `ImageIndexer` uses `getFolder()->getName()` too. | Medium |
| 5 | Deferred | WP-007, WP-009 | QA / Developer | A corrupt PNG raises an uncaught `ImageHelper_Exception` (legacy behaviour kept). `MetadataVerifier` aborts the census on any non-`MetadataException` throwable. | Medium / Low |
| 6 | Deferred | WP-004, WP-010 | Developer / Security Auditor | A LoRA ID that collides with a core bag key is dropped or leaks into the bag. The same ambiguity exists in the legacy `custom_text` bag. | Low |
| 7 | Deferred | WP-006 | Developer / Reviewer | Non-strict `in_array()` in `ImageProperties::getLoRASummary()`. `getWorkflowVersion()` accepts `'32.7'`/`'1e3'`; tighten to digits-only if the version ever feeds a filter. Neither has a `constraints.md` entry. | Low |
| 8 | Deferred | WP-003 | Developer | Model named `"0"` is treated as missing (`empty()` parity); a strict `=== ''` would change legacy indexing. | Low |
| 9 | Deferred | WP-001 | Developer / Reviewer | `LoRATest::testTrackedLoraFileMatchesRawJson` reads the live `data/loras/loras.json`; use a committed fixture copy. | Medium (flaked once) |
| 10 | Deferred | WP-010, WP-009, WP-011 | QA | Missing automated tests: `ImageIndexer::analyzeImages()` (needs app bootstrap; verified by a sandboxed run), `Builder::verifyMetadata()` exit status, `SD1.5` folder and degenerate file names in the analyzer tests, malformed-JSON sidecar in `ImageInfoTest`. | Medium / Low |
| 11 | Deferred | WP-009 | Developer | `verifyMetadata()` and `ImageIndexer::findAllImages()` scan the image folder separately; a shared finder could replace both. Census lists hold bare file names. | Low |
| 12 | Deferred | WP-007 | Developer | Move the PNG and temp-folder helpers in `ImageAnalyzerTest` into a shared test trait if later work packages need generated images. | Low |
| 13 | Deferred | WP-002 | Developer | Add a `readObject(): ?ArrayDataCollection` helper in `BaseSidecarFormat` to avoid duplicated `is_array` narrowing. | Low |
| 14 | Out-of-scope | WP-011 | Developer | A permanent regression command for the 2,322 un-re-analyzed index entries was not recommended. The oracle (temp folder) is the only authority. | Low |
| 15 | Out-of-scope | WP-010 | Release Engineer | The user must regenerate `.context/` with the CTX Generator and tag or publish 1.2.0 (and the 1.1.0 housekeeping). | Medium |
| 16 | Out-of-scope | WP-009 | Documentation | The README scripts table omits `test` and `analyze` (they are listed in `AGENTS.md`). | Low |
| 17 | Process | Project-level | Developer / Documentation | WP-011 implementation and documentation pipelines declared no `artifacts.files_modified`; this is expected for a verification-only WP. | Low |

### Next Steps

1. Plan a security-hardening follow-up: output escaping in `ImageBrowser.php` and `ImageDetails.php` (item 2), plus the registry BOM, `is_file()` and size-cap gaps (item 3). Escaping is the most material open item because the structured sidecar widens what reaches those sinks.
2. Plan the upscale-heuristic fix (item 1) as a separate project. It must start by refreshing the population oracle and accepting a deliberate change to indexed output.
3. Replace the remaining `getFolder()->getName()` uses with the safe derivation (item 4), and add the missing tests (items 5 and 10).
4. Make `LoRATest` independent of the live `data/loras/loras.json` (item 9).
5. The user should regenerate `.context/` and tag and publish 1.2.0 (item 15).
