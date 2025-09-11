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

    private function loadDataFile(JSONFile $dataFile) : void
    {
        foreach($dataFile->getData() as $loraID => $loraData) {
            $this->registerItem(new LoRA($loraID, $loraData));
        }
    }

    public function getAllAsBucket() : LoRABucket
    {
        return new LoRABucket($this->getAll());
    }
}
