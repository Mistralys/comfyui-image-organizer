# ComfyUI Image Organizer

PHP tool used to organize and manage my collection of ComfyUI-generated images.

## Keep track of how every image was made

It uses JSON sidecar files created by the "Save Image Extended" node with custom
metadata generated in my workflows to keep track of all settings used to generate 
the images, from the checkpoint to LoRAs that were used, their weights, and more.

> NOTE: This only works as intended when used together with my workflows, which
> can be found under [workflows](docs/workflows/).

## Features

- Browse your generated images as thumbnail cards, with adjustable card sizes and filters for folders, favorites, gallery and website images.
- See the settings behind each image on its own details page: checkpoint, sampler, steps, seed, LoRAs with their weights, and more.
- Flag images as favorites, for the gallery or for the website, and give them labels.
- Move images to other folders, delete them, or copy them to an output folder, from the browser.
- Link an image to its upscaled version, so the pair is handled together.
- Look up your LoRA collection in a filterable overview.
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
| Legacy | `<image name>.json` | A single date-keyed entry with the `custom_text` property string and the checkpoint. |

When several candidates exist for an image, the structured file wins. A ComfyUI workflow
export saved next to an image is not a sidecar and is ignored; a JSON file that no format
recognizes is reported as a skipped image during indexing, by file name only.

Structured sidecars also carry the flow metadata of the workflow run, such as whether the
image is a preview render. The legacy format is indexed exactly as before.

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
| `composer build` | Indexes all images, detects upscaled versions and cleans up empty folders. |
| `composer index` | Indexes all images and cleans up empty folders. |
| `composer detect-upscaled` | Detects upscaled versions of indexed images. |
| `composer clean-folders` | Cleans up empty image folders. |
| `composer update-file-index` | Rebuilds the file index used to serve images and thumbnails. |
| `composer verify-metadata` | Reads the sidecar of every image and reports how many use each format, plus the images without a sidecar, with an unrecognized sidecar, without a model name, or with a sidecar that fails to parse. Changes nothing; exits with an error only if a sidecar fails to parse. |

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
