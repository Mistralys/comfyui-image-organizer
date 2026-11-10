<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\FileHelper\JSONFile;

/**
 * Sparse updates of `data/file_index.json`, the lookup `image.php` serves from.
 */
final class FileIndex
{
    /**
     * Changes only the given entries and leaves all others as they are.
     *
     * @param JSONFile $file
     * @param array<string,array<string,string>> $entries Entries to add or replace, by image ID.
     * @param string[] $removedIDs
     * @return bool `false` when the file does not exist yet; nothing is written then, as a
     *              file holding only the patched entries would hide all the other images.
     */
    public static function patch(JSONFile $file, array $entries, array $removedIDs = array()) : bool
    {
        if(!$file->exists()) {
            return false;
        }

        $index = $file->getData();

        foreach($removedIDs as $id) {
            unset($index[$id]);
        }

        foreach($entries as $id => $entry) {
            $index[$id] = $entry;
        }

        $file->putData($index);

        return true;
    }
}
