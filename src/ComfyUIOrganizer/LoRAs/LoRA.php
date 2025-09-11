<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\LoRAs;

use AppUtils\ArrayDataCollection;
use AppUtils\Interfaces\StringPrimaryRecordInterface;

class LoRA implements StringPrimaryRecordInterface
{
    public const string KEY_LABEL = 'label';
    public const string KEY_FILE = 'file';
    public const string KEY_SOURCE = 'source';
    public const string KEY_CATEGORY = 'category';
    public const string KEY_TRIGGER_WORDS = 'triggerWords';
    public const string KEY_MODEL = 'model';
    public const string KEY_NOTES = 'notes';
    public const string KEY_WEIGHT = 'weight';

    private string $id;
    private ArrayDataCollection $data;

    public function __construct(string $id, array $data)
    {
        $this->id = $id;
        $this->data = ArrayDataCollection::create($data);
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function getLabel() : string
    {
        return $this->data->getString(self::KEY_LABEL);
    }

    public function getFile() : string
    {
        return $this->data->getString(self::KEY_FILE);
    }

    public function getSource() : string
    {
        return $this->data->getString(self::KEY_SOURCE);
    }

    public function getCategory() : string
    {
        return $this->data->getString(self::KEY_CATEGORY);
    }

    public function getNotes() : string
    {
        return $this->data->getString(self::KEY_NOTES);
    }

    public function getWeight() : string
    {
        return $this->data->getString(self::KEY_WEIGHT);
    }

    public function getTriggerWords() : array
    {
        return $this->data->getArrayFlavored(self::KEY_TRIGGER_WORDS)->toIndexedStrings(true);
    }

    public function getModel() : string
    {
        return $this->data->getString(self::KEY_MODEL);
    }
}
