<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\LoRAs;

use AppUtils\Collections\BaseStringPrimaryCollection;
use AppUtils\FileHelper\FolderInfo;
use AppUtils\FileHelper\JSONFile;
use Mistralys\ComfyUIOrganizer\OrganizerApp;

/**
 * @method LoRA getByID(string $id)
 * @method LoRA[] getAll()
 */
class LoRAsCollection extends BaseStringPrimaryCollection
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if(self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getDefaultID(): string
    {
        return $this->getAutoDefault();
    }

    /**
     * @return string[]
     */
    public function getCategories() : array
    {
        $categories = [];
        foreach($this->getAll() as $lora) {
            $category = $lora->getCategory();
            if($category !== '' && !in_array($category, $categories, true)) {
                $categories[] = $category;
            }
        }
        sort($categories);
        return $categories;
    }

    /**
     * @return JSONFile[]
     */
    public function getDataFiles() : array
    {
        return FolderInfo::factory(OrganizerApp::create()->getStorageFolder().'/loras')
            ->createFileFinder()
            ->includeExtension('json')
            ->makeRecursive()
            ->getFiles()
            ->typeJSON();
    }

    protected function registerItems(): void
    {
        foreach($this->getDataFiles() as $file) {
            $this->loadDataFile($file);
        }
    }

    /**
     * LoRA IDs must be unique across all files under `data/loras/`, as they load into one collection:
     * on a duplicate, the first one registered wins and the later one is dropped silently.
     */
    private function loadDataFile(JSONFile $dataFile) : void
    {
        foreach($dataFile->getData() as $loraID => $loraData) {
            $this->registerItem(LoRA::fromJSONEntry((string)$loraID, $loraData));
        }
    }

    public function getAllAsBucket() : LoRABucket
    {
        return new LoRABucket($this->getAll());
    }
}
