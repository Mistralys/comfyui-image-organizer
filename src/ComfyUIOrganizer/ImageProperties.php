<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\ArrayDataCollection;

class ImageProperties
{
    const string KEY_TEST_NAME = 'testName';
    const string KEY_TEST_NUMBER = 'testNumber';
    const string KEY_SEED = 'seed';
    const string KEY_FOLDER_NAME = 'folder';
    public const string KEY_IMG_BATCH_NR = 'imgBatchNr';
    public const string KEY_FAVORITE = 'favorite';
    public const string KEY_UPSCALED_IMAGE = 'upscaledImage';

    private ArrayDataCollection $data;

    /**
     * @var callable
     */
    private $modifiedCallback;

    public function __construct(ArrayDataCollection $data, callable $modifiedCallback)
    {
        $this->data = $data;
        $this->modifiedCallback = $modifiedCallback;
    }

    public function getSeed() : int
    {
        return $this->data->getInt(self::KEY_SEED);
    }

    public function getFolderName() : string
    {
        return $this->data->getString(self::KEY_FOLDER_NAME);
    }

    public function getTestNumber() : int
    {
        return $this->data->getInt(self::KEY_TEST_NUMBER);
    }

    public function getBatchNumber() : int
    {
        return $this->data->getInt(self::KEY_IMG_BATCH_NR);
    }

    public function getTestName() : string
    {
        return $this->data->getString(self::KEY_TEST_NAME);
    }

    public function serialize() : array
    {
        $data = $this->data->getData();

        ksort($data);

        return $data;
    }

    public function setFavorite(bool $favorite) : self
    {
        return $this->setKey(self::KEY_FAVORITE, $favorite);
    }

    private ?ImageInfo $upscaledImage = null;
    private bool $upscaledImageSet = false;

    public function getUpscaledImage() : ?ImageInfo
    {
        if($this->upscaledImageSet) {
            return $this->upscaledImage;
        }

        $this->upscaledImageSet = true;

        $id = $this->data->getString(self::KEY_UPSCALED_IMAGE);
        $collection = OrganizerApp::create()->createImageCollection();

        if(!empty($id) && $collection->idExists($id)) {
            $this->upscaledImage = $collection->getByID($id);
        }

        return $this->upscaledImage;
    }

    public function setUpscaledImage(ImageInfo $image) : self
    {
        return $this->setKey(self::KEY_UPSCALED_IMAGE, $image->getID());
    }

    private function setKey(string $name, mixed $value) : self
    {
        $this->data->setKey($name, $value);
        $this->handleModified();
        return $this;
    }

    private function handleModified() : void
    {
        call_user_func($this->modifiedCallback);
    }

    public function isFavorite() : bool
    {
        return $this->data->getBool(self::KEY_FAVORITE);
    }

    public function setFolderName(string $folderName) : self
    {
        return $this->setKey(self::KEY_FOLDER_NAME, $folderName);
    }
}