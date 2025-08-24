<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\ClassHelper;
use Mistralys\X4\UI\Console;

class Builder
{
    public static function build(): void
    {
        self::init();

        new ImageIndexer(OrganizerApp::create())
            ->indexAll()
            ->detectUpscaledImages()
            ->cleanUpFolders();
    }

    public static function index(): void
    {
        self::init();

        new ImageIndexer(OrganizerApp::create())
            ->indexAll()
            ->cleanUpFolders();
    }

    public static function detectUpscaled(): void
    {
        self::init();

        new ImageIndexer(OrganizerApp::create())
            ->detectUpscaledImages();
    }

    public static function cleanFolders(): void
    {
        self::init();

        new ImageIndexer(OrganizerApp::create())
            ->cleanUpFolders();
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
