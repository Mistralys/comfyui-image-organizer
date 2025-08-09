<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Ajax\Methods;

use Mistralys\ComfyUIOrganizer\Ajax\BaseImageMethod;
use Mistralys\ComfyUIOrganizer\ImageInfo;

class SetUpscaledImageMethod extends BaseImageMethod
{
    public const string METHOD_NAME = 'SetUpscaledImage';
    public const string REQUEST_PARAM_UPSCALED_ID = 'upscaledID';
    public const int ERROR_UPSCALED_IMAGE_NOT_FOUND = 179501;
    public const string RESPONSE_UPSCALED_FOR_GALLERY = 'upscaledForGallery';
    public const string RESPONSE_UPSCALED_FAVORITE = 'upscaledFavorite';
    public const string RESPONSE_UPSCALED_ID = 'upscaledID';

    public function getID(): string
    {
        return self::METHOD_NAME;
    }

    protected function processImage(ImageInfo $image): never
    {
        $upscaledID = $this->request->registerParam(self::REQUEST_PARAM_UPSCALED_ID)->setMD5()->getString();

        if(!$this->collection->idExists($upscaledID)) {
            $this->sendError(
                'The specified upscaled image does not exist.',
                self::ERROR_UPSCALED_IMAGE_NOT_FOUND
            );
        }

        $upscaledImage = $this->collection->getByID($upscaledID);

        $image->prop()->setUpscaledImage($upscaledImage);

        $this->collection->save();

        $this->sendSuccess(
            'Successfully set upscaled image to ['.$upscaledID.'].',
            array(
                self::RESPONSE_UPSCALED_ID => $upscaledID,
                self::RESPONSE_UPSCALED_FAVORITE => $upscaledImage->isFavorite(),
                self::RESPONSE_UPSCALED_FOR_GALLERY => $upscaledImage->isForGallery()
            )
        );
    }
}
