# ComfyUI Image Organizer

PHP tool used to organize and manage my collection of ComfyUI-generated images.

## Keep track of how every image was made

It uses JSON sidecar files created by the "Save Image Extended" node with custom
metadata generated in my workflows to keep track of all settings used to generate 
the images, from the checkpoint to LoRAs that were used, their weights, and more.

> NOTE: This only works as intended when used together with my workflows, which
> can be found under [workflows](docs/workflows/).

## Features

- Browse your generated images as thumbnail cards showing seed, CFG and total steps, with adjustable card sizes and filters for folders, favorites, gallery and website images.
- See the settings behind each image on its own details page: checkpoint, sampler, steps, seed, LoRAs with their weights, and more.
- Flag images as favorites, for the gallery or for the website, and give them labels.
- Move images to other folders, delete them, or copy them to an output folder, from the browser.
- See the upscaled version of an image next to it: a file with the suffix `_upscaled` is treated as part of its image.
- Look up your LoRA collection in a filterable overview.
- Import LoRA reference entries from a Civitai URL and filename using an interactive CLI.
- Fix the LoRA paths in your ComfyUI workflows after moving or renaming LoRA files.
- Optionally send images and their settings to a personal website.

## Requirements

- PHP 8.4 or newer, with the `mbstring` and `curl` extensions
- [Composer](https://getcomposer.org/)
- A local webserver serving this folder
- ComfyUI with the "Save Image Extended" node installed
- The workflows from [docs/workflows](docs/workflows/), which write the metadata the Organizer reads

## Quick Start

Install the dependencies and create your configuration file:

```bash
composer install
cp config.dist.php config.php
```

Open `config.php` and set the constants to match your setup:

- `APP_WEBROOT_URL`: the URL under which the webserver serves this folder.
- `APP_COMFYUI_FOLDER`: the path to your ComfyUI installation.
- `APP_IMAGE_FOLDER`: the folder in which ComfyUI saves its images.

Then index your images:

```bash
composer build
```

Open the URL you set in `APP_WEBROOT_URL` in your browser to start browsing.

## Learn More

| Resource | Description |
|----------|-------------|
| [Workflows](docs/workflows/readme.md) | The ComfyUI workflows this tool is built for. |
| [Workflow templates](docs/templates/) | Reusable workflow modules, such as the LoRA module and the upscaler. |
| [config.dist.php](config.dist.php) | The documented configuration template. |
| [Changelog](changelog.md) | Release history. |
| [License](LICENSE) | MIT license. |

The guides below cover the metadata syntax, indexing and the LoRA path fixer.

### Supported sidecar formats

The indexer looks for the sidecar of each PNG image and recognizes it by its content,
not only by its name. Two formats are supported:

| Format | File name | Content |
|--------|-----------|---------|
| Structured | `<image name>-meta.json`, or `<image name>-meta_<counter>.json` when the image name ends in a counter such as `_00001_`; previews use `-preview-meta.json` | Sections for image, prompt, model, sampler, upscale, LoRAs and flow. |
| Legacy | `<image name>.json` | A single date-keyed entry with the `custom_text` property string and the checkpoint. An optional `prompt` object with `positive` and `negative` texts supplies the prompts when `custom_text` has none. |

When several candidates exist for an image, the structured file wins. A ComfyUI workflow
export saved next to an image is not a sidecar and is ignored; a JSON file that no format
recognizes is reported as a skipped image during indexing, by file name only.

Structured sidecars also carry the flow metadata of the workflow run, such as whether the
image is a preview render. The legacy format is indexed exactly as before.

### Upscaled images

An upscaled image is not indexed on its own and needs no sidecar. Place it beside the
original image with the suffix `_upscaled`; the Organizer then shows the original card
with an "Upscaled" badge, serves the upscaled file as the full-size image, and moves or
deletes it together with the original.

| Original | Upscaled version |
|----------|------------------|
| `43-refining-seed591657868797463-nr1_00001_.png` | `43-refining-seed591657868797463-nr1_00001_upscaled.png` |
| `001.png` | `001_upscaled.png` |

The image's settings are those of the original. Nothing needs refreshing: the file is
found on disk whenever the image is shown.

### Image property collection

The `custom_text` property string of the legacy format uses a custom text-based syntax
that is built in the workflow by concatenating strings. 

Hyphens are used as property separators, and colons for values. Example: 

``` 
-propName:value
```

Text that can contain hyphens or colons must be surrounded with backticks:

```
-propName:`hyphen-with:colon`
```

Example with a few image properties:

```
-sampler:dpm_2-samplerSteps:32-seed:254472155648008-imgWidth:915-imgHeight:1144
```

### Image indexing

The UI can be used to manage the images once all images have been indexed.
It will create the index file in the `data` folder with all collected metadata
extracted from the sidecar and image files.

This can be done on the command line, or via the UI.

#### Command line indexing

```bash
composer build
```

The following Composer scripts are available:

| Script | What it does |
|--------|--------------|
| `composer build` | Indexes all images and cleans up empty folders. |
| `composer index` | Indexes all images and cleans up empty folders. |
| `composer migrate-upscaled` | One-off: reports how the old, index-linked upscaled pairs would be converted to `_upscaled` files. Changes nothing. |
| `composer migrate-upscaled-apply` | One-off: converts those pairs and rebuilds the file index. A backup of `data/images.json` is written to `data/backup/` first. |
| `composer clean-folders` | Cleans up empty image folders. |
| `composer update-file-index` | Rebuilds the file index used to serve images and thumbnails. |
| `composer verify-metadata` | Reads the sidecar of every image and reports how many use each format, plus the images without a sidecar, with an unrecognized sidecar, without a model name, or with a sidecar that fails to parse. Changes nothing; exits with an error only if a sidecar fails to parse. |

### Adding a LoRA from Civitai

To delegate an import to an agent, use the [Add LoRA skill](.github/skills/add-lora/SKILL.md)
and provide the Civitai URL and filename or local path. It guides the agent through
category and destination selection, variant conflicts, a dry run and verification.

From the project folder, pass the Civitai model URL and either a filename or a full
local path. Quote both arguments, especially when they contain spaces:

```powershell
php tools\add_lora.php "https://civitai.com/models/832858/anime-art?modelVersionId=1376386" "C:\Users\newsl\Downloads\Anime Art V3.safetensors"
```

The tool fetches the name, base model and trigger words from Civitai. It generates
a camelCase ID and lists the existing categories from all LoRA registry files.
Choose a category by number or enter a name, including a new category name.
Only the filename is stored in the registry. A filename alone is a registry-only
import and need not exist locally. Supplying a local path additionally copies
that file into the configured ComfyUI installation; a missing or unreadable path
is an error. The source is kept, never moved or uploaded. No model file is downloaded.

- New entries go into `data/loras/loras.json`.
- When the ID already exists for a different base model (for example SDXL versus
  FLUX), the new model variant is added to that entry in its original registry
  file. Its label, category, description and existing variants are kept; there
  is no category or ID prompt (copying can still prompt for a destination).
- If that ID already has the same base model, the tool prompts for a different
  ID and suggests one suffixed with the Civitai version ID. It never replaces
  the existing variant. For the example above, you can enter `animeArtV3`.
  With an explicit `--id`, a conflict fails instead of prompting for another ID.
- An already registered filename (case-insensitive) or Civitai version is an error,
  even when found in another registry file.

The URL's `modelVersionId` selects the exact version. Without it, the tool uses
the first (latest) version returned by Civitai. The stored source URL always pins
the selected version. The selected variant's `baseModel` metadata (the page's
Details section) is authoritative; author descriptions may discuss other variants
and are not used to determine it. In this installation, `Flux.1 D` always means
`FLUX`; this alias is exclusively Flux.1 Dev, not Schnell or other Flux versions.

