# ComfyUI Image Organizer

PHP tool used to organize and manage my collection of ComfyUI-generated images.

It uses JSON sidecar files created by the "Save Image Extended" node with custom
metadata generated in my workflows to keep track of all settings used to generate 
the images, from the checkpoint to LoRAs that were used, their weights, and more.

> NOTE: This only works as intended when used together with my workflows, which
> can be found under [workflows](docs/workflows/).

## Image property collection

To be able to collect all manner of metadata without relying on custom nodes,
I have used a custom text-based syntax that is built in the workflow by concatenating
strings. 

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

## Image indexing

The UI can be used to manage the images once all images have been indexed.
It will create the index file in the `data` folder with all collected metadata
extracted from the sidecar and image files.

This can be done on the command line, or via the UI.

### Command line indexing

```bash
composer build
```

## LoRA path fixing

The paths for LoRAs stored in Workflows can be fixed automatically after moving
them around in the ComfyUI folders or when renaming them. 

### Requirements

- The official file name must be added to the LoRA collection's JSON files.
- The official file name must be kept intact when renaming LoRA files (use prefixes only).

> HINT: The official file name is the one given when downloading the LoRA from AI model
> websites like Civitai or Huggingface. 

The path fixer cross-references all LoRA files it finds in the ComfyUI folders with the 
official file names it finds in the LoRA collection. If a LoRA file ends with or equals
an official name, it generates the correct path including subfolders.

### Example 

- Ralfinger's "Long Exposure" LoRA is named `ral-exposure-sdxl.safetensors`.
- In my workflow, it was saved as `ral-exposure-sdxl.safetensors`.
- I moved this to `SDXL\Environmental\LongExposure_ral-exposure-sdxl.safetensors`.

The path fixer will recognize it, and update the path in the workflow.

> NOTE: The name search is case-insensitive. 

### Converting an image's embedded workflow

1. Drag the image into ComfyUI to load the workflow.
2. Save the workflow, give it a name you can find easily.
3. Close the workflow.
4. Run the path fixer.
5. Open the workflow again in ComfyUI.

> TIP: I usually save the workflow as `ATempImage` to show it
> at the top of the workflow lists.
