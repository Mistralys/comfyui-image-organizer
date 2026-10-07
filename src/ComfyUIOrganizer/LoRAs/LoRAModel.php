<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\LoRAs;

use AppUtils\ArrayDataCollection;

/**
 * One model variant of a LoRA (e.g. the SDXL 1.0 build), read-only.
 */
class LoRAModel
{
    public const string KEY_FILE = 'file';
    public const string KEY_SOURCE = 'source';
    public const string KEY_TRIGGER_WORDS = 'triggerWords';
    public const string KEY_MODEL = 'model';
    public const string KEY_NOTES = 'notes';
    public const string KEY_WEIGHT = 'weight';

    private string $modelName;
    private string $source;
    private string $file;

    /**
     * @var string[]
     */
    private array $triggerWords;
    private string $notes;
    private string $weight;

    /**
     * @param string $modelName
     * @param string $source
     * @param string $file
     * @param string[] $triggerWords
     * @param string $notes
     * @param string $weight
     */
    public function __construct(
        string $modelName,
        string $source,
        string $file,
        array $triggerWords,
        string $notes,
        string $weight
    ) {
        $this->modelName = $modelName;
        $this->source = $source;
        $this->file = $file;
        $this->triggerWords = $triggerWords;
        $this->notes = $notes;
        $this->weight = $weight;
    }

    /**
     * @param string $modelName
     * @param array<int|string,mixed> $data
     * @return self
     */
    public static function fromArray(string $modelName, array $data) : self
    {
        $collection = ArrayDataCollection::create($data);

        return new self(
            $modelName,
            $collection->getString(self::KEY_SOURCE),
            $collection->getString(self::KEY_FILE),
            $collection->getArrayFlavored(self::KEY_TRIGGER_WORDS)->toIndexedStrings(true),
            $collection->getString(self::KEY_NOTES),
            $collection->getString(self::KEY_WEIGHT)
        );
    }

    public function getModelName() : string
    {
        return $this->modelName;
    }

    public function getSource() : string
    {
        return $this->source;
    }

    public function getFile() : string
    {
        return $this->file;
    }

    /**
     * @return string[]
     */
    public function getTriggerWords() : array
    {
        return $this->triggerWords;
    }

    public function getNotes() : string
    {
        return $this->notes;
    }

    public function getWeight() : string
    {
        return $this->weight;
    }
}
