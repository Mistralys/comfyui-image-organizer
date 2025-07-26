<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Ajax;

use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\OrganizerApp;

/**
 * @property OrganizerApp $application
 */
class FavoriteImageMethod extends BaseImageMethod
{
    public const string METHOD_NAME = 'FavoriteImage';

    public function getID(): string
    {
        return self::METHOD_NAME;
    }

    protected function processImage(ImageInfo $image): never
    {
        $image->setFavorite(true)->save();

        $this->sendSuccess('Image deleted successfully.');
    }
}
