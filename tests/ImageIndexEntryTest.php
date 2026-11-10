<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests;

use Mistralys\ComfyUIOrganizer\ImageIndexEntry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImageIndexEntryTest extends TestCase
{
    /**
     * @return array<string,mixed>
     */
    private static function sampleData() : array
    {
        return array(
            'id' => 'abc',
            'imageFile' => 'C:/out/Folder/001.png',
            'sidecarFile' => 'C:/out/Folder/001.json',
            'date' => '2026-01-02 03:04:05.000000',
            'checkpoint' => 'model.safetensors',
            'imageSize' => array('width' => 512, 'height' => 768),
            'properties' => array('folder' => 'Folder', 'seed' => '42', 'favorite' => true),
            'forGallery' => true
        );
    }

    public function testFromArrayFillsDefaults() : void
    {
        $entry = ImageIndexEntry::fromArray(self::sampleData());

        $this->assertFalse($entry->isModified());
        $this->assertSame('', $entry->getLabel());
        $this->assertSame('abc', $entry->getID());
        $this->assertSame(array('width' => 512, 'height' => 768), $entry->getImageSize());
    }

    public function testToArrayKeepsKeyOrderAndUnknownKeys() : void
    {
        $result = ImageIndexEntry::fromArray(self::sampleData())->toArray();

        $this->assertSame(
            array(
                'id', 'imageFile', 'sidecarFile', 'date', 'checkpoint',
                'imageSize', 'modified', 'label', 'properties', 'forGallery'
            ),
            array_keys($result)
        );
        $this->assertTrue($result['forGallery']);
        $this->assertFalse($result['modified']);
        $this->assertSame('', $result['label']);
    }

    public function testRetiredUpscaledKeyIsKeptAsUnknownKey() : void
    {
        $data = self::sampleData();
        $data['upscaled'] = true;

        $result = ImageIndexEntry::fromArray($data)->toArray();

        $this->assertTrue($result['upscaled']);
    }

    public function testWithKnownFieldsFromKeepsUnknownKeys() : void
    {
        $stored = ImageIndexEntry::fromArray(self::sampleData());
        $changedData = self::sampleData();
        $changedData['label'] = 'New label';
        $changedData['modified'] = true;
        $changedData['properties'] = array('folder' => 'Other');
        unset($changedData['forGallery']);
        $changedData['other'] = 'ignored';
        $source = ImageIndexEntry::fromArray($changedData);

        $merged = $stored->withKnownFieldsFrom($source);

        $this->assertNotSame($stored, $merged);
        $this->assertSame('New label', $merged->getLabel());
        $this->assertTrue($merged->isModified());
        $this->assertSame(array('folder' => 'Other'), $merged->getProperties());
        $this->assertSame(array('forGallery' => true), $merged->getExtra());

        // The receiver is not mutated.
        $this->assertSame('', $stored->getLabel());
        $this->assertFalse($stored->isModified());
        $this->assertSame(array('folder' => 'Folder', 'seed' => '42', 'favorite' => true), $stored->getProperties());
    }

    /**
     * @return array<string,array{0:string,1:string,2:string,3:bool,4:string,5:string,6:string}>
     */
    public static function syncLocationProvider() : array
    {
        $image = 'C:/out/Folder/001.png';
        $sidecar = 'C:/out/Folder/001.json';

        return array(
            'unchanged' => array($image, $sidecar, 'Folder', false, $image, $sidecar, 'Folder'),
            'image changed' => array('C:/out/New/001.png', $sidecar, 'Folder', true, 'C:/out/New/001.png', $sidecar, 'Folder'),
            'sidecar changed' => array($image, 'C:/out/New/001.json', 'Folder', true, $image, 'C:/out/New/001.json', 'Folder'),
            'folder changed' => array($image, $sidecar, 'New', true, $image, $sidecar, 'New'),
        );
    }

    #[DataProvider('syncLocationProvider')]
    public function testSyncLocation(
        string $imageFile,
        string $sidecarFile,
        string $folderName,
        bool $expectedResult,
        string $expectedImage,
        string $expectedSidecar,
        string $expectedFolder
    ) : void {
        $data = self::sampleData();
        $data['modified'] = true;
        $data['label'] = 'Keep';
        $entry = ImageIndexEntry::fromArray($data);

        $this->assertSame($expectedResult, $entry->syncLocation($imageFile, $sidecarFile, $folderName));

        $this->assertSame($expectedImage, $entry->getImageFile());
        $this->assertSame($expectedSidecar, $entry->getSidecarFile());
        $this->assertSame(
            array('folder' => $expectedFolder, 'seed' => '42', 'favorite' => true),
            $entry->getProperties()
        );
        $this->assertTrue($entry->isModified());
        $this->assertSame('Keep', $entry->getLabel());
    }

    public function testSyncLocationFillsEmptyFolderName() : void
    {
        $data = self::sampleData();
        $data['properties'] = array('folder' => '', 'seed' => '42');
        $entry = ImageIndexEntry::fromArray($data);

        $this->assertTrue($entry->syncLocation('C:/out/Folder/001.png', 'C:/out/Folder/001.json', 'Folder'));
        $this->assertSame(array('folder' => 'Folder', 'seed' => '42'), $entry->getProperties());
    }

    public function testSyncLocationFillsMissingFolderName() : void
    {
        $data = self::sampleData();
        $data['properties'] = array();
        $entry = ImageIndexEntry::fromArray($data);

        $this->assertTrue($entry->syncLocation('C:/out/Folder/001.png', 'C:/out/Folder/001.json', 'Folder'));
        $this->assertSame(array('folder' => 'Folder'), $entry->getProperties());
    }
}
