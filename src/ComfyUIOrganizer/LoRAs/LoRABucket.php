<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\LoRAs;

use AppUtils\Baskets\GenericStringPrimaryBasket;

/**
 * @method LoRA[] getAll()
 * @method LoRA getByID(string $id)
 */
class LoRABucket extends GenericStringPrimaryBasket
{
    public function getAllowedItemClasses(): array
    {
        return array(
            LoRA::class
        );
    }

    /**
     * @return string[]
     */
    public function getModels() : array
    {
        $models = array();

        foreach($this->getAll() as $lora) {
            foreach($lora->getModelNames() as $model) {
                if(!in_array($model, $models, true)) {
                    $models[] = $model;
                }
            }
        }

        sort($models);

        return $models;
    }

    /**
     * Gets all LoRAs categorized by their category.
     * @return array<string,LoRA[]>
     */
    public function getCategorized() : array
    {
        $categories = array();

        foreach($this->getAll() as $lora) {
            $category = $lora->getCategory();
            if(!isset($categories[$category])) {
                $categories[$category] = array();
            }
            $categories[$category][] = $lora;
        }

        return $categories;
    }

    /**
     * Gets LoRAs by their model name.
     *
     * @param string $modelName E.g. `SDXL 1.0` (case insensitive).
     * @return LoRA[]
     */
    public function getByModel(string $modelName) : array
    {
        $result = array();
        $modelName = strtolower($modelName);

        foreach($this->getAll() as $lora) {
            foreach($lora->getModelNames() as $name) {
                if(strtolower($name) === $modelName) {
                    $result[] = $lora;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Gets LoRAs by their model name, as a bucket of LoRAs
     * to filter and manipulate further.
     *
     * @param string $modelName E.g. `SDXL 1.0` (case insensitive).
     * @return LoRABucket
     */
    public function getByModelAsBucket(string $modelName) : LoRABucket
    {
        return new LoRABucket($this->getByModel($modelName));
    }
}
