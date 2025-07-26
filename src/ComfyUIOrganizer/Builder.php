<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\ClassHelper;
use AppUtils\ClassHelper\Repository\ClassRepository;
use Mistralys\X4\UI\Console;
use const Mistralys\ComfyUIOrganizer\Config\APP_IMAGE_FOLDER;

class Builder
{
    public static function build(): void
    {
        self::init();

        new ImageIndexer(OrganizerApp::create())->indexImages();
    }

    public static function postAutoload(): void
    {
        self::init();

        Console::line1('Clearing class cache.');

        ClassHelper::getRepositoryManager()->clearCache();
    }

    private static bool $initialized = false;

    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        require_once __DIR__ . '/../../prepend.php';
    }
}
