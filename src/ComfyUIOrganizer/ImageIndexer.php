<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\ConvertHelper;
use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\FolderInfo;
use AppUtils\FileHelper\JSONFile;
use AppUtils\ImageHelper;
use AppUtils\Microtime;
use AppUtils\OperationResult_Collection;
use Mistralys\X4\UI\Console;
use function AppUtils\t;

class ImageIndexer
{
    public const int ACTION_UPDATE_PATHS = 180901;
    public const int ACTION_SKIP_NO_CHECKPOINT = 180902;
    public const int ACTION_IMAGE_INDEXED = 180903;

    private OrganizerApp $app;
    private array $images = array();
    private JSONFile $storageFile;

    public function __construct(OrganizerApp $app)
    {
        $this->app = $app;
        $this->storageFile = $this->app->getStorageFile();
        $this->analysisResults = new OperationResult_Collection($this);
    }

    private function loadIndex() : void
    {
        if (!$this->storageFile->exists()) {
            return;
        }

        Console::line1('Found existing image index file, loading data...');
        $this->images = $this->storageFile->getData();
        $this->storageFile->copyTo($this->storageFile->getFolder().'/backup/images-'.Microtime::createNow()->format('Y-m-d-H-i-s').'.json');
    }

    public function findAllImages() : array
    {
        return $this->app->getImageFolder()->createFileFinder()
            ->includeExtension('png')
            ->makeRecursive()
            ->getFiles()
            ->typeANY();
    }

    public function indexAll(): self
    {
        Console::header('Indexing images');

        return $this->analyzeImages($this->findAllImages());
    }

    private OperationResult_Collection $analysisResults;

    /**
     * @param FileInfo[] $files
     * @return $this
     */
    private function analyzeImages(array $files) : self
    {
        $this->loadIndex();

        Console::line1('Processing [%d] image files.', count($files));

        $this->analysisResults = new OperationResult_Collection($this);

        foreach($files as $file)
        {
            $sidecarFile = JSONFile::factory(str_replace('.png', '.json', $file->getPath()));
            if(!$sidecarFile->exists()) {
                Console::line1('Image [%s] | SKIP | No sidecar file found.', $file->getBaseName());
                continue;
            }

            $this->analyzeImage($file, $sidecarFile);
        }

        Console::nl();
        Console::line1('Found [%d] images with sidecar files.', count($this->images));
        Console::line1('Writing image index file...');

        $this->app->getStorageFile()->putData($this->images);

        Console::line1('ALL DONE!');
        Console::nl();

        return $this;
    }

