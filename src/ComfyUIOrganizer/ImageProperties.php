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

    public function getTestName() : string
    {
        return $this->data->getString(self::KEY_TEST_NAME);
    }

    public function serialize() : array
    {
        return $this->data->getData();
    }

    public function setFavorite(bool $favorite) : self
    {
        return $this->setKey(self::KEY_FAVORITE, $favorite);
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
}