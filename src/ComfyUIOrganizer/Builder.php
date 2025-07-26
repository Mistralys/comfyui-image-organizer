<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use const Mistralys\ComfyUIOrganizer\Config\APP_IMAGE_FOLDER;

class Builder
{
    public static function build() : void
    {
        require_once __DIR__.'/../../vendor/autoload.php';
        require_once __DIR__.'/../../config.php';

        $app = new OrganizerApp(APP_IMAGE_FOLDER);
        new ImageIndexer($app)->indexImages();
    }
}