    private function analyzeImage(FileInfo $imageFile, JSONFile $sidecarFile): void
    {
        $baseName = $imageFile->getBaseName();

        Console::line1('Image [%s] | Analyzing...', $baseName);

        $data = $sidecarFile->getData();

        $date = key($data);
        $id = hash_file('md5', $imageFile->getPath());

        // Do not change any images that have already been indexed and modified.
        // BUT: Update the image and sidecar file paths in the existing entry, as they
        // may have been moved or renamed. Since the ID is a file hash, we can
        // safely update the paths without changing the ID.

        if(isset($this->images[$id][ImageInfo::KEY_MODIFIED]) && $this->images[$id][ImageInfo::KEY_MODIFIED] === true)
        {
            $file = $imageFile->getPath();
            $sidecar = $sidecarFile->getPath();
            $folder = $imageFile->getFolder()->getName();

            $updated = false;

            if($this->images[$id][ImageInfo::KEY_IMAGE_FILE] !== $file)
            {
                $this->images[$id][ImageInfo::KEY_IMAGE_FILE] = $file;
                $updated = true;
            }

            if($this->images[$id][ImageInfo::KEY_SIDECAR_FILE] !== $sidecar)
            {
                $this->images[$id][ImageInfo::KEY_SIDECAR_FILE] = $sidecar;
                $updated = true;
            }

            if(empty($this->images[$id][ImageInfo::KEY_PROPERTIES][ImageProperties::KEY_FOLDER_NAME]) || $this->images[$id][ImageInfo::KEY_PROPERTIES][ImageProperties::KEY_FOLDER_NAME] !== $folder)
            {
                $this->images[$id][ImageInfo::KEY_PROPERTIES][ImageProperties::KEY_FOLDER_NAME] = $folder;
                $updated = true;
            }

            if($updated)
            {
                Console::line2('UPDATE | Updated file paths in index.');

                $this->analysisResults->makeNotice(
                    t('Image [%s] | UPDATE | Updated file paths in index.', $imageFile->getName()),
                    self::ACTION_UPDATE_PATHS
                );
            }
            else
            {
                Console::line2('SKIP | Already indexed and modified, no changes.');
            }

            return;
        }

        $properties = $data[$date] ?? array();

        $checkpoint = $properties['checkpoint'] ?? '';
        $text = $properties['custom_text'] ?? '';

        if(empty($checkpoint))
        {
            Console::line2('SKIP | No checkpoint found.');

            $this->analysisResults->makeWarning(
                t('Image [%s] | SKIP | No checkpoint information found.', $imageFile->getName()),
                self::ACTION_SKIP_NO_CHECKPOINT
            );

            return;
        }

        $props = $this->parseText($text);

        $imageParts = ConvertHelper::explodeTrim('-', str_replace('_', '-', $imageFile->getBaseName()));

        $testNr = (int)array_shift($imageParts);
        $imageNr = (int)array_pop($imageParts);
        $seed = '';
        foreach($imageParts as $index => $part) {
            if(str_starts_with($part, 'seed')) {
                $seed = str_replace('seed', '', $part);
                $testName = implode('-', array_slice($imageParts, 0, $index));
            }
        }

        $props[ImageProperties::KEY_FOLDER_NAME] = $imageFile->getFolder()->getName();

        if(!isset($props[ImageProperties::KEY_SEED])) {
            $props[ImageProperties::KEY_SEED] = $seed;
        }

        if(!isset($props[ImageProperties::KEY_IMG_BATCH_NR])) {
            $props[ImageProperties::KEY_IMG_BATCH_NR] = (string)$imageNr;
        }

        if(!isset($props[ImageProperties::KEY_TEST_NUMBER])) {
            $props[ImageProperties::KEY_TEST_NUMBER] = (string)$testNr;
        }

        if(!isset($props[ImageProperties::KEY_TEST_NAME])) {
            $props[ImageProperties::KEY_TEST_NAME] = $testName ?? '';
        }

        $size = ImageHelper::getImageSize($imageFile->getPath());

        $isUpscaled = isset($props['upscaleFactor']) || str_contains('upscale', $imageFile->getBaseName());

        if($isUpscaled === false && ($size->getWidth() >= 2000 || $size->getHeight() >= 2000)) {
            // If the image is larger than 2000x2000 pixels, we assume it is an upscaled image.
            // This is a heuristic to avoid false positives.
            $isUpscaled = true;
        }

        $this->images[$id] = array(
            ImageInfo::KEY_ID => $id,
            ImageInfo::KEY_IMAGE_FILE => $imageFile->getPath(),
            ImageInfo::KEY_SIDECAR_FILE => $sidecarFile->getPath(),
            ImageInfo::KEY_DATE => Microtime::createFromString(str_replace('/', '-', $date))->getISODate(),
            ImageInfo::KEY_CHECKPOINT => $checkpoint,
            ImageInfo::KEY_UPSCALED => $isUpscaled,
            ImageInfo::KEY_IMAGE_SIZE => array('width' => $size->getWidth(), 'height' => $size->getHeight()),
            ImageInfo::KEY_PROPERTIES => $props
        );

        $this->analysisResults->makeSuccess(
            t('Image [%s] | OK | Indexed successfully.', $imageFile->getName()),
            self::ACTION_IMAGE_INDEXED
        );
    }

