<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata;

use AppUtils\ArrayDataCollection;
use AppUtils\ClassHelper;
use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\FolderInfo;
use AppUtils\FileHelper\JSONFile;
use AppUtils\FileHelper_Exception;
use JsonException;

/**
 * Knows all sidecar formats and finds the sidecar of an image
 * by recognizing the content of its candidate files.
 *
 * Formats are the classes in `Metadata/Formats/`, found by an
 * uncached folder scan, so that the registry works without the
 * class cache folder that only `prepend.php` sets up.
 *
 * The `Metadata/` module depends on nothing from the indexer, the browser or `ImageInfo`; it uses
 * only `AppUtils`, `OrganizerException` and the `ImageProperties` key constants (see `MetadataModuleDependenciesTest`).
 */
class MetadataRegistry
{
    private static ?self $instance = null;

    /**
     * @var SidecarFormatInterface[]
     */
    private array $formats;

    /**
     * @param SidecarFormatInterface[] $formats
     */
    private function __construct(array $formats)
    {
        usort(
            $formats,
            static fn(SidecarFormatInterface $a, SidecarFormatInterface $b) : int => $a->getPriority() <=> $b->getPriority()
        );

        $this->formats = $formats;
    }

    /**
     * Scans `Metadata/Formats/` for the format classes. A new format needs no registration; run
     * `composer dump-autoload` so that the classmap holds it. A class there that does not implement
     * the interface is silently ignored, so it would never become a format.
     *
     * @return self
     */
    public static function create() : self
    {
        $formats = array();

        $classes = ClassHelper::findClassesInFolder(
            FolderInfo::factory(__DIR__.'/Formats'),
            false,
            SidecarFormatInterface::class
        );

        foreach($classes as $class)
        {
            $formats[] = ClassHelper::requireObjectInstanceOf(
                SidecarFormatInterface::class,
                new ($class->getNameNS())()
            );
        }

        return new self($formats);
    }

    public static function getInstance() : self
    {
        if(self::$instance === null) {
            self::$instance = self::create();
        }

        return self::$instance;
    }

    /**
     * Builds a registry over exactly the given formats, without scanning.
     *
     * @param SidecarFormatInterface[] $formats
     * @return self
     */
    public static function createWithFormats(array $formats) : self
    {
        return new self($formats);
    }

    /**
     * @return SidecarFormatInterface[] In priority order.
     */
    public function getFormats() : array
    {
        return $this->formats;
    }

    /**
     * Walks all formats in priority order and accepts a candidate
     * only when the format recognizes its content. Candidates are
     * read once and the walk continues after a match, so that the
     * existing candidates nobody recognized are reported as well.
     */
    public function resolve(FileInfo $image) : SidecarResolution
    {
        $resolved = null;
        $recognized = array();
        $existing = array();
        $contents = array();

        foreach($this->formats as $format)
        {
            foreach($format->getSidecarCandidates($image) as $path)
            {
                $file = JSONFile::factory($path);

                if(!$file->exists()) {
                    continue;
                }

                $key = $file->getPath();

                if(!array_key_exists($key, $contents)) {
                    $contents[$key] = $this->readObject($file);
                    $existing[$key] = $file;
                }

                $data = $contents[$key];

                if($data === null || !$format->detect($data)) {
                    continue;
                }

                $recognized[$key] = true;

                if($resolved === null) {
                    $resolved = new ResolvedSidecar($file, $format, $data);
                }
            }
        }

        return new SidecarResolution(
            $resolved,
            array_values(array_diff_key($existing, $recognized))
        );
    }

    /**
     * @param JSONFile $file
     * @return ImageMetadata
     * @throws MetadataException {@see MetadataException::ERROR_INVALID_STRUCTURE} if no format recognizes the content.
     */
    public function parseFile(JSONFile $file) : ImageMetadata
    {
        $data = $this->readObject($file);

        if($data !== null)
        {
            foreach($this->formats as $format)
            {
                if($format->detect($data)) {
                    return $format->parse($data);
                }
            }
        }

        throw new MetadataException(
            'Unrecognized sidecar.',
            sprintf('No sidecar format recognizes the content of [%s].', $file->getPath()),
            MetadataException::ERROR_INVALID_STRUCTURE
        );
    }

    /**
     * A root that is not an object (a scalar, `null`, a non-empty list) or unreadable JSON is
     * "unrecognized", not an error. An empty object, like `[]` which decodes the same, is accepted
     * here and left to `detect()`.
     *
     * TODO: Known limits that apply to every indexing run: a UTF-8 BOM before the JSON makes the file
     * unrecognized (pinned by testBomPrefixedSidecarIsUnrecognizedKnownDefectCharacterisation); a directory
     * named like a candidate raises a PHP warning here before it is reported as unrecognized; there is no
     * `is_file()` guard and no size cap.
     *
     * @param JSONFile $file
     * @return ArrayDataCollection|null `null` if the file is not readable JSON with an object at its root.
     */
    private function readObject(JSONFile $file) : ?ArrayDataCollection
    {
        try
        {
            // Decoded here rather than via getData(), whose `: array` type turns a scalar root into a TypeError.
            $raw = json_decode($file->getContents(), true, 512, JSON_THROW_ON_ERROR);
        }
        catch (FileHelper_Exception|JsonException)
        {
            return null;
        }

        // Checked before wrapping: the collection would present a list's indexes as string keys.
        if(!is_array($raw) || ($raw !== array() && array_is_list($raw))) {
            return null;
        }

        return ArrayDataCollection::create($raw);
    }
}
