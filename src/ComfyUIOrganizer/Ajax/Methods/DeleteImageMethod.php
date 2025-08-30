<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Ajax\Methods;

use Mistralys\ComfyUIOrganizer\Ajax\BaseImageMethod;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\OrganizerApp;

/**
 * @property OrganizerApp $application
 */
class DeleteImageMethod extends BaseImageMethod
{
    public const string METHOD_NAME = 'DeleteImage';

    public function getID(): string
    {
        return self::METHOD_NAME;
    }

    protected function processImage(ImageInfo $image): never
    {
        $this->collection->deleteImage($image);

        // Using this method as the image method sendSuccess() will try
        // to get data of the deleted image.
        $this->methods->sendSuccess('Image deleted successfully.');
    }
}