    public function getAnalysisResults() : OperationResult_Collection
    {
        return $this->analysisResults;
    }

    private function parseText(string $text) : array
    {
        if(empty($text)) {
            return array();
        }

        preg_match_all('/`([^`]*)`/', $text, $matches);

        $punctuation = array(
            '__HYPHEN__' => '-',
            '__COLON__' => ':'
        );

        foreach($matches[1] as $match) {
            $replace = str_replace(array_values($punctuation), array_keys($punctuation), $match);
            $text = str_replace($match, $replace, $text);
        }

        $properties = ConvertHelper::explodeTrim('-', $text);

        $result = array();
        foreach($properties as $propertyString) {
            $parts = explode(':', $propertyString);
            $name = $parts[0] ?? '';
            $value = $parts[1] ?? '';
            $result[$name] = str_replace(array_keys($punctuation), array_values($punctuation), trim($value, '`'));
        }

        return $result;
    }

    public function detectUpscaledInFolder(string $folderName) : self
    {
        return $this->detectUpscaledImages($folderName);
    }

    public function detectUpscaledImages(?string $folderName=null) : self
    {
        Console::header('Upscaled images detection');

        $collection = new ImageCollection($this->app->getStorageFile());

        foreach($this->detectSettingHashes($collection, $folderName) as $hash => $images)
        {
            // We can only handle pairs of images: One regular and one upscaled.
            // Other more complex cases must be handled manually, as the choice
            // is not obvious.
            if(count($images) !== 2) {
                continue;
            }

            Console::line1('Hash match', $hash);

            $regular = null;
            $upscaled = null;

            foreach($images as $image) {
                if(!$image->isUpscaled()) {
                    $regular = $image;
                } else {
                    $upscaled = $image;
                }

                Console::line2(
                    'Image ID [%s] | Size [%dx%d] | Upscaled [%s] | Facefix [%s]',
                    $image->getImageFile()->getBaseName(),
                    $image->getImageSize()['width'], $image->getImageSize()['height'],
                    ConvertHelper::boolStrict2string($image->isUpscaled(), true),
                    ConvertHelper::boolStrict2string($image->prop()->isFacefix(), true)
                );
            }

            if($regular && $upscaled)
            {
                Console::line2('OK | Linking upscaled image [%s] to regular image [%s].', $upscaled->getImageFile()->getBaseName(), $regular->getImageFile()->getBaseName());
                $regular->prop()->setUpscaledImage($upscaled);
            } else {
                Console::line2('SKIP | Could not determine which image is the upscaled one, skipping.');
            }
        }

        $collection->save();

        return $this;
    }

    /**
     * Groups images by their settings hash.
     * @return array<string,ImageInfo[]>
     */
    private function detectSettingHashes(ImageCollection $collection, ?string $folderName=null) : array
    {
        $hashes = array();

        foreach($collection->getAll() as $image)
        {
            if($folderName !== null && $image->prop()->getFolderName() !== $folderName) {
                continue;
            }

            // Skip images that are already marked as upscaled or already have an upscaled image assigned.
            if($image->prop()->getUpscaledImage() !== null) {
                continue;
            }

            $hash = $image->getSettingsHash();
            if(!isset($hashes[$hash])) {
                $hashes[$hash] = array();
            }

            $hashes[$hash][] = $image;
        }

        Console::line1('Found [%d] unique image settings hashes.', count($hashes));

        return $hashes;
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

    public function findFolderImages(string $folderName) : array
    {
        $folder = FolderInfo::factory($this->app->getImageFolder().'/'.$folderName);

        if(!$folder->exists()) {
            return array();
        }

        return $folder->createFileFinder()
            ->includeExtension('png')
            ->getFiles()
            ->typeANY();
    }

    public function indexFolder(string $folderName) : self
    {
        return $this->analyzeImages($this->findFolderImages($folderName));
    }
}
