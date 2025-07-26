<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\ConvertHelper;
use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\JSONFile;
use AppUtils\ImageHelper;
use AppUtils\Microtime;
use Mistralys\X4\UI\Console;

class ImageIndexer
{
    private OrganizerApp $app;
    private array $images = array();
    private JSONFile $storageFile;

    public function __construct(OrganizerApp $app)
    {
        $this->app = $app;
        $this->storageFile = $this->app->getStorageFile();
    }

    public function indexImages(): void
    {
        Console::header('Indexing images');

        if ($this->storageFile->exists()) {
            Console::line1('Found existing image index file, loading data...');
            $this->images = $this->storageFile->getData();
        }

        $files = $this->app->getImageFolder()->createFileFinder()
            ->includeExtension('png')
            ->makeRecursive()
            ->getFiles()
            ->typeANY();

        Console::line1('Found [%d] image files.', count($files));

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
    }

    private function analyzeImage(FileInfo $imageFile, JSONFile $sidecarFile): void
    {
        $baseName = $imageFile->getBaseName();

        Console::line1('Image [%s] | Analyzing...', $baseName);

        $data = $sidecarFile->getData();

        $date = key($data);
        $id = md5($imageFile->getPath().'/'.$date);

        // Do not change any images that have already been indexed and modified.
        if(isset($this->images[$id][ImageInfo::KEY_MODIFIED]) && $this->images[$id][ImageInfo::KEY_MODIFIED] === true) {
            Console::line2('SKIP | Already indexed and modified.');
            return;
        }

        $properties = $data[$date] ?? array();

        $checkpoint = $properties['checkpoint'] ?? '';
        $text = $properties['custom_text'] ?? '';

        if(empty($checkpoint)) {
            Console::line2('SKIP | No checkpoint found.');
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

        if(!isset($props[ImageProperties::KEY_FOLDER_NAME])) {
            $props[ImageProperties::KEY_FOLDER_NAME] = $imageFile->getFolder()->getName();
        }

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

        $this->images[$id] = array(
            ImageInfo::KEY_ID => md5($imageFile->getPath().'/'.$date),
            ImageInfo::KEY_IMAGE_FILE => $imageFile->getPath(),
            ImageInfo::KEY_SIDECAR_FILE => $sidecarFile->getPath(),
            ImageInfo::KEY_DATE => Microtime::createFromString(str_replace('/', '-', $date))->getISODate(),
            ImageInfo::KEY_CHECKPOINT => $checkpoint,
            ImageInfo::KEY_UPSCALED => isset($props['upscaleFactor']) || str_contains('upscale', $imageFile->getBaseName()),
            ImageInfo::KEY_IMAGE_SIZE => array('width' => $size->getWidth(), 'height' => $size->getHeight()),
            ImageInfo::KEY_PROPERTIES => $props
        );
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
}