Add `--dry-run` to fetch, validate and preview without changing registries or copying files.
Use `--category=Styles` to skip the category prompt, or `--help` for usage.
Run the PHP command directly: Composer subprocesses do not reliably forward
standard input to interactive prompts on Windows.

New entries leave the existing JSON text untouched. Merging a model variant
preserves unknown JSON values but reformats the registry file. Writes use a
temporary file and refuse to replace a file changed since it was loaded.
API, input and write errors exit with status 1; success and help exit with status 0.
The Civitai API must be publicly accessible; authentication is not supported.

#### Custom entry metadata

Override the generated ID and/or Civitai label, and optionally add a description:

```powershell
php tools\add_lora.php "https://civitai.com/models/832858/anime-art?modelVersionId=1376386" "Anime Art V3.safetensors" --id=animeArtV3 "--label=Anime Art V3" "--description=Pastel anime style; suggested strength 0.8." --category=Styles --dry-run
```

All three options are independent. Without `--id`, the ID is generated from
Civitai's original name, even when `--label` is supplied. Without `--label`, the
Civitai name is used. Without `--description`, a new entry has no description.
Descriptions are user notes, not a way to override authoritative base-model metadata.
They are displayed in previews and saved under the entry's `description` key.

IDs must start with a letter and contain only letters and numbers; custom labels
must not be empty. ID and label outer whitespace is trimmed; description text
is preserved. A custom ID does not bypass duplicate filename/version checks.

These options register a new entry, not edit an existing one. When adding a
different-base-model variant to an existing ID, its existing label and description
are kept. Conflicting explicit label/description values are rejected: choose a
new ID for a separate entry or omit the conflicting overrides to merge.

