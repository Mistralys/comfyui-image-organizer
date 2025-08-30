<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Ajax;

use Mistralys\ComfyUIOrganizer\ImageCollection;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\OrganizerApp;
use Mistralys\X4\UI\Ajax\BaseAjaxMethod;

/**
 * @property OrganizerApp $application
 */
abstract class BaseImageMethod extends BaseAjaxMethod
{
    public const int ERROR_IMAGE_NOT_FOUND = 179301;
    public const string REQUEST_PARAM_FOR_GALLERY = 'forGallery';
    public const string REQUEST_PARAM_FOR_WEBSITE = 'forWebsite';
    public const string REQUEST_PARAM_FAVORITE = 'favorite';

    protected ImageCollection $collection;

    protected function init(): void
    {
    }

    protected function _process(): never
    {
        $image = $this->collection->getFromRequest();
        if($image === null) {
            $this->sendError('Image not found or not specified.', self::ERROR_IMAGE_NOT_FOUND);
        }

        $this->processImage($image);
    }

    protected function getImage() : ImageInfo
    {
        $image = $this->collection->getFromRequest();
        if($image !== null) {
            return $image;
        }

        $this->sendError('Image not found or not specified.', self::ERROR_IMAGE_NOT_FOUND);
    }

    abstract protected function processImage(ImageInfo $image): never;

    protected function preProcess(): void
    {
        $this->collection = $this->application->createImageCollection();
    }

    protected function sendSuccess(string $message = '', array $payload = array()): never
    {
        $image = $this->getImage();

        $payload[BaseImageMethod::REQUEST_PARAM_FOR_GALLERY] = $image->prop()->isForGallery();
        $payload[BaseImageMethod::REQUEST_PARAM_FOR_WEBSITE] = $image->prop()->isForWebsite();
        $payload[BaseImageMethod::REQUEST_PARAM_FAVORITE] = $image->prop()->isFavorite();

        parent::sendSuccess($message, $payload);
    }
}
