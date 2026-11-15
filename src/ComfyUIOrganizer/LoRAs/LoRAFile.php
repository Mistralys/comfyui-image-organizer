<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\LoRAs;

use RuntimeException;
use stdClass;

/**
 * A registry file with typed records and its original JSON, so appending never
 * rewrites existing entries, including unknown keys and empty objects.
 */
final class LoRAFile
{
    private readonly stdClass $data;

    /** @var list<LoRA> */
    private array $entries = array();

    private function __construct(
        private readonly string $path,
        private readonly string $original
    ) {
        $data = json_decode($original, false, 512, JSON_THROW_ON_ERROR);
        if(!$data instanceof stdClass) {
            throw new RuntimeException(sprintf('LoRA registry must be a JSON object: %s', $path));
        }
        $this->data = $data;

        foreach(get_object_vars($data) as $id => $entry) {
            if(!$entry instanceof stdClass) {
                throw new RuntimeException(sprintf('Invalid LoRA entry [%s] in %s.', $id, $path));
            }

            $this->entries[] = LoRA::fromJSONEntry((string)$id, json_decode(
                json_encode($entry, JSON_THROW_ON_ERROR),
                true,
                512,
                JSON_THROW_ON_ERROR
            ));
        }
    }

    public static function load(string $path) : self
    {
        $json = file_get_contents($path);
        if($json === false) {
            throw new RuntimeException(sprintf('Cannot read LoRA registry: %s', $path));
        }

        return new self($path, $json);
    }

    /** @return list<LoRA> */
    public function getEntries() : array
    {
        return $this->entries;
    }

    /**
     * Appends a new entry using a same-directory temporary file and atomic rename.
     * Refuses a stale snapshot rather than overwriting edits made since load().
     */
    public function append(LoRA $lora) : void
    {
        foreach($this->entries as $entry) {
            if($entry->getID() === $lora->getID()) {
                throw new RuntimeException(sprintf('LoRA ID [%s] already exists.', $lora->getID()));
            }
        }

        $entry = array(
            'category' => $lora->getCategory(),
            'label' => $lora->getLabel(),
            'models' => self::modelData($lora)
        );
        if($lora->getDescription() !== '') {
            $entry['description'] = $lora->getDescription();
        }
        $json = json_encode(array($lora->getID() => $entry),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $indent = preg_match('/\n([ \t]+)"/', $this->original, $matches) === 1 ? $matches[1] : '  ';
        $entryJSON = preg_replace_callback('/^( +)/m', static fn(array $match) : string =>
            str_repeat($indent, intdiv(strlen($match[1]), 4)), trim(substr($json, 1, -1), "\n"));
        $eol = str_contains($this->original, "\r\n") ? "\r\n" : "\n";
        $closingBrace = strrpos($this->original, '}');
        if($closingBrace === false || $entryJSON === null) {
            throw new RuntimeException('Cannot format the LoRA registry entry.');
        }

        $updated = rtrim(substr($this->original, 0, $closingBrace))
            .($this->entries !== array() ? ',' : '').$eol
            .str_replace("\n", $eol, $entryJSON).$eol
            .substr($this->original, $closingBrace);

        $this->write($updated);
    }

    /**
     * Adds non-conflicting base-model variants to an existing entry, retaining its
     * metadata and unknown JSON values. Flat entries become nested; this rewrites
     * JSON formatting. A dry run returns the merged record without writing.
     */
    public function mergeVariant(LoRA $incoming, bool $dryRun = false) : LoRA
    {
        foreach($this->entries as $existing) {
            if($existing->getID() !== $incoming->getID()) {
                continue;
            }
            if(array_intersect($existing->getModelNames(), $incoming->getModelNames()) !== array()) {
                throw new RuntimeException('This LoRA already has a variant for the same base model.');
            }

            $merged = new LoRA($existing->getID(), $existing->getLabel(), $existing->getCategory(),
                array_merge($existing->getModels(), $incoming->getModels()), $existing->getDescription());
            if($dryRun) {
                return $merged;
            }

            $document = clone $this->data;
            $id = $existing->getID();
            $entry = $document->{$id};
            if(!$entry instanceof stdClass) {
                throw new RuntimeException('Cannot merge a non-object LoRA entry.');
            }
            $entry = clone $entry;
            $models = $entry->models ?? null;
            if($models === array()) {
                $models = new stdClass();
            }
            if(!$models instanceof stdClass) {
                $models = new stdClass();
                $oldModel = new stdClass();
                foreach(array('source', 'file', 'triggerWords', 'notes', 'weight') as $key) {
                    if(property_exists($entry, $key)) {
                        $oldModel->{$key} = $entry->{$key};
                        unset($entry->{$key});
                    }
                }
                unset($entry->model);
                $models->{$existing->getModels()[0]->getModelName()} = $oldModel;
            } else {
                $models = clone $models;
            }
            foreach(self::modelData($incoming) as $name => $model) {
                $models->{$name} = $model;
            }
            $entry->models = $models;
            $document->{$id} = $entry;
            $json = json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            $this->write($json.PHP_EOL);
            return $merged;
        }

        throw new RuntimeException(sprintf('LoRA [%s] does not exist in this registry.', $incoming->getID()));
    }

    /** @return array<string,array<string,mixed>> */
    private static function modelData(LoRA $lora) : array
    {
        $models = array();
        foreach($lora->getModels() as $model) {
            $models[$model->getModelName()] = array(
                'source' => $model->getSource(),
                'file' => $model->getFile(),
                'triggerWords' => $model->getTriggerWords()
            );
            if($model->getNotes() !== '') {
                $models[$model->getModelName()]['notes'] = $model->getNotes();
            }
            if($model->getWeight() !== '') {
                $models[$model->getModelName()]['weight'] = $model->getWeight();
            }
        }
        return $models;
    }

    private function write(string $updated) : void
    {
        $temporary = tempnam(dirname($this->path), '.lora-');
        if($temporary === false) {
            throw new RuntimeException('Cannot create a temporary LoRA registry file.');
        }

        try {
            if(file_put_contents($temporary, $updated) !== strlen($updated)) {
                throw new RuntimeException('Cannot write the complete LoRA registry.');
            }
            if(file_get_contents($this->path) !== $this->original) {
                throw new RuntimeException('LoRA registry changed during import; please run the command again.');
            }
            if(!rename($temporary, $this->path)) {
                throw new RuntimeException('Cannot replace the LoRA registry.');
            }
        } finally {
            if(is_file($temporary) && !unlink($temporary)) {
                throw new RuntimeException(sprintf('Cannot remove temporary file: %s', $temporary));
            }
        }
    }
}
