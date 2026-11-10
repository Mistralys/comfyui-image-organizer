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
            ->cleanUpFolders();
    }

    public static function index(): void
    {
        self::init();

        new ImageIndexer(OrganizerApp::create())
            ->indexAll()
            ->cleanUpFolders();
    }

    public static function cleanFolders(): void
    {
        self::init();

        new ImageIndexer(OrganizerApp::create())
            ->cleanUpFolders();
    }

    /**
     * One-off, with {@see UpscaledPairsMigration}: reports what the migration would do and changes nothing.
     */
    public static function migrateUpscaled(): void
    {
        self::runUpscaledMigration(false);
    }

    /**
     * One-off, with {@see UpscaledPairsMigration}: converts the old upscaled pairs and refreshes the file index.
     */
    public static function migrateUpscaledApply(): void
    {
        self::runUpscaledMigration(true);
    }

    private static function runUpscaledMigration(bool $apply): void
    {
        self::init();

        Console::header($apply ? 'Migrating upscaled image pairs' : 'Migrating upscaled image pairs (dry run)');

        $result = new UpscaledPairsMigration(OrganizerApp::create()->getStorageFile())->run($apply);

        Console::nl();
        Console::line1('Migrated: %d | Skipped: %d | Stale links dropped: %d', $result['migrated'], $result['skipped'], $result['staleLinks']);

        if(!$apply) {
            Console::line1('Dry run, nothing was changed. Use `composer migrate-upscaled-apply` to apply.');
            return;
        }

        ImageIndexer::updateFileIndex();
    }

    public static function updateFileIndex(): void
    {
        self::init();

        ImageIndexer::updateFileIndex();
    }

    /**
     * Read-only census of the sidecars of all PNGs in the image folder, found without
     * `OrganizerApp::create()` or any access to `data/`.
     *
     * @return bool Must stay `bool`: Composer maps a script callable's `false` to exit status 1 and ignores an
     *              integer. `false` when a sidecar failed to parse.
     */
    public static function verifyMetadata(): bool
    {
        self::init();

        Console::header('Verifying image metadata');

        $images = ImageIndexer::filterOriginals(FolderInfo::factory(APP_IMAGE_FOLDER)
            ->createFileFinder()
            ->includeExtension('png')
            ->makeRecursive()
            ->getFiles()
            ->typeANY());

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
