## Unreleased

- Index Manager: Added "Discover new folders", which indexes only image folders that have no image in the index yet, instead of a full refresh.

- Browser: Added a "Copy path" item to the image "More" menu; it copies the image's absolute path to the clipboard and confirms it.

## 1.2.0 - Structured sidecar metadata

**The indexer now reads the structured `-meta.json` sidecars the workflows write,
next to the legacy ones.** Legacy images are indexed exactly as before.

- Sidecars: Structured `-meta.json` sidecars are recognized by their content, including the flow metadata of the workflow run.
- Sidecars: A structured sidecar is preferred over any other candidate; workflow exports beside an image are ignored.
- Indexing: A JSON file that no format recognizes is reported as a skipped image instead of aborting the run.
- Indexing: The per-image logic moved into `ImageAnalyzer` and is now covered by tests.
- Tools: Added `composer verify-metadata`, a read-only census of every image's sidecar.
- LoRAs: The overview shows the optional `description` of each LoRA in a new last column.

## 1.1.0 - Typed data and PHPStan level 9

**The LoRA overview and workflow path fixing can now read nested LoRA definition files.**
Image index and workflow files keep unknown keys when saved, and the codebase now has
its first automated tests.

- LoRAs: Overview and workflow path fixing now read the nested format as well as the flat one.
- Data: Image index and workflows preserve unknown keys when written back.
- Data: JSON files are read into typed data objects at the file boundaries.
- Tests: Added the first PHPUnit tests for data objects, session state, LoRAs and workflows.
- Analysis: PHPStan raised to level 9 with no findings and no baseline.

## 1.0.0 - Initial release
- First release with all initially planned features.
