<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\LoRAs;

use AppUtils\ArrayDataCollection;
use AppUtils\Interfaces\StringPrimaryRecordInterface;
use Mistralys\ComfyUIOrganizer\OrganizerException;
use function AppUtils\sb;

class LoRA implements StringPrimaryRecordInterface
{
    public const string KEY_LABEL = 'label';
    public const string KEY_CATEGORY = 'category';
    public const string KEY_MODELS = 'models';

    private string $id;
    private string $label;
    private string $category;

    /**
     * @var LoRAModel[]
     */
    private array $models;

    /**
     * @param string $id
     * @param string $label
     * @param string $category
     * @param LoRAModel[] $models
     */
    public function __construct(string $id, string $label, string $category, array $models)
    {
        $this->id = $id;
        $this->label = $label;
        $this->category = $category;
        $this->models = $models;
    }

    /**
     * Creates a LoRA from an entry of a LoRA definition file.
     * Supports the nested shape (a `models` object with one
     * entry per model) and the flat shape (a single model
     * described by the entry itself).
     *
     * @param string $id
     * @param mixed $entry
     * @return LoRA
     * @throws OrganizerException {@see OrganizerException::ERROR_INVALID_LORA_ENTRY}
     */
    public static function fromJSONEntry(string $id, mixed $entry) : LoRA
    {
        if(!is_array($entry)) {
            throw new OrganizerException(
                'Invalid LoRA entry.',
                sprintf(
                    'The LoRA entry [%s] is not an object, it is of type [%s].',
                    $id,
                    get_debug_type($entry)
                ),
                OrganizerException::ERROR_INVALID_LORA_ENTRY
            );
        }

        $data = ArrayDataCollection::create($entry);
        $modelsData = $data->getKey(self::KEY_MODELS);
        $models = array();

        if(is_array($modelsData))
        {
            foreach($modelsData as $modelName => $modelData) {
                $models[] = LoRAModel::fromArray(
                    (string)$modelName,
                    is_array($modelData) ? $modelData : array()
                );
            }
        }
        else
        {
            $models[] = LoRAModel::fromArray($data->getString(LoRAModel::KEY_MODEL), $entry);
        }

        return new self(
            $id,
            $data->getString(self::KEY_LABEL),
            $data->getString(self::KEY_CATEGORY),
            $models
        );
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function getLabel() : string
    {
        return $this->label;
    }

    public function getCategory() : string
    {
        return $this->category;
    }

    /**
     * @return LoRAModel[]
     */
    public function getModels() : array
    {
        return $this->models;
    }

    public function getLabelLinkedSource() : string
    {
        return (string)sb()->link($this->getLabel(), $this->getSource(), true);
    }

    /**
     * The non-empty file names of all models.
     * @return string[]
     */
    public function getFiles() : array
    {
        return $this->collectNonEmpty(static fn(LoRAModel $model) : string => $model->getFile());
    }

    /**
     * The non-empty model names, e.g. `SDXL 1.0`.
     * @return string[]
     */
    public function getModelNames() : array
    {
        return $this->collectNonEmpty(static fn(LoRAModel $model) : string => $model->getModelName());
    }

    /**
     * The source of the first model that has one.
     */
    public function getSource() : string
    {
        return $this->getFirstNonEmpty(static fn(LoRAModel $model) : string => $model->getSource());
    }

    public function getNotes() : string
    {
        return $this->getFirstNonEmpty(static fn(LoRAModel $model) : string => $model->getNotes());
    }

    public function getWeight() : string
    {
        return $this->getFirstNonEmpty(static fn(LoRAModel $model) : string => $model->getWeight());
    }

    /**
     * De-duplicated trigger words of all models, in first-seen order.
     * @return string[]
     */
    public function getTriggerWords() : array
    {
        $result = array();

        foreach($this->models as $model) {
            foreach($model->getTriggerWords() as $word) {
                if(!in_array($word, $result, true)) {
                    $result[] = $word;
                }
            }
        }

        return $result;
    }

    public function getTriggerWordsFlattened() : string
    {
        return implode(', ', $this->getTriggerWords());
    }

    /**
     * @param callable(LoRAModel):string $getter
     * @return string[]
     */
    private function collectNonEmpty(callable $getter) : array
    {
        $result = array();

        foreach($this->models as $model) {
            $value = $getter($model);
            if($value !== '') {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * @param callable(LoRAModel):string $getter
     */
    private function getFirstNonEmpty(callable $getter) : string
    {
        foreach($this->models as $model) {
            $value = $getter($model);
            if($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
