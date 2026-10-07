# Sidecar fixtures

Verbatim copies of real sidecar files from the ComfyUI output folder. Do not edit, reformat or re-save them: the tests rely on the real shapes, including floating-point noise and long prompt strings.

| Fixture | Source (relative to the output folder) |
|---|---|
| `legacy-checkpoint.json` | `LichQueen/47-initial-seed100106045988674_02.json` |
| `legacy-model-vae.json` | `BarbarianCore/100-initial-seed1025741115066804u02.json` |
| `legacy-positive-prompt-top-level.json` | `AmazonBlonde-EpicRealism/133-reborn-seed1085897555240861_01.json` |
| `legacy-two-date-keys.json` | `FluxTests/146-initial-seed12537901431950u01.json` |
| `structured-flux-v32.json` | `VikingGoddess/2-flux-seed678193439927262-nr1-meta.json` |
| `structured-v31-early.json` | `AmazonBlonde-EpicRealism/168-continued-seed656765794489516-nr3-meta.json` |
| `structured-upscaled.json` | `AmazonBlonde-EpicRealism/170-continued-seed656765794489516-nr1-meta.json` |
| `structured-preview.json` | `OctoCatapult/89-flux-seed723787551876991-nr1-preview-meta.json` |
| `structured-no-flow-no-typename.json` | `OctoCatapult/87-sdxl-seed216878393744831-nr1-meta.json` |

## Synthetic file

`workflow-export.json` is **synthetic**. It is not a copy of a real file; it is a minimal stand-in for a ComfyUI workflow export (`nodes` + `links`), which must not be detected as a sidecar.

## Expected bags

Each `legacy-*.json` fixture has an `<fixture>.expected-bag.json` file next to it. It holds the `custom_text` bag produced by the original, unmodified `ImageIndexer::parseText()` (taken before that logic was moved), and is the byte-identical oracle for the legacy format.
