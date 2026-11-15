## Unreleased

- LoRAs: Registration now accepts `--id`, `--label` and `--description` for custom entry metadata, with matching dry-run output and agent-skill instructions. Explicit ID conflicts fail without renaming; model-variant merges keep existing entry metadata.

- LoRAs: Added `php tools\add_lora.php` to import Civitai metadata from a URL and filename, with category selection, model-specific variant merging, an ID prompt for same-model conflicts, and a dry-run option.
- LoRAs: Imports from a local path now also copy the file into ComfyUI, with configurable folder suggestions, destination selection and `--destination` for agents. Identical files are reused; conflicting files are never overwritten, and new copies are rolled back if registry saving fails.
- LoRAs: The `FLUX` folder alias is Dev-only (`Flux.1 D`); Schnell no longer receives Dev's destination suggestions. Base models come from the selected variant's metadata, not author descriptions.
- Agents: Added the `add-lora` skill for importing a LoRA with the CLI, resolving category and model-variant choices, and verifying the saved entry.

- Sidecars: A legacy sidecar can now hold its prompts in a `prompt` object (`positive`, `negative`) inside the date entry; a prompt in `custom_text` wins.
- Indexing: Re-indexing now adds sidecar properties that an already modified image entry lacks (for example prompts added to a sidecar later); stored values are never overwritten.
- Details page: The prompts are HTML-escaped and keep their line breaks.

- Browser: Moving or deleting images now patches only the affected entries of the image lookup file immediately; before, moved images failed to load until the next indexing run.

- Browser: Deleting the selected images is now a single request instead of one per image, with a progress message; the selection is kept if the request fails.

- Upscaling: An upscaled image is now a file beside its original with the suffix `_upscaled` (`x_00001_.png` and `x_00001_upscaled.png`). It needs no sidecar, is never indexed, and inherits the original's metadata.
- Upscaling: The browser shows the original card with an "Upscaled" badge; the details page, "Copy to output" and the image delivery use the upscaled file. The "Upscaling" filter shows images that have one.
- Upscaling: Moving or deleting an image now also moves or deletes its upscaled, PSD, JPG and website image files.
- Upscaling: Removed the manual "Set upscaled ID..." action, the "Auto-detect upscaled" Index Manager button, `composer detect-upscaled`, the pair linking, and the size and sidecar heuristics that flagged images as upscaled.
- Tools: Added the one-off `composer migrate-upscaled` (dry run) and `composer migrate-upscaled-apply`, which convert the old linked pairs to `_upscaled` files.
- Tools: `composer verify-metadata` and the indexer skip `_upscaled.png` files.

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
