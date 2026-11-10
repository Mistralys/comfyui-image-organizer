<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\ArrayDataCollection;

/**
 * One record of the image index (`data/images.json`).
 *
 * Keys the application does not model are kept in {@see self::getExtra()}
 * and written back by {@see self::toArray()}, so nothing stored in the
 * index is lost on a load-and-save cycle.
 */
class ImageIndexEntry
{
    private const array KNOWN_KEYS = array(
        ImageInfo::KEY_ID,
        ImageInfo::KEY_IMAGE_FILE,
        ImageInfo::KEY_SIDECAR_FILE,
        ImageInfo::KEY_DATE,
        ImageInfo::KEY_CHECKPOINT,
        ImageInfo::KEY_IMAGE_SIZE,
        ImageInfo::KEY_MODIFIED,
        ImageInfo::KEY_LABEL,
        ImageInfo::KEY_PROPERTIES
    );

    private string $id;
    private string $imageFile;
    private string $sidecarFile;
    private string $date;
    private string $checkpoint;
    private bool $modified;
    private string $label;

    /**
     * @var array{width:int,height:int}
     */
    private array $imageSize;

    /**
     * @var array<int|string,mixed>
     */
    private array $properties;

    /**
     * @var array<string,mixed>
     */
    private array $extra;

    /**
     * @param string $id
     * @param string $imageFile
     * @param string $sidecarFile
     * @param string $date ISO date string.
     * @param string $checkpoint
     * @param bool $modified
     * @param string $label
     * @param array{width:int,height:int} $imageSize
     * @param array<int|string,mixed> $properties
     * @param array<string,mixed> $extra Unknown top-level keys, kept for the round trip.
     */
    public function __construct(
        string $id,
        string $imageFile,
        string $sidecarFile,
        string $date,
        string $checkpoint,
        bool $modified,
        string $label,
        array $imageSize,
        array $properties,
        array $extra = array()
    ) {
        $this->id = $id;
        $this->imageFile = $imageFile;
        $this->sidecarFile = $sidecarFile;
        $this->date = $date;
        $this->checkpoint = $checkpoint;
        $this->modified = $modified;
        $this->label = $label;
        $this->imageSize = $imageSize;
        $this->properties = $properties;
        $this->extra = $extra;
    }

    /**
     * Lenient: a missing field takes its default (`''`, `false`, `0`, `[]`), so a legacy entry
     * without `label` or `modified` gains them in the next written index, a one-time large diff.
     *
     * @param array<int|string,mixed> $data
     * @return self
     */
    public static function fromArray(array $data) : self
    {
        $collection = ArrayDataCollection::create($data);
        $size = ArrayDataCollection::create($collection->getArray(ImageInfo::KEY_IMAGE_SIZE));

        $extra = array();
        foreach($data as $key => $value) {
            if(!in_array($key, self::KNOWN_KEYS, true)) {
                $extra[(string)$key] = $value;
            }
        }

        return new self(
            $collection->getString(ImageInfo::KEY_ID),
            $collection->getString(ImageInfo::KEY_IMAGE_FILE),
            $collection->getString(ImageInfo::KEY_SIDECAR_FILE),
            $collection->getString(ImageInfo::KEY_DATE),
            $collection->getString(ImageInfo::KEY_CHECKPOINT),
            $collection->getBool(ImageInfo::KEY_MODIFIED),
            $collection->getString(ImageInfo::KEY_LABEL),
            array('width' => $size->getInt('width'), 'height' => $size->getInt('height')),
            $collection->getArray(ImageInfo::KEY_PROPERTIES),
            $extra
        );
    }

    /**
     * Known keys in the order of {@see ImageInfo::serialize()}, followed by the unknown keys.
     * The merge would renumber integer-like unknown keys; real index data has none.
     *
     * @return array<string,mixed>
     */
    public function toArray() : array
    {
        return array_merge(
            array(
                ImageInfo::KEY_ID => $this->id,
                ImageInfo::KEY_IMAGE_FILE => $this->imageFile,
                ImageInfo::KEY_SIDECAR_FILE => $this->sidecarFile,
                ImageInfo::KEY_DATE => $this->date,
                ImageInfo::KEY_CHECKPOINT => $this->checkpoint,
                ImageInfo::KEY_IMAGE_SIZE => $this->imageSize,
                ImageInfo::KEY_MODIFIED => $this->modified,
                ImageInfo::KEY_LABEL => $this->label,
                ImageInfo::KEY_PROPERTIES => $this->properties
            ),
            $this->extra
        );
    }

    /**
     * Updates the location values after an image was moved or renamed.
     *
     * @param string $imageFile
     * @param string $sidecarFile
     * @param string $folderName
     * @return bool Whether any value was changed.
     */
    public function syncLocation(string $imageFile, string $sidecarFile, string $folderName) : bool
    {
        $updated = false;

        if($this->imageFile !== $imageFile) {
            $this->imageFile = $imageFile;
            $updated = true;
        }

        if($this->sidecarFile !== $sidecarFile) {
            $this->sidecarFile = $sidecarFile;
            $updated = true;
        }

        if(($this->properties[ImageProperties::KEY_FOLDER_NAME] ?? null) !== $folderName) {
            $this->properties[ImageProperties::KEY_FOLDER_NAME] = $folderName;
            $updated = true;
        }

        return $updated;
    }

    /**
     * Returns a copy that takes all known fields from the source entry
     * and keeps the unknown keys of this entry.
     *
     * @param ImageIndexEntry $source
     * @return ImageIndexEntry
     */
    public function withKnownFieldsFrom(ImageIndexEntry $source) : self
    {
        return new self(
            $source->id,
            $source->imageFile,
            $source->sidecarFile,
            $source->date,
            $source->checkpoint,
            $source->modified,
            $source->label,
            $source->imageSize,
            $source->properties,
            $this->extra
        );
    }

    public function getID() : string
    {
        return $this->id;
    }

    public function getImageFile() : string
    {
        return $this->imageFile;
    }

    public function getSidecarFile() : string
    {
        return $this->sidecarFile;
    }

    public function getDate() : string
    {
        return $this->date;
    }

    public function getCheckpoint() : string
    {
        return $this->checkpoint;
    }

    public function isModified() : bool
    {
        return $this->modified;
    }

    public function getLabel() : string
    {
        return $this->label;
    }

    /**
     * @return array{width:int,height:int}
     */
    public function getImageSize() : array
    {
        return $this->imageSize;
    }

    /**
     * @return array<int|string,mixed>
     */
    public function getProperties() : array
    {
        return $this->properties;
    }

    /**
     * @return array<string,mixed>
     */
    public function getExtra() : array
    {
        return $this->extra;
    }
}
