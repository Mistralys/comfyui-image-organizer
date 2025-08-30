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
This can be done on the command line:

```bash
composer build
```

This will create the index file in the `data` folder with all collected metadata
extracted from the sidecar and image files.