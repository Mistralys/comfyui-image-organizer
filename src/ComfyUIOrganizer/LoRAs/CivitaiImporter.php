<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\LoRAs;

use AppUtils\ConvertHelper;
use AppUtils\FileHelper\FolderInfo;
use Closure;
use RuntimeException;
use stdClass;

/**
 * Imports public Civitai metadata into the LoRA registry. An optional installer
 * copies a supplied local file before saving; model files are never downloaded.
 * The transport is injectable so tests need neither the network nor OrganizerApp.
 */
final class CivitaiImporter
{
    private const string ID_PATTERN = '/^[A-Za-z][A-Za-z0-9]*$/';

    /** @var Closure(string):string */
    private readonly Closure $fetch;

    /** @param (Closure(string):string)|null $fetch */
    public function __construct(
        private readonly string $folder,
        ?Closure $fetch = null,
        private readonly ?LoRAInstaller $installer = null
    ) {
        $this->fetch = $fetch ?? self::request(...);
    }

    /** @return list<LoRAFile> */
    private function loadFiles() : array
    {
        $files = FolderInfo::factory($this->folder)->createFileFinder()
            ->includeExtension('json')->makeRecursive()->getFiles()->typeJSON();

        return array_values(array_map(static fn($file) : LoRAFile => LoRAFile::load($file->getPath()), $files));
    }

    /** @return list<string> Sorted categories from every registry file. */
    public function getCategories() : array
    {
        $categories = array();
        foreach($this->loadFiles() as $file) {
            foreach($file->getEntries() as $entry) {
                if($entry->getCategory() !== '') {
                    $categories[] = $entry->getCategory();
                }
            }
        }

        $categories = array_values(array_unique($categories));
        sort($categories);
        return $categories;
    }

    /**
     * Uses modelVersionId when provided, otherwise the first (latest) API version.
     * The selected version's baseModel metadata is authoritative, never author
     * descriptions; Flux.1 D is represented by the registry's FLUX alias.
     * Stores only the supplied basename. With an installer, explicit local paths
     * must exist and are copied transactionally; basenames remain registry-only.
     * Different base models merge under the same ID; same-base-model collisions
     * require a different ID from the resolver. Filenames and versions stay unique.
     * A dry run performs the same validation without saving.
     * Optional ID, label and description apply to a new entry. An explicit ID
     * never falls back to interactive renaming. Merges preserve existing entry
     * metadata and reject conflicting explicit label/description values.
     *
     * @param (Closure(LoRA,LoRA):string)|null $resolveID Existing and incoming records.
     * @param (Closure():string)|null $resolveCategory Only called for new entries.
     */
    public function import(
        string $url,
        string $filename,
        string $category,
        bool $dryRun = false,
        ?Closure $resolveID = null,
        ?Closure $resolveCategory = null,
        ?string $id = null,
        ?string $label = null,
        ?string $description = null
    ) : LoRA
    {
        if($id !== null) {
            $id = trim($id);
            if(preg_match(self::ID_PATTERN, $id) !== 1) {
                throw new RuntimeException('LoRA ID must contain only letters and numbers, starting with a letter.');
            }
        }
        if($label !== null) {
            $label = trim($label);
            if($label === '') {
                throw new RuntimeException('A custom LoRA label must not be empty.');
            }
        }
        $this->installer?->validateInput($filename);
        $inputFilename = $filename;
        $parts = parse_url($url);
        if($parts === false
            || ($parts['scheme'] ?? '') !== 'https'
            || !in_array(strtolower($parts['host'] ?? ''), array('civitai.com', 'www.civitai.com', 'civitai.red'), true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || preg_match('~^/models/([1-9][0-9]*)(?:/[^/]*)?/?$~', $parts['path'] ?? '', $match) !== 1
        ) {
            throw new RuntimeException('Expected an HTTPS Civitai model URL, e.g. https://civitai.com/models/832858?modelVersionId=1376386');
        }

        $modelID = $match[1];
        parse_str($parts['query'] ?? '', $query);
        $versionID = $query['modelVersionId'] ?? null;
        if($versionID !== null && (!is_string($versionID) || preg_match('/^[1-9][0-9]*$/', $versionID) !== 1)) {
            throw new RuntimeException('modelVersionId must be a positive integer.');
        }

        $filename = basename(str_replace('\\', '/', $filename));
        if(preg_match('/^[^\\x00-\\x1f]+\\.safetensors$/i', $filename) !== 1) {
            throw new RuntimeException('Provide a .safetensors filename or full local path.');
        }
        $category = trim($category);
        if($category === '' && $resolveCategory === null) {
            throw new RuntimeException('A non-empty category is required.');
        }

        if($versionID === null) {
            $model = $this->readObject('https://civitai.com/api/v1/models/'.$modelID);
            $versions = $model->modelVersions ?? null;
            $latest = is_array($versions) ? ($versions[0] ?? null) : null;
            if((string)($model->id ?? '') !== $modelID
                || !$latest instanceof stdClass || !is_int($latest->id ?? null) || $latest->id < 1) {
                throw new RuntimeException('Civitai returned no usable version for this model.');
            }
            $versionID = (string)$latest->id;
        }

        $version = $this->readObject('https://civitai.com/api/v1/model-versions/'.$versionID);
        $model = $version->model ?? null;
        if(!$model instanceof stdClass
            || ($model->type ?? null) !== 'LORA'
            || !is_int($version->id ?? null) || $version->id < 1
            || (string)($version->modelId ?? '') !== $modelID
            || (string)$version->id !== $versionID
            || !is_string($model->name ?? null) || trim($model->name) === ''
            || !is_string($version->baseModel ?? null) || trim($version->baseModel) === ''
        ) {
            throw new RuntimeException('Civitai returned incomplete metadata, a different model/version, or a model that is not a LoRA.');
        }

        $words = $version->trainedWords ?? null;
        if(!is_array($words) || !array_is_list($words)
            || array_any($words, static fn(mixed $word) : bool => !is_string($word))) {
            throw new RuntimeException('Civitai returned invalid or missing trainedWords.');
        }

        $apiLabel = trim($model->name);
        $generatedID = ConvertHelper::string2Camel($apiLabel, true);
        if(preg_match(self::ID_PATTERN, $generatedID) !== 1) {
            $generatedID = 'civitai'.$modelID;
        }
        $source = sprintf('https://civitai.com/models/%s?modelVersionId=%d', $modelID, $version->id);
        $baseModel = $version->baseModel === 'Flux.1 D' ? 'FLUX' : $version->baseModel;
        $lora = new LoRA($id ?? $generatedID, $label ?? $apiLabel, $category, array(
            new LoRAModel($baseModel, $source, $filename, $words, '', '')
        ), $description ?? '');

        return $this->store($lora, $dryRun, $id !== null ? null : $resolveID, $resolveCategory, $inputFilename, $label, $description);
    }

    /**
     * @param (Closure(LoRA,LoRA):string)|null $resolveID
     * @param (Closure():string)|null $resolveCategory
     */
    private function store(
        LoRA $lora,
        bool $dryRun,
        ?Closure $resolveID,
        ?Closure $resolveCategory,
        string $inputFilename,
        ?string $customLabel,
        ?string $customDescription
    ) : LoRA
    {
        $files = $this->loadFiles();
        $filename = $lora->getFiles()[0];
        $sourceParts = parse_url($lora->getSource());
        parse_str($sourceParts['query'] ?? '', $query);
        foreach($files as $file) {
            foreach($file->getEntries() as $existing) {
                foreach($existing->getModels() as $existingModel) {
                    $existingURL = parse_url($existingModel->getSource());
                    parse_str($existingURL['query'] ?? '', $existingQuery);
                    $sameVersion = in_array($existingURL['host'] ?? '', array('civitai.com', 'www.civitai.com', 'civitai.red'), true)
                        && ($existingQuery['modelVersionId'] ?? null) === $query['modelVersionId'];
                    if(strcasecmp($existingModel->getFile(), $filename) === 0
                        || $sameVersion) {
                        throw new RuntimeException(sprintf('LoRA [%s] already uses this filename or Civitai version; nothing was changed.', $existing->getID()));
                    }
                }
            }
        }

        do {
            $renamed = false;
            foreach($files as $file) {
                foreach($file->getEntries() as $existing) {
                    if($existing->getID() !== $lora->getID()) {
                        continue;
                    }
                    if(array_intersect($existing->getModelNames(), $lora->getModelNames()) === array()) {
                        if(($customLabel !== null && $customLabel !== $existing->getLabel())
                            || ($customDescription !== null && $customDescription !== $existing->getDescription())) {
                            throw new RuntimeException(sprintf(
                                'LoRA [%s] already exists with different entry metadata. Choose a new --id or omit conflicting --label/--description overrides to merge the variant.',
                                $existing->getID()
                            ));
                        }
                        return $this->persist($inputFilename, $lora, $existing->getCategory(), $dryRun,
                            static fn() : LoRA => $file->mergeVariant($lora, $dryRun));
                    }
                    if($resolveID === null) {
                        throw new RuntimeException(sprintf('LoRA [%s] already has a variant for %s; a different ID is required.',
                            $lora->getID(), implode(', ', $lora->getModelNames())));
                    }
                    $id = trim($resolveID($existing, $lora));
                    if($id === $lora->getID() || preg_match(self::ID_PATTERN, $id) !== 1) {
                        throw new RuntimeException('Choose a different LoRA ID containing only letters and numbers, starting with a letter.');
                    }
                    $lora = new LoRA($id, $lora->getLabel(), $lora->getCategory(), $lora->getModels(), $lora->getDescription());
                    $renamed = true;
                    break 2;
                }
            }
        } while($renamed);

        $category = $lora->getCategory();
        if($category === '' && $resolveCategory !== null) {
            $category = trim($resolveCategory());
        }
        if($category === '') {
            throw new RuntimeException('A non-empty category is required.');
        }
        $lora = new LoRA($lora->getID(), $lora->getLabel(), $category, $lora->getModels(), $lora->getDescription());
        $target = LoRAFile::load($this->folder.DIRECTORY_SEPARATOR.'loras.json');
        return $this->persist($inputFilename, $lora, $category, $dryRun, static function() use ($target, $lora, $dryRun) : LoRA {
            if(!$dryRun) {
                $target->append($lora);
            }
            return $lora;
        });
    }

    /** @param Closure():LoRA $save */
    private function persist(string $inputFilename, LoRA $incoming, string $category, bool $dryRun, Closure $save) : LoRA
    {
        if($this->installer === null) {
            return $save();
        }
        return $this->installer->run($inputFilename, $incoming->getModels()[0], $category, $dryRun, $save);
    }

    private function readObject(string $url) : stdClass
    {
        $data = json_decode(($this->fetch)($url), false, 512, JSON_THROW_ON_ERROR);
        if(!$data instanceof stdClass) {
            throw new RuntimeException('Civitai returned a JSON value that is not an object.');
        }
        return $data;
    }

    private static function request(string $url) : string
    {
        $curl = curl_init($url);
        if($curl === false) {
            throw new RuntimeException('Cannot initialize the Civitai request.');
        }

        try {
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => array('Accept: application/json'),
                CURLOPT_USERAGENT => 'ComfyUI-Image-Organizer'
            ));
            if(PHP_OS_FAMILY === 'Windows') {
                curl_setopt($curl, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
            }
            $response = curl_exec($curl);
            if(!is_string($response)) {
                throw new RuntimeException('Civitai request failed: '.curl_error($curl));
            }
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if($status !== 200) {
                throw new RuntimeException(sprintf('Civitai returned HTTP %d. Check the URL and public API access.', $status));
            }
            return $response;
        } finally {
            curl_close($curl);
        }
    }
}
