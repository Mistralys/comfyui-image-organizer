<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\FileHelper\JSONFile;

/**
 * The set of entries of the image index (`data/images.json`), keyed by image ID.
 */
class ImageIndex
{
    /**
     * @var array<string,ImageIndexEntry>
     */
    private array $entries = array();

    /**
     * Keys each entry by its own `id` and ignores the outer JSON key, so an entry
     * stored under another key is written back under its `id`. A numeric `id`
     * becomes an integer array key.
     *
     * @param array<int|string,mixed> $data
     * @return self
     * @throws OrganizerException {@see OrganizerException::ERROR_INVALID_INDEX_ENTRY}
     */
    public static function fromArray(array $data) : self
    {
        $index = new self();

        foreach($data as $key => $entry)
        {
            if(!is_array($entry)) {
                throw new OrganizerException(
                    'Invalid image index entry.',
                    sprintf(
                        'The index entry [%s] is not an array, it is of type [%s].',
                        $key,
                        get_debug_type($entry)
                    ),
                    OrganizerException::ERROR_INVALID_INDEX_ENTRY
                );
            }

            $index->set(ImageIndexEntry::fromArray($entry));
        }

        return $index;
    }

    /**
     * @param JSONFile $file
     * @return self
     * @throws OrganizerException
     */
    public static function load(JSONFile $file) : self
    {
        return self::fromArray($file->getData());
    }

    public function save(JSONFile $file) : void
    {
        $file->putData($this->toArray());
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function toArray() : array
    {
        $result = array();

        foreach($this->entries as $id => $entry) {
            $result[$id] = $entry->toArray();
        }

        return $result;
    }

    public function get(string $id) : ?ImageIndexEntry
    {
        return $this->entries[$id] ?? null;
    }

    public function has(string $id) : bool
    {
        return isset($this->entries[$id]);
    }

    /**
     * Adds the entry, or replaces the one stored under the same ID.
     */
    public function set(ImageIndexEntry $entry) : self
    {
        $this->entries[$entry->getID()] = $entry;
        return $this;
    }

    public function remove(string $id) : self
    {
        unset($this->entries[$id]);
        return $this;
    }

    /**
     * @return array<string,ImageIndexEntry>
     */
    public function getEntries() : array
    {
        return $this->entries;
    }

    public function count() : int
    {
        return count($this->entries);
    }

    /**
     * Names of the image folders that have at least one entry.
     *
     * @return string[]
     */
    public function getFolderNames() : array
    {
        $names = array();

        foreach($this->entries as $entry)
        {
            $name = $entry->getProperties()[ImageProperties::KEY_FOLDER_NAME] ?? null;

            if(is_string($name) && $name !== '') {
                $names[$name] = $name;
            }
        }

        return array_values($names);
    }
}
