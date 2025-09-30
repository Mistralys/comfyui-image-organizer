# File Naming Control

This group is used to determine the name of the folder
where images are saved, as well as the file name, which
uses a counter to increment test numbers used in images.

It also adds this information into the main properties
text, which will typically be passed on to the image
renderers next.

# Changelog

## v7 - Model names
- Added inputs for the model, vae and clip names.
- Added the `modelName` property.
- Added the `VAEName` propery.
- Added the `clipNames` property.

## v6 - Workflow version
- Added the workflow version input (integer).
- Now adding the `workflowVersion` property to the text.
