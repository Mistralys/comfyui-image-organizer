<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\ClassHelper;
use AppUtils\FileHelper\FolderInfo;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataRegistry;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataVerifier;
use Mistralys\X4\UI\Console;
use const Mistralys\ComfyUIOrganizer\Config\APP_IMAGE_FOLDER;

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

    public static function updateFileIndex(): void
    {
        self::init();

        ImageIndexer::updateFileIndex();
    }

    /**
     * Read-only census of the sidecars of all PNGs in the image folder.
     *
     * @return bool `false` when a sidecar failed to parse, which Composer reports as a non-zero exit status.
     */
    public static function verifyMetadata(): bool
    {
        self::init();

        Console::header('Verifying image metadata');

        $images = FolderInfo::factory(APP_IMAGE_FOLDER)
            ->createFileFinder()
            ->includeExtension('png')
            ->makeRecursive()
            ->getFiles()
            ->typeANY();

        Console::line1('Found [%d] image files.', count($images));

        $census = new MetadataVerifier(MetadataRegistry::getInstance())->census($images);

        Console::nl();
        Console::line1('Images per format:');

        foreach($census['formats'] as $formatID => $count) {
            Console::line2('%s: %d', $formatID, $count);
        }

        $lists = array(
            'No sidecar file' => $census['noSidecar'],
            'Unrecognized sidecar' => $census['unrecognized'],
            'No model name' => $census['noModelName'],
            'Parse failures' => $census['failures']
        );

        foreach($lists as $label => $names)
        {
            Console::nl();
            Console::line1('%s: %d', $label, count($names));

            foreach($names as $name) {
                Console::line2('%s', $name);
            }
        }

        Console::nl();

        return $census['failures'] === array();
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
