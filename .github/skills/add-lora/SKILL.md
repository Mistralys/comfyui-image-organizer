---
name: add-lora
description: Add a LoRA to the ComfyUI Image Organizer registry from a Civitai URL and filename or local path, and copy local files into ComfyUI. Use when the user asks to add, register, import or install a Civitai LoRA, including custom IDs, labels and descriptions, category and destination selection, and model-variant conflicts with the add_lora.php CLI.
---

# Add a LoRA from Civitai

Use the existing [CLI](../../../tools/add_lora.php), not manual registry edits.
The [README](../../../README.md#adding-a-lora-from-civitai) describes its behavior.
This skill operates the tool; it does not change application code.

## Inputs

- A Civitai model URL, optionally containing `modelVersionId`.
- A `.safetensors` filename or full local path.
- Optionally, a custom registry ID, label, description, category and destination subfolder.

Ask for missing URL or filename. Preserve an explicit version ID and the filename
exactly, including spaces and capitalization. The CLI stores only the basename.
A filename alone is registry-only and need not exist locally. An explicit local
path must exist and is copied by the CLI into ComfyUI. Preserve the downloaded
source; do not upload, download, move, rename or manually edit model files.
Honor user-supplied metadata instead of generating replacements. Pass custom
values with `--id=`, `--label=` and `--description=`. These are independent:
changing the label alone does not change the ID generated from Civitai's name.
Do not invent a description or ask for optional metadata the user did not request.
IDs start with a letter and contain only letters and numbers; labels cannot be
empty. ID and label outer whitespace is trimmed; description text is preserved.

## Procedure

### 1. Prepare

Work from the organizer repository root, not the ComfyUI output folder. Follow
its `AGENTS.md`. Inspect existing changes under `data/loras/` so they are not
mistaken for this import's changes.

Run `php tools\add_lora.php --help` if the command's current behavior is uncertain.
The application needs PHP 8.4+, cURL, its installed Composer dependencies and
`config.php`. Report missing prerequisites; never invent configuration or disable
TLS verification to get past an API error.
The copy root is `APP_COMFYUI_FOLDER` followed by `models\loras`, not a hard-coded
machine path. Optional `APP_LORA_CATEGORY_FOLDERS` overrides supply folder
suggestions; categories and physical folder names are not interchangeable.
Use the selected variant's `baseModel` metadata (Civitai's Details section),
never model-page prose, to identify its base model. Descriptions can mention
unrelated variants. This installation uses `FLUX` exclusively for `Flux.1 D`
(Dev); do not equate Schnell or other Flux variants with that alias.

Invoke PHP directly, not through Composer: Composer subprocesses do not reliably
forward standard input to these prompts on Windows. Use PowerShell argument
variables, not an interpolated shell command or `Invoke-Expression`.

Set `$customID`, `$customLabel` and `$customDescription` to the supplied values,
or `$null` for each omitted value. Build optional arguments and carry them
through discovery, the successful preview and the final save:

```powershell
$entryOptions = @()
if ($null -ne $customID) { $entryOptions += "--id=$customID" }
if ($null -ne $customLabel) { $entryOptions += "--label=$customLabel" }
if ($null -ne $customDescription) { $entryOptions += "--description=$customDescription" }
```

### 2. Discover the required choices without writing

Set `$url` and `$file` to the user's values. Start with a dry run. If the category
is unknown, use:

```powershell
$null | php tools\add_lora.php @entryOptions --dry-run -- $url $file
```

Closing standard input prevents unattended execution from hanging at a prompt.
This discovery call can exit 1 because an answer is missing; that is an incomplete
preview, not a successful import. Read the actual message before proceeding.

- **New entry:** the CLI lists existing categories and requests one. Show the
  choices to the user and ask for a category unless already supplied. A new
  category name is allowed. Prefer names over numbered choices when automating,
  because the ordering may change.
- **Existing ID, different base model:** the CLI merges the model variant into
  that entry, keeping its category, label, description and existing variants.
  Do not ask for a category or different ID for this case. A local file can still
  require a destination choice. Explicit label/description values that differ
  from the existing entry are rejected rather than ignored or written over it.
  Ask whether to use a new ID for a separate entry or omit those overrides for
  the merge; never discard requested metadata without the user's decision.
- **Generated ID, same base model:** ask for a different ID, showing the CLI's
  suggestion. IDs must start with a letter and contain only letters and numbers.
  Do not replace the existing variant. Set the answer as `$customID`, rebuild
  `$entryOptions`, and use `--id` on the next run to avoid the prompt.
