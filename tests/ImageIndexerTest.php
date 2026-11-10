<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests;

use Mistralys\ComfyUIOrganizer\ImageIndex;
use Mistralys\ComfyUIOrganizer\ImageIndexer;
use PHPUnit\Framework\TestCase;

final class ImageIndexerTest extends TestCase
{
    /**
     * @param array<string,mixed> $properties
     * @return array<string,mixed>
     */
    private static function entryData(string $id, array $properties) : array
    {
        return array(
            'id' => $id,
            'imageFile' => 'C:/out/' . $id . '.png',
            'sidecarFile' => 'C:/out/' . $id . '.json',
            'date' => '2026-01-02 03:04:05.000000',
            'checkpoint' => 'model',
            'imageSize' => array('width' => 1, 'height' => 2),
            'properties' => $properties,
        );
    }

    public function testKnownFoldersAreExcluded() : void
    {
        $index = ImageIndex::fromArray(array(
            'a' => self::entryData('a', array('folder' => 'A')),
            'b' => self::entryData('b', array('folder' => 'B')),
            'b2' => self::entryData('b2', array('folder' => 'B')),
        ));

        $this->assertSame(array('C'), ImageIndexer::filterNewFolders(array('A', 'B', 'C'), $index));
    }

    public function testEntriesWithoutUsableFolderAreIgnored() : void
    {
        $index = ImageIndex::fromArray(array(
            'a' => self::entryData('a', array()),
            'b' => self::entryData('b', array('folder' => 5)),
            'c' => self::entryData('c', array('folder' => '')),
            'd' => self::entryData('d', array('folder' => 'D')),
        ));

        $this->assertSame(array('D'), $index->getFolderNames());
        $this->assertSame(array('A', 'B'), ImageIndexer::filterNewFolders(array('A', 'D', 'B'), $index));
    }

    public function testEmptyIndexReturnsAllFolders() : void
    {
        $this->assertSame(
            array('A', 'B'),
            ImageIndexer::filterNewFolders(array('A', 'B'), new ImageIndex())
        );
    }
}
