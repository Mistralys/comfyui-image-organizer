# ComfyUI Image Organizer

PHP tool used to organize and manage my collection of ComfyUI-generated images.

It uses JSON sidecar files created by the "Save Image Extended" node with custom
metadata generated in my workflows to keep track of all settings used to generate 
the images, from the checkpoint to LoRAs that were used, their weights, and more.

## Image indexing

The UI can be used to manage the images once all images have been indexed.
This can be done on the command line:

```bash
composer build
```

This will create the index file in the `data` folder with all collected metadata
extracted from the sidecar and image files.