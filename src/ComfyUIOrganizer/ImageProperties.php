<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\ArrayDataCollection;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRAsCollection;

class ImageProperties
{
    public const string KEY_TEST_NAME = 'testName';
    public const string KEY_TEST_NUMBER = 'testNumber';
    public const string KEY_SEED = 'seed';
    public const string KEY_FOLDER_NAME = 'folder';
    public const string KEY_IMG_BATCH_NR = 'imgBatchNr';
    public const string KEY_FAVORITE = 'favorite';
    public const string KEY_FOR_WEBSITE = 'forWebsite';
    public const string KEY_FOR_GALLERY = 'forGallery';
    public const string KEY_UPSCALED_IMAGE = 'upscaledImage';
    public const string KEY_CFG = 'imgCFG';
    public const string KEY_PROMPT_NEGATIVE = 'promptNegative';
    public const string KEY_PROMPT_POSITIVE = 'promptPositive';
    public const string KEY_SAMPLER = 'sampler';
    public const string KEY_SAMPLER_STEPS = 'samplerSteps';
    public const string KEY_SCHEDULER = 'scheduler';
    public const string KEY_FACEFIX = 'isFacefix';

    private ArrayDataCollection $data;

    /**
     * @var callable
     */
    private $modifiedCallback;
    private ImageInfo $image;

    public function __construct(ImageInfo $image, ArrayDataCollection $data, callable $modifiedCallback)
    {
        $this->image = $image;
        $this->data = $data;
        $this->modifiedCallback = $modifiedCallback;
    }

    public function getSeed() : int
    {
        return $this->data->getInt(self::KEY_SEED);
    }

    public function getCFG() : float
    {
        return $this->data->getFloat(self::KEY_CFG);
    }

    public function getPromptNegative() : string
    {
        return $this->data->getString(self::KEY_PROMPT_NEGATIVE);
    }

    public function getPromptPositive() : string
    {
        return $this->data->getString(self::KEY_PROMPT_POSITIVE);
    }

    public function getSampler() : string
    {
        return $this->data->getString(self::KEY_SAMPLER);
    }

    public function getSamplerSteps() : int
    {
        return $this->data->getInt(self::KEY_SAMPLER_STEPS);
    }

    public function getScheduler() : string
    {
        return $this->data->getString(self::KEY_SCHEDULER);
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

    public function getLoRASummary() : string
    {
        $loraIDs = LoRAsCollection::getInstance()->getIDs();

        $loras = array_filter(
            $this->serialize(),
            function ($key) use ($loraIDs) {
                return in_array($key, $loraIDs);
            },
            ARRAY_FILTER_USE_KEY
        );

        ksort($loras);

        $loraSummary = array();
        foreach ($loras as $key => $value)
        {
            $loraSummary[] = $key.' '.str_replace('0.', '.', $value);
        }

        return implode(', ', $loraSummary);
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

    public function setFavorite(bool $enabled) : self
    {
        return $this->setKey(self::KEY_FAVORITE, $enabled);
    }

    public function setForWebsite(bool $enabled) : self
    {
        if($enabled) {
            $this->setForGallery(true);
        }

        return $this->setKey(self::KEY_FOR_WEBSITE, $enabled);
    }

    public function setForGallery(bool $enabled) : self
    {
        if($enabled) {
            $this->setFavorite(true);
        }

        return $this->setKey(self::KEY_FOR_GALLERY, $enabled);
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

    /**
     * Sets the upscaled image for this image.
     *
     * > NOTE: The upscaled image will also inherit this
     * > image's favorite status and gallery status if they
     * > are enabled for this image.
     *
     * @param ImageInfo $image
     * @return self
     */
    public function setUpscaledImage(ImageInfo $image) : self
    {
        $this->upscaledImageSet = false;
        $this->upscaledImage = null;

        $this->setKey(self::KEY_UPSCALED_IMAGE, $image->getID());

        $image->registerLowResImage($this->image);

        return $this;
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

    public function isForGallery() : bool
    {
        return $this->data->getBool(self::KEY_FOR_GALLERY);
    }

    public function isForWebsite() : bool
    {
        return $this->data->getBool(self::KEY_FOR_WEBSITE);
    }

    public function isFacefix() : bool
    {
        return $this->data->getBool(self::KEY_FACEFIX);
    }

    public function setFolderName(string $folderName) : self
    {
        return $this->setKey(self::KEY_FOLDER_NAME, $folderName);
    }
}