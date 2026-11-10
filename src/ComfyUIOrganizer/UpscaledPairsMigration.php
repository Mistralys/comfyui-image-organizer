<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\FolderInfo;
use AppUtils\FileHelper\JSONFile;
use AppUtils\Microtime;
use Mistralys\X4\UI\Console;

/**
 * One-off conversion of the retired index-linked regular/upscaled pairs to the
 * `_upscaled.png` sibling convention (see {@see ImageInfo::resolveUpscaledPath()}).
 *
 * Per pair, the upscaled PNG and its PSD, JPG and website image are copied next to the
 * regular image under the sibling names, the link and the upscaled entry leave the index,
 * and the old upscaled files are deleted only after the index was saved. A run can be
 * repeated after an interruption.
 *
 * Delete this class, its test and the `migrate-upscaled*` Composer scripts once it has run.
 */
class UpscaledPairsMigration
{
    // The retired ImageProperties::KEY_UPSCALED_IMAGE.
    private const string KEY_UPSCALED_IMAGE = 'upscaledImage';

    private JSONFile $storageFile;
    private ImageIndex $index;

    public function __construct(JSONFile $storageFile)
    {
        $this->storageFile = $storageFile;
        $this->index = ImageIndex::load($storageFile);
    }

    /**
     * @param bool $apply Without it, the run only reports what it would do and writes nothing.
     * @return array{migrated:int, skipped:int, staleLinks:int}
     */
    public function run(bool $apply) : array
    {
        $result = array('migrated' => 0, 'skipped' => 0, 'staleLinks' => 0);

        /** @var array<string,FileInfo> $obsolete */
        $obsolete = array();

        /** @var string[] $consumed */
        $consumed = array();

        foreach($this->index->getEntries() as $entry)
        {
            $targetID = $entry->getProperties()[self::KEY_UPSCALED_IMAGE] ?? '';

            if(!is_string($targetID) || $targetID === '') {
                continue;
            }

            $properties = $entry->getProperties();
            unset($properties[self::KEY_UPSCALED_IMAGE]);

            $name = basename($entry->getImageFile());
            $target = $this->index->get($targetID);

            if($target === null)
            {
                Console::line1('Image [%s] | STALE | The linked upscaled image is not indexed, dropping the link.', $name);
                $result['staleLinks']++;
                $this->replaceProperties($entry, $properties, $entry->isModified());
                continue;
            }

            $regular = FileInfo::factory($entry->getImageFile());
            $source = FileInfo::factory($target->getImageFile());
            $destination = FileInfo::factory(ImageInfo::resolveUpscaledPath($regular->getPath()));

            if(!$regular->exists() || !$source->exists()) {
                Console::line1('Image [%s] | SKIP | The regular or the upscaled image file is missing.', $name);
                $result['skipped']++;
                continue;
            }

            if(!self::canCopy($source, $destination)) {
                Console::line1('Image [%s] | SKIP | A different file exists at [%s].', $name, $destination->getName());
                $result['skipped']++;
                continue;
            }

            Console::line1('Image [%s] | MIGRATE | [%s] becomes [%s].', $name, $source->getName(), $destination->getName());

            $copies = array(array($source, $destination));
            $obsolete[$source->getPath()] = $source;
            $obsolete[$target->getSidecarFile()] = FileInfo::factory($target->getSidecarFile());

            $siblingDestinations = self::siblingPaths($regular->getPath());

            foreach(self::siblingPaths($source->getPath()) as $type => $siblingSource)
            {
                $from = FileInfo::factory($siblingSource);

                if(!$from->exists()) {
                    continue;
                }

                $to = FileInfo::factory($siblingDestinations[$type]);

                if(!self::canCopy($from, $to)) {
                    Console::line2('KEEP | The [%s] file of the upscaled image stays, a different one exists for the regular image.', $type);
                    continue;
                }

                Console::line2('Moving the [%s] file.', $type);
                $copies[] = array($from, $to);
                $obsolete[$from->getPath()] = $from;
            }

            if($apply)
            {
                foreach($copies as list($from, $to))
                {
                    if(!$to->exists()) {
                        $from->copyTo($to);
                    }
                }
            }

            $modified = $entry->isModified();
            $targetWebsiteID = $target->getProperties()[ImageProperties::KEY_WEBSITE_IMAGE_ID] ?? '';

            if(empty($properties[ImageProperties::KEY_WEBSITE_IMAGE_ID]) && is_string($targetWebsiteID) && $targetWebsiteID !== '')
            {
                $properties[ImageProperties::KEY_WEBSITE_IMAGE_ID] = $targetWebsiteID;
                $modified = true;
            }

            $this->replaceProperties($entry, $properties, $modified);
            $consumed[] = $targetID;
            $result['migrated']++;
        }

        if($apply && ($result['migrated'] > 0 || $result['staleLinks'] > 0))
        {
            foreach($consumed as $id) {
                $this->index->remove($id);
            }

            $this->backupIndex();
            $this->index->save($this->storageFile);

            foreach($obsolete as $file)
            {
                if($file->exists()) {
                    $file->delete();
                }
            }
        }

        return $result;
    }

    /**
     * @param array<int|string,mixed> $properties
     */
    private function replaceProperties(ImageIndexEntry $entry, array $properties, bool $modified) : void
    {
        $this->index->set(new ImageIndexEntry(
            $entry->getID(),
            $entry->getImageFile(),
            $entry->getSidecarFile(),
            $entry->getDate(),
            $entry->getCheckpoint(),
            $modified,
            $entry->getLabel(),
            $entry->getImageSize(),
            $properties,
            $entry->getExtra()
        ));
    }

    private function backupIndex() : void
    {
        $folder = FolderInfo::factory($this->storageFile->getFolder().'/backup');
        $folder->create();

        $this->storageFile->copyTo(sprintf(
            '%s/images-%s-before-upscaled-migration.json',
            $folder->getPath(),
            Microtime::createNow()->format('Y-m-d-H-i-s')
        ));
    }

    /**
     * Whether the copy may go ahead: the target is free, or already holds the same content
     * from an earlier interrupted run.
     */
    private static function canCopy(FileInfo $source, FileInfo $target) : bool
    {
        return !$target->exists() || md5_file($source->getPath()) === md5_file($target->getPath());
    }

    /**
     * The paths mirror {@see ImageInfo::getPSDFile()}, {@see ImageInfo::getJPGFile()} and {@see ImageInfo::getWebsiteImage()}.
     *
     * @return array{psd:string, jpg:string, website:string}
     */
    private static function siblingPaths(string $pngPath) : array
    {
        return array(
            'psd' => str_replace('.png', '.psd', $pngPath),
            'jpg' => str_replace('.png', '.jpg', $pngPath),
            'website' => str_replace('.png', '_websiteImage.png', str_replace('_.png', '.png', $pngPath))
        );
    }
}
