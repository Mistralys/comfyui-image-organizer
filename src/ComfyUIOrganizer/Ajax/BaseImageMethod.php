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

    abstract protected function processImage(ImageInfo $image): never;

    protected function preProcess(): void
    {
        $this->collection = $this->application->createImageCollection();
    }
}
