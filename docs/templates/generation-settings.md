# Generation Settings Module 

Groups all main generation settings for the workflow:

- Checkpoint
- Image size
- Seed
- Steps
- Sampler
- Scheduler

It has output and input connectors for the LoRAs, and main output connectors
for the rest of the workflow.

# Changelog

## v8 
- Removed the checkpoint loader, now a separate module (to switch SDXL/FLUX).
- Added `Model`, `Clip` and `VAE` inputs.
- More compact layout.

## v7 
- Reordered the outgoing connectors to the LoRAs for the new v5 LoRA module.

## v6
- Initial GIT version.
