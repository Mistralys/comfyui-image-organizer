<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Ajax\Methods;

use Mistralys\ComfyUIOrganizer\Ajax\BaseImageMethod;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\OrganizerApp;

/**
 * @property OrganizerApp $application
 */
class CopyToOutputMethod extends BaseImageMethod
{
    public const string METHOD_NAME = 'CopyToOutput';

    public function getID(): string
    {
        return self::METHOD_NAME;
    }

    protected function processImage(ImageInfo $image): never
    {
        $image->copyToOutput();

        $this->sendSuccess('Image copied successfully.');
    }
}
