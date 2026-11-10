<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\FolderInfo;
use AppUtils\FileHelper\JSONFile;
use AppUtils\Microtime;
use AppUtils\OperationResult_Collection;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataRegistry;
use Mistralys\X4\UI\Console;
use function AppUtils\t;

/**
 * Orchestrates indexing runs. It reads no sidecar file itself: each PNG goes
 * through {@see MetadataRegistry::resolve()} and the resulting sidecar to
 * {@see ImageAnalyzer}, which holds all per-image logic.
 */
class ImageIndexer
{
    // The ACTION_SKIP_* codes are reported as warnings, so that countSuccesses() and
    // countNotices(), read by ImageBrowser::handleRefreshFolder(), count only indexed and updated images.
    // 180904 (ACTION_SKIP_NO_DATE) is retired and stays unassigned; MetadataExceptionTest pins it.
    public const int ACTION_UPDATE_PATHS = 180901;
    public const int ACTION_SKIP_NO_CHECKPOINT = 180902;
    public const int ACTION_IMAGE_INDEXED = 180903;
    public const int ACTION_SKIP_UNRECOGNIZED_SIDECAR = 180905;
    public const int ACTION_SKIP_INVALID_SIDECAR = 180906;
    public const string INDEX_THUMBNAIL_FILE = 'thumbnailFile';
    public const string INDEX_IMAGE_FILE = 'imageFile';

    private OrganizerApp $app;
    private ImageIndex $images;
    private JSONFile $storageFile;
    private MetadataRegistry $registry;

    public function __construct(OrganizerApp $app)
    {
        $this->app = $app;
        $this->registry = MetadataRegistry::getInstance();
        $this->images = new ImageIndex();
        $this->storageFile = $this->app->getStorageFile();
        $this->analysisResults = new OperationResult_Collection($this);
    }

    /**
     * Rebuilds `data/file_index.json`, which `image.php` resolves every request through.
     * Moves and deletes in the UI update it themselves; this run repairs any drift.
     */
    public static function updateFileIndex(): void
    {
        Console::header('Updating image index file');

        $collection = OrganizerApp::create()->createImageCollection();

        Console::line1('Writing index file with [%d] entries.', count($collection->getAll()));

        $collection->writeFileIndex();

        Console::line1('ALL DONE!');
        Console::nl();
    }

    private function loadIndex() : void
    {
        if (!$this->storageFile->exists()) {
            return;
        }

        Console::line1('Found existing image index file, loading data...');

        // Backup before the run rewrites the index; this step must stay.
        $this->storageFile->copyTo($this->storageFile->getFolder().'/backup/images-'.Microtime::createNow()->format('Y-m-d-H-i-s').'.json');
        $this->images = ImageIndex::load($this->storageFile);
    }

    /**
     * @return FileInfo[]
     */
    public function findAllImages() : array
    {
        return self::filterOriginals($this->app->getImageFolder()->createFileFinder()
            ->includeExtension('png')
            ->makeRecursive()
            ->getFiles()
            ->typeANY());
    }

    /**
     * Drops the `_upscaled.png` versions, which belong to an image and are never indexed themselves.
     *
     * @param FileInfo[] $files
     * @return FileInfo[]
     */
    public static function filterOriginals(array $files) : array
    {
        return array_values(array_filter(
            $files,
            static fn(FileInfo $file) : bool => !ImageInfo::isUpscaledFileName($file->getName())
        ));
    }

    public function indexAll(): self
    {
        Console::header('Indexing images');

        return $this->analyzeImages($this->findAllImages());
    }

    private OperationResult_Collection $analysisResults;

    /**
     * An image without any sidecar file is skipped with a console line only, and no result entry.
     * One whose candidate files are all unrecognized also gets a warning.
     * A resolved sidecar wins, even when other candidate files beside the image go unrecognized.
     *
     * @param FileInfo[] $files
     * @return $this
     */
    private function analyzeImages(array $files) : self
    {
        $this->loadIndex();

        Console::line1('Processing [%d] image files.', count($files));

        $this->analysisResults = new OperationResult_Collection($this);

        $analyzer = new ImageAnalyzer($this->images, $this->analysisResults);

        foreach($files as $file)
        {
            $resolution = $this->registry->resolve($file);
            $sidecar = $resolution->getSidecar();

            if($sidecar === null)
            {
                if($resolution->getUnrecognizedCandidates() !== array())
                {
                    Console::line1('Image [%s] | SKIP | Unrecognized sidecar file.', $file->getBaseName());

                    $this->analysisResults->makeWarning(
                        t('Image [%s] | SKIP | The sidecar file is not recognized.', $file->getName()),
                        self::ACTION_SKIP_UNRECOGNIZED_SIDECAR
                    );

                    continue;
                }

                Console::line1('Image [%s] | SKIP | No sidecar file found.', $file->getBaseName());
                continue;
            }

            $analyzer->analyze($file, $sidecar);
        }

        Console::nl();
        Console::line1('Found [%d] images with sidecar files.', $this->images->count());
        Console::line1('Writing image index file...');

        $this->images->save($this->app->getStorageFile());
        $this->app->resetImageCollection();

        self::updateFileIndex();

        Console::line1('ALL DONE!');
        Console::nl();

        return $this;
    }

    public function getAnalysisResults() : OperationResult_Collection
    {
        return $this->analysisResults;
    }

    public function cleanUpFolders() : self
    {
        Console::header('Cleaning up empty folders');

        foreach($this->app->getImageFolder()->getSubFolders() as $folder)
        {
            if($folder->isEmpty()) {
                Console::line1('Removing empty folder [%s].', $folder->getName());
                $folder->delete();
            }
        }

        Console::line1('ALL DONE!');
        Console::nl();

        return $this;
    }

    /**
     * @return FileInfo[]
     */
    public function findFolderImages(string $folderName) : array
    {
        $folder = FolderInfo::factory($this->app->getImageFolder().'/'.$folderName);

        if(!$folder->exists()) {
            return array();
        }

        return self::filterOriginals($folder->createFileFinder()
            ->includeExtension('png')
            ->getFiles()
            ->typeANY());
    }

    public function indexFolder(string $folderName) : self
    {
        return $this->analyzeImages($this->findFolderImages($folderName));
    }

    /**
     * Indexes only the image subfolders that have no image in the index yet.
     * A folder whose images were all skipped stays "new" and is scanned again.
     */
    public function indexNewFolders() : self
    {
        Console::header('Discovering new folders');

        $subFolderNames = array();

        foreach($this->app->getImageFolder()->getSubFolders() as $folder) {
            $subFolderNames[] = $folder->getName();
        }

        $files = array();

        foreach(self::filterNewFolders($subFolderNames, $this->loadStoredIndex()) as $name)
        {
            Console::line1('Found new folder [%s].', $name);

            foreach($this->findFolderImages($name) as $file) {
                $files[] = $file;
            }
        }

        if($files === array()) {
            Console::line1('No new folders with images found.');
            Console::nl();
            return $this;
        }

        return $this->analyzeImages($files);
    }

    /**
     * @param string[] $subFolderNames
     * @return string[] The names without an entry in the index.
     */
    public static function filterNewFolders(array $subFolderNames, ImageIndex $index) : array
    {
        return array_values(array_diff($subFolderNames, $index->getFolderNames()));
    }

    private function loadStoredIndex() : ImageIndex
    {
        if(!$this->storageFile->exists()) {
            return new ImageIndex();
        }

        return ImageIndex::load($this->storageFile);
    }
}
