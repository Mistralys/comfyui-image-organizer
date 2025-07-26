<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Ajax;

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

        $this->sendSuccess('Image deleted successfully.');
    }
}