- **Explicit ID, same base model:** the command fails without interactive
  renaming. Ask for a different ID; never silently replace the user's chosen ID.
- **Duplicate filename/version, API error or other error:** report it. Do not
  bypass duplicate checks by changing the filename or editing JSON manually.
- **Copy destination:** for a local source, the CLI lists existing subfolders and
  suggests a folder when the base model/category has a mapping. Show the proposed
  destination and alternatives, and ask for a choice unless already supplied.
  Unknown models receive no automatic suggestion; do not assume FLUX or SDXL.
  Use the selected relative folder with `--destination`, never an absolute path.
  Only existing folders inside the LoRA root are accepted; do not create or
  reorganize folders without a separate request.

When an ID prompt occurs before category selection, retrieve the existing
categories without writing or making another API request:

```powershell
@'
<?php
require 'prepend.php';
$importer = new Mistralys\ComfyUIOrganizer\LoRAs\CivitaiImporter('data\loras');
echo implode(PHP_EOL, $importer->getCategories()), PHP_EOL;
'@ | php
```

Ask for the alternative ID and category together when both are needed.
After resolving those choices, another incomplete dry run may be needed to
discover the destination prompt. Do not treat that missing-input exit as success.

### 3. Complete and inspect the preview

Set `$copyOptions = @()` for a filename-only import. For a local source path,
after the destination has been selected, set:

```powershell
$copyOptions = @("--destination=$destination")
```

Do not supply `--destination` with a filename-only import.

For a new entry with a chosen category:

```powershell
$null | php tools\add_lora.php @entryOptions "--category=$category" @copyOptions --dry-run -- $url $file
```

For a resolved same-base-model conflict, use the same command after rebuilding
`$entryOptions` with the newly chosen `--id`. Do not pipe a rename answer when an
explicit ID is supplied; such conflicts fail rather than prompt.

For a different-base-model merge, omit `--category`; use `@copyOptions` if copying.
Require exit 0 and inspect the preview's ID, label, description when supplied,
category, base model, filename,
source URL, trigger words and copy destination (or registry-only message).
An existing destination is reused only when size and SHA-256 match; different
contents are an error, never an invitation to overwrite.
If another collision or unexpected merge occurs,
resolve it with the user rather than guessing additional prompt answers.

Without `modelVersionId`, the CLI selects Civitai's first (latest) version.
Before saving, set `$url` to the incoming variant's pinned source URL from the
successful preview, so a newer release cannot change the version being imported.
For a merge, distinguish the incoming variant from existing variants also shown.

### 4. Save and verify

If the user requested only a preview, stop without saving. Otherwise repeat the
successful command with the same choices and pinned URL, removing `--dry-run`.
An import request authorizes that registry update; no extra confirmation is
needed once ambiguous choices have been resolved.

Require exit 0 and `Saved LoRA registry entry.`. Inspect the resulting entry and
compare the registry changes with the state before importing:

- New entries belong in `data/loras/loras.json`.
- Different-base-model merges update the existing entry's original JSON file,
  which can be another file under `data/loras/`.
- The filename, pinned version URL, base model and trigger words must match the
  preview. Existing variants, entry metadata and unrelated values must remain.
  A merge can reformat the file; a new-entry append preserves existing JSON text.
- A new entry's custom ID, label and description must match the successful
  preview and the user's choices. Preserve these flags on the saving command.
- For a local source, require `Copied to:` or `Reusing identical file:` and verify
  the destination exists with the expected basename and matching source contents.
  The downloaded source must still exist. Registry-only imports must not copy a file.

If the CLI reports a registry changed during import, inspect the new state and
repeat the dry run before retrying. Never overwrite concurrent edits.
The CLI rolls back a new unchanged copy if registry saving fails; it never removes
a reused file. Report rollback or cleanup failures and their paths. Do not
manually delete an existing or changed destination to make a retry succeed.
Do not rebuild the image index, fix workflows or commit changes unless requested.

## Report

Briefly report the saved ID, label, category, base model, filename and affected registry
file, with a link to that file. For local sources, also link the copied or reused
destination and state that the source was kept. Include the description and
trigger words when present. Distinguish
an added entry from a merged variant, a dry run, an existing duplicate or a failed
import. Never claim a dry run or missing-input exit saved anything.