#### Copying the downloaded file

The destination root is `APP_COMFYUI_FOLDER` from `config.php`, followed by
`models\loras`. Registry categories and storage folder names are independent.
The CLI suggests these existing folders and prompts for confirmation or another
folder, selected by number or relative path:

| Category | FLUX | SDXL |
|----------|------|------|
| Styles | `FLUX\ArtStyles` | `SDXL\ArtStyles` |
| Scifi | `FLUX\Scifi` | `SDXL\SciFi` |
| Fantasy | `FLUX\Fantasy` | `SDXL\Fantasy` |
| Characters | `FLUX\Characters` | Choose a folder |
| Details | Choose a folder | `SDXL\Detailers` |
| NSFW / XXX | `FLUX\NSFW` | `SDXL\zzNSFW` |

FLUX means `FLUX` / `Flux.1 D` (Dev only); SDXL includes
`SDXL 1.0`, `SDXL 0.9`, `SDXL Lightning` and `SDXL Hyper`. Unknown models, including
other Flux variants, receive no automatic suggestion. Choose a destination explicitly.
If a suggested folder does not exist, the CLI reports it and asks for an existing
folder instead. No folders are created or renamed.

Set the optional `APP_LORA_CATEGORY_FOLDERS` constant in `config.php` to override
individual suggestions; [config.dist.php](config.dist.php) contains an example.
Overrides merge with the defaults. For merged variants, the existing registry
category supplies the suggestion.

Agents and unattended commands can skip the folder prompt:

```powershell
php tools\add_lora.php "https://civitai.com/models/832858/anime-art?modelVersionId=1376386" "C:\Users\newsl\Downloads\Anime Art V3.safetensors" --category=Styles --destination=FLUX\ArtStyles --dry-run
```

`--destination` is an existing folder relative to the LoRA root, not a filename
or an absolute path. Parent traversal and folders resolving outside the root are
rejected. It requires a source path; omit it for registry-only imports.

The file keeps its original basename. Existing destination files are reused only
when size and SHA-256 match; different contents are never overwritten. A new
copy is staged and verified before being published with an atomic no-overwrite
hard link, then the registry is saved. The destination filesystem must support
hard links (such as NTFS). A failed registry save removes only that operation's
new, unchanged copy; reused files and the downloaded source are never deleted.
A rollback or cleanup failure is reported explicitly.

### LoRA path fixing

The paths for LoRAs stored in Workflows can be fixed automatically after moving
them around in the ComfyUI folders or when renaming them. 

#### Prerequisites

- The official file name must be added to the LoRA collection's JSON files.
- The official file name must be kept intact when renaming LoRA files (use prefixes only).

> HINT: The official file name is the one given when downloading the LoRA from AI model
> websites like Civitai or Huggingface. 

The LoRA JSON files (in `data/loras/`) support two shapes. A LoRA with several model
variants lists them in a `models` object, keyed by model name; every variant has its own
`file`, `source` and `triggerWords`:

```json
{
  "aztecStyle": {
    "category": "Styles",
    "label": "Aztec Style",
    "models": {
      "SDXL 1.0": {
        "source": "https://civitai.com/models/...",
        "file": "tbh264-sdxl.safetensors",
        "triggerWords": ["style of aztec"]
      }
    }
  }
}
```

The flat shape describes a single model in the entry itself, named by the `model` key:

```json
{
  "realisticBeautyModel": {
    "category": "XXX",
    "label": "Realistic Beauty Model",
    "model": "SDXL 1.0",
    "source": "https://civitai.com/models/...",
    "file": "Android_XL.safetensors",
    "triggerWords": ["beuty"]
  }
}
```

In both shapes, an optional `description` key on the entry is shown in the last column
of the LoRA overview.

The path fixer cross-references all LoRA files it finds in the ComfyUI folders with the 
official file names it finds in the LoRA collection. If a LoRA file ends with or equals
an official name, it generates the correct path including subfolders.

#### Example 

- Ralfinger's "Long Exposure" LoRA is named `ral-exposure-sdxl.safetensors`.
- In my workflow, it was saved as `ral-exposure-sdxl.safetensors`.
- I moved this to `SDXL\Environmental\LongExposure_ral-exposure-sdxl.safetensors`.

The path fixer will recognize it, and update the path in the workflow.

> NOTE: The name search is case-insensitive. 

#### Converting an image's embedded workflow

1. Drag the image into ComfyUI to load the workflow.
2. Save the workflow, give it a name you can find easily.
3. Close the workflow.
4. Run the path fixer.
5. Open the workflow again in ComfyUI.

> TIP: I usually save the workflow as `ATempImage` to show it
> at the top of the workflow lists.
