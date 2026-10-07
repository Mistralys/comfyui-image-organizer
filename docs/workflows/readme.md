# ComfyUI Workflows

## SDXL Workflow

- 3 Images per run
- Many LoRA modules selectable
- Upscaler and face detailer modules
- Saving of setting summaries for the Organizer

## Image Metadata Files

The workflows write a `-meta.json` file next to each generated image, in the
same folder. The file name follows the image's file prefix, for example
`2-flux-seed678193439927262-nr1-meta.json`. Preview renders get a
`-preview-meta.json` file instead. The Organizer's indexer reads these files and
prefers them over a legacy JSON file of the same base name.

Example:

```json
{
  "image": {
    "seed": 678193439927262,
    "width": 1600,
    "height": 900,
    "counter": 2,
    "seriesName": "flux",
    "folderName": "VikingGoddess",
    "filePrefix": "2-flux-seed678193439927262",
    "batchNumber": 1,
    "cfg": 1.0
  },
  "prompt": { "positive": "...", "negative": "" },
  "upscale": { "factor": 4, "steps": 30 },
  "loras": {
    "faetastic": { "strength": 0.3, "name": "faetastic", "triggerWords": "" },
    "_collectedTriggerWords": ""
  },
  "model": { "prettyName": "FLUX 1 DEV" },
  "flow": { "version": 32, "typeName": "FLUX1", "isPreview": false, "changelog": "{Markdown changelog source}" },
  "sampler": { "steps": 26, "name": "euler", "scheduler": "simple" }
}
```

Only keys that are not self-explanatory are described below.

| Key | Description |
|-----|-------------|
| `image.seriesName` | User-provided. Groups images thematically, typically when trying out different generation settings. By convention, the first generated images start with `initial`. |
| `image.counter` | Used internally by the workflow to generate the image numbers. |
| `image.folderName` | Name of the folder in ComfyUI's `output` folder where the images are stored. |
| `image.batchNumber` | Number of the image within the generated batch. A workflow that generates a batch of three images gives them the batch numbers 1, 2 and 3. |
| `image.cfg` | The CFG setting used for this specific batch image. It is stored per image because it is specific to each batch number. For FLUX images it is virtually always `1.0`. |
| `image.isUpscaled` | Boolean. Only present when the image has been upscaled. |
| `prompt.negative` | Things to exclude from the image. Used mainly for SDXL images; FLUX does not use negative prompts. |
| `upscale` | The upscale settings. |
| `loras` | The LoRAs that were enabled for the workflow, with their settings. |
| `loras._collectedTriggerWords` | Can be ignored; used internally by the workflow. |
| `model.prettyName` | User-provided name of the model used for generation. |
| `flow.typeName` | The type of workflow, which distinguishes FLUX from SDXL and other workflow types. Together with `flow.version`, it uniquely identifies the workflow that generated the image. |
| `flow.isPreview` | Relevant only for FLUX: enables the preview mode for fast preview rendering. |
| `flow.changelog` | Full Markdown-formatted changelog of the workflow (see changelog format below) |
| `sampler` | Details of the sampler used to generate the image. |

## Changelog Format

The workflow changelog is integrated into the workflow metadata, with each version having a dedicated section with a list of changes made.

```markdown
# Changelog

## v32 - 2026-10-07
- Fixed missing workflow version metadata
- Added `flow.typeName` metadata
- Added `flow.changelog` metadata
- Added workflow subgraph and JSON

## v31 - 2026-08-11
- Initial subgraph-enabled release.
- Everything converted to subgraphs.
```
