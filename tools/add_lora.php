<?php

declare(strict_types=1);

use Mistralys\ComfyUIOrganizer\LoRAs\CivitaiImporter;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRA;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRAInstaller;
use const Mistralys\ComfyUIOrganizer\Config\APP_COMFYUI_FOLDER;

require_once __DIR__.'/../prepend.php';

$usage = 'Usage: php tools\add_lora.php <Civitai URL> <filename or path> [--id=ID] [--label=Label] [--description=Text] [--category=Styles] [--destination=FLUX\ArtStyles] [--dry-run]';

try {
    $positional = array();
    $category = null;
    $destination = null;
    $id = null;
    $label = null;
    $description = null;
    $dryRun = false;
    $optionsEnded = false;
    foreach(array_slice($argv, 1) as $argument) {
        if($optionsEnded) {
            $positional[] = $argument;
            continue;
        }
        if($argument === '--') {
            $optionsEnded = true;
            continue;
        }
        if($argument === '--help' || $argument === '-h') {
            echo $usage.PHP_EOL
                .'Without --category, lists existing categories and prompts for a number or category name.'.PHP_EOL
                .'Different base models merge; same-base-model collisions prompt for a different ID.'.PHP_EOL
                .'--id and --label override the generated values; --description adds entry notes. Explicit ID conflicts fail without renaming.'.PHP_EOL
                .'Merging preserves existing entry metadata; conflicting label/description overrides are rejected.'.PHP_EOL
                .'A local source path also copies the file into <ComfyUI>\models\loras; a filename alone is registry-only.'.PHP_EOL
                .'--destination selects an existing subfolder relative to that root; otherwise prompts with a category-based suggestion.'.PHP_EOL
                .'--dry-run fetches and previews the entry without modifying any files.'.PHP_EOL;
            exit(0);
        }
        if($argument === '--dry-run') {
            $dryRun = true;
        } elseif(str_starts_with($argument, '--category=')) {
            $category = substr($argument, strlen('--category='));
        } elseif(str_starts_with($argument, '--destination=')) {
            $destination = substr($argument, strlen('--destination='));
        } elseif(str_starts_with($argument, '--id=')) {
            $id = substr($argument, strlen('--id='));
        } elseif(str_starts_with($argument, '--label=')) {
            $label = substr($argument, strlen('--label='));
        } elseif(str_starts_with($argument, '--description=')) {
            $description = substr($argument, strlen('--description='));
        } elseif(str_starts_with($argument, '-')) {
            throw new RuntimeException('Unknown option: '.$argument);
        } else {
            $positional[] = $argument;
        }
    }

    if(count($positional) !== 2) {
        throw new RuntimeException($usage);
    }

    /** @param list<string> $folders */
    $chooseDestination = static function(?string $suggested, array $folders, string $model, string $category) : string {
        printf('Copy destination for %s / %s:%s', $model, $category, PHP_EOL);
        $suggested = $suggested !== null ? str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $suggested) : null;
        if($suggested !== null && !in_array($suggested, $folders, true)) {
            printf('Mapped folder is unavailable: %s%s', $suggested, PHP_EOL);
            $suggested = null;
        }
        foreach($folders as $index => $folder) {
            printf('  %d. %s%s%s', $index + 1, $folder, $folder === $suggested ? ' (suggested)' : '', PHP_EOL);
        }
        if($folders === array()) {
            throw new RuntimeException('No existing subfolders under the ComfyUI LoRA root. Create a suitable folder before importing.');
        }
        printf('Destination (number or existing relative folder)%s: ', $suggested !== null ? ' ['.$suggested.']' : '');
        $answer = fgets(STDIN);
        if($answer === false) {
            throw new RuntimeException('No destination received. Use --destination=Relative\Folder for unattended imports.');
        }
        $selected = trim($answer);
        if($selected === '' && $suggested !== null) {
            return $suggested;
        }
        if(ctype_digit($selected)) {
            $selected = $folders[(int)$selected - 1] ?? '';
        }
        if($selected === '') {
            throw new RuntimeException('Select an existing destination folder.');
        }
        return $selected;
    };
    $mappingConstant = 'Mistralys\\ComfyUIOrganizer\\Config\\APP_LORA_CATEGORY_FOLDERS';
    $installer = new LoRAInstaller(
        APP_COMFYUI_FOLDER.DIRECTORY_SEPARATOR.'models'.DIRECTORY_SEPARATOR.'loras',
        defined($mappingConstant) ? constant($mappingConstant) : array(),
        $destination,
        $chooseDestination
    );
    $importer = new CivitaiImporter(__DIR__.'/../data/loras', installer: $installer);
    $chooseCategory = static function() use ($importer) : string {
        $categories = $importer->getCategories();
        echo 'Existing categories:'.PHP_EOL;
        foreach($categories as $index => $existing) {
            printf('  %d. %s%s', $index + 1, $existing, PHP_EOL);
        }
        if($categories === array()) {
            echo '  (none yet)'.PHP_EOL;
        }
        echo 'Category (number or name; a new name creates a category): ';
        $answer = fgets(STDIN);
        if($answer === false) {
            throw new RuntimeException('No category received. Use --category=Name for non-interactive imports.');
        }
        $selected = trim($answer);
        if(ctype_digit($selected)) {
            $selected = $categories[(int)$selected - 1] ?? '';
            if($selected === '') {
                throw new RuntimeException('Invalid category number.');
            }
        }
        return $selected;
    };
    $chooseID = static function(LoRA $existing, LoRA $incoming) : string {
        if(preg_match('/[?&]modelVersionId=([0-9]+)/', $incoming->getSource(), $match) !== 1) {
            throw new RuntimeException('Imported LoRA has no Civitai version ID.');
        }
        $suggested = $incoming->getID().'V'.$match[1];
        printf('Entry [%s] already has a %s variant. It will be kept unchanged.%s',
            $existing->getID(), implode(', ', $incoming->getModelNames()), PHP_EOL);
        printf('ID for this version [%s]: ', $suggested);
        $answer = fgets(STDIN);
        if($answer === false) {
            throw new RuntimeException('No alternative LoRA ID received.');
        }
        return trim($answer) !== '' ? trim($answer) : $suggested;
    };
    if($category !== null && trim($category) === '') {
        throw new RuntimeException('A non-empty category is required.');
    }

    $lora = $importer->import($positional[0], $positional[1], $category ?? '', $dryRun, $chooseID, $chooseCategory,
        id: $id, label: $label, description: $description);
    printf('%s [%s]: %s%s', $dryRun ? 'Would add' : 'Added', $lora->getID(), $lora->getLabel(), PHP_EOL);
    printf('Category: %s%s', $lora->getCategory(), PHP_EOL);
    if($lora->getDescription() !== '') {
        printf('Description: %s%s', $lora->getDescription(), PHP_EOL);
    }
    foreach($lora->getModels() as $model) {
        printf('Model: %s%sFile: %s%sSource: %s%sTrigger words: %s%s',
            $model->getModelName(), PHP_EOL, $model->getFile(), PHP_EOL,
            $model->getSource(), PHP_EOL, implode(', ', $model->getTriggerWords()), PHP_EOL);
    }
    if($installer->getLastDestination() !== null) {
        printf('%s: %s%s', $installer->isReusingFile() ? 'Reusing identical file' : ($dryRun ? 'Would copy to' : 'Copied to'),
            $installer->getLastDestination(), PHP_EOL);
    } else {
        echo 'Registry-only import; no model file copied.'.PHP_EOL;
    }
    echo $dryRun ? 'Dry run; nothing was changed.'.PHP_EOL : 'Saved LoRA registry entry.'.PHP_EOL;
} catch(Throwable $e) {
    fwrite(STDERR, 'Error: '.$e->getMessage().PHP_EOL);
    exit(1);
}
