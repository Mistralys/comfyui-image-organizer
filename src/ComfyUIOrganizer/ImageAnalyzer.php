<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\ConvertHelper;
use AppUtils\FileHelper\FileInfo;
use AppUtils\ImageHelper;
use AppUtils\Microtime;
use AppUtils\OperationResult_Collection;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataException;
use Mistralys\ComfyUIOrganizer\Metadata\ResolvedSidecar;
use Mistralys\X4\UI\Console;
use function AppUtils\t;

/**
 * Decides, per image, what the indexer does with it: keeps a modified
 * entry (syncing its location only), skips an unusable sidecar, or
 * builds a fresh entry from the parsed metadata.
 *
 * It works on the index and the result collection it is given and does
 * not touch the application, the storage file or the `data/` folder.
 */
class ImageAnalyzer
{
    public function __construct(
        private readonly ImageIndex $images,
        private readonly OperationResult_Collection $results
    )
    {
    }

    /**
     * @param FileInfo $imageFile
     * @param ResolvedSidecar $sidecar
     * @return void
     * @throws OrganizerException {@see OrganizerException::ERROR_CANNOT_HASH_IMAGE_FILE}
     */
    public function analyze(FileInfo $imageFile, ResolvedSidecar $sidecar) : void
    {
        Console::line1('Image [%s] | Analyzing...', $imageFile->getBaseName());

        $id = $this->hashImage($imageFile);
        $sidecarPath = $sidecar->getSidecar()->getPath();

        // Do not change any images that have already been indexed and modified.
        // BUT: Update the image and sidecar file paths in the existing entry, as they
        // may have been moved or renamed. Since the ID is a file hash, we can
        // safely update the paths without changing the ID.

        $stored = $this->images->get($id);

        if($stored !== null && $stored->isModified())
        {
            $updated = $stored->syncLocation(
                $imageFile->getPath(),
                $sidecarPath,
                self::resolveFolderName($imageFile)
            );

            if($updated)
            {
                Console::line2('UPDATE | Updated file paths in index.');

                $this->results->makeNotice(
                    t('Image [%s] | UPDATE | Updated file paths in index.', $imageFile->getName()),
                    ImageIndexer::ACTION_UPDATE_PATHS
                );
            }
            else
            {
                Console::line2('SKIP | Already indexed and modified, no changes.');
            }

            return;
        }

        try
        {
            $metadata = $sidecar->getFormat()->parse($sidecar->getData());
        }
        catch (MetadataException $e)
        {
            // Neither the exception text nor the sidecar content goes into the messages.
            if($e->getCode() === MetadataException::ERROR_NO_MODEL_NAME)
            {
                Console::line2('SKIP | No checkpoint found.');

                $this->results->makeWarning(
                    t('Image [%s] | SKIP | No checkpoint information found.', $imageFile->getName()),
                    ImageIndexer::ACTION_SKIP_NO_CHECKPOINT
                );

                return;
            }

            Console::line2('SKIP | Invalid sidecar file.');

            $this->results->makeWarning(
                t('Image [%s] | SKIP | The sidecar file is invalid.', $imageFile->getName()),
                ImageIndexer::ACTION_SKIP_INVALID_SIDECAR
            );

            return;
        }

        $props = self::completeProperties($metadata->getIndexProperties(), $imageFile);

        $size = ImageHelper::getImageSize($imageFile->getPath());

        $isUpscaled = isset($props['upscaleFactor']) || str_contains('upscale', $imageFile->getBaseName());

        if($isUpscaled === false && ($size->getWidth() >= 2000 || $size->getHeight() >= 2000)) {
            // If the image is larger than 2000x2000 pixels, we assume it is an upscaled image.
            // This is a heuristic to avoid false positives.
            $isUpscaled = true;
        }

        $generated = $metadata->getDate();

        if($generated !== null) {
            $date = $generated->getISODate();
        } elseif($stored !== null) {
            // A moved image is copied without its modification time, so the stored date is kept.
            $date = $stored->getDate();
        } else {
            $modified = $imageFile->getModifiedDate();
            $date = ($modified !== null ? Microtime::createFromDate($modified) : Microtime::createNow())->getISODate();
        }

        $new = new ImageIndexEntry(
            $id,
            $imageFile->getPath(),
            $sidecarPath,
            $date,
            $metadata->getModelName() ?? '',
            $isUpscaled,
            false,
            '',
            array('width' => $size->getWidth(), 'height' => $size->getHeight()),
            $props
        );

        // Keep the unknown keys of an already stored entry.
        $this->images->set($stored !== null ? $stored->withKnownFieldsFrom($new) : $new);

        $this->results->makeSuccess(
            t('Image [%s] | OK | Indexed successfully.', $imageFile->getName()),
            ImageIndexer::ACTION_IMAGE_INDEXED
        );
    }

    /**
     * Sets the folder name from the parent folder and fills the seed, batch
     * number, test number and test name from the file name, where the bag
     * does not have them.
     *
     * @param array<string,string> $bag
     * @param FileInfo $imageFile
     * @return array<string,string>
     */
    public static function completeProperties(array $bag, FileInfo $imageFile) : array
    {
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

        $bag[ImageProperties::KEY_FOLDER_NAME] = self::resolveFolderName($imageFile);

        if(!isset($bag[ImageProperties::KEY_SEED])) {
            $bag[ImageProperties::KEY_SEED] = $seed;
        }

        if(!isset($bag[ImageProperties::KEY_IMG_BATCH_NR])) {
            $bag[ImageProperties::KEY_IMG_BATCH_NR] = (string)$imageNr;
        }

        if(!isset($bag[ImageProperties::KEY_TEST_NUMBER])) {
            $bag[ImageProperties::KEY_TEST_NUMBER] = (string)$testNr;
        }

        if(!isset($bag[ImageProperties::KEY_TEST_NAME])) {
            $bag[ImageProperties::KEY_TEST_NAME] = $testName ?? '';
        }

        return $bag;
    }

    /**
     * Not {@see FileInfo::getFolder()}: FolderInfo rejects a folder name that looks like a file name, e.g. `SD1.5`.
     */
    private static function resolveFolderName(FileInfo $imageFile) : string
    {
        return basename(dirname($imageFile->getPath()));
    }

    /**
     * @param FileInfo $imageFile
     * @return string
     * @throws OrganizerException
     */
    private function hashImage(FileInfo $imageFile) : string
    {
        // hash_file() warns on an unreadable file; the exception below reports it.
        set_error_handler(static fn() : bool => true);

        try
        {
            $id = hash_file('md5', $imageFile->getPath());
        }
        finally
        {
            restore_error_handler();
        }

        if($id === false) {
            throw new OrganizerException(
                'Cannot hash the image file.',
                sprintf('Could not compute the MD5 hash of the image file [%s].', $imageFile->getPath()),
                OrganizerException::ERROR_CANNOT_HASH_IMAGE_FILE
            );
        }

        return $id;
    }
}
