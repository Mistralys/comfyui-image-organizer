<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests;

use Mistralys\ComfyUIOrganizer\ImageIndexEntry;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\Metadata\Formats\LegacySidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\Formats\StructuredSidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataException;
use PHPUnit\Framework\TestCase;

final class ImageInfoTest extends TestCase
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

    public function testSerializeKeepsPayloadShape() : void
    {
        $image = ImageInfo::fromEntry(ImageIndexEntry::fromArray(self::sampleData()));

        $payload = $image->serialize();

        $this->assertSame(
            array(
                'id', 'imageFile', 'sidecarFile', 'date', 'checkpoint', 'upscaled',
                'imageSize', 'modified', 'label', 'properties'
            ),
            array_keys($payload)
        );
        $this->assertSame('abc', $payload['id']);
        $this->assertSame('model.safetensors', $payload['checkpoint']);
        $this->assertFalse($payload['upscaled']);
        $this->assertFalse($payload['modified']);
        $this->assertSame('', $payload['label']);
        $this->assertSame(array('width' => 512, 'height' => 768), $payload['imageSize']);
        $this->assertSame(
            array('favorite' => true, 'folder' => 'Folder', 'seed' => '42'),
            $payload['properties']
        );
    }

    public function testToEntryHasNoUnknownKeys() : void
    {
        $image = ImageInfo::fromEntry(ImageIndexEntry::fromArray(self::sampleData()));

        $entry = $image->toEntry();

        $this->assertSame(array(), $entry->getExtra());
        $this->assertArrayNotHasKey('forGallery', $entry->toArray());
    }

    private static function imageWithSidecar(string $sidecarPath) : ImageInfo
    {
        $data = self::sampleData();
        $data['sidecarFile'] = $sidecarPath;

        return ImageInfo::fromEntry(ImageIndexEntry::fromArray($data));
    }

    public function testGetMetadataIsLazyAndDoesNotMarkChanged() : void
    {
        $fixtures = __DIR__.'/fixtures/sidecars/';

        $legacy = self::imageWithSidecar($fixtures.'legacy-checkpoint.json');
        $structured = self::imageWithSidecar($fixtures.'structured-flux-v32.json');

        $this->assertSame(LegacySidecarFormat::FORMAT_ID, $legacy->getMetadata()->getFormatID());
        $this->assertSame(StructuredSidecarFormat::FORMAT_ID, $structured->getMetadata()->getFormatID());

        $this->assertFalse($legacy->isDataChanged());
        $this->assertFalse($structured->isDataChanged());
        $this->assertFalse($structured->toEntry()->isModified());
        $this->assertSame($structured->getMetadata(), $structured->getMetadata());
    }

    public function testGetMetadataThrowsForMissingOrUnrecognizedSidecar() : void
    {
        $fixtures = __DIR__.'/fixtures/sidecars/';

        foreach(array($fixtures.'does-not-exist.json', $fixtures.'workflow-export.json') as $path)
        {
            try
            {
                self::imageWithSidecar($path)->getMetadata();
                $this->fail('A sidecar that is missing or not recognized must throw.');
            }
            catch (MetadataException $e)
            {
                $this->assertSame(MetadataException::ERROR_INVALID_STRUCTURE, $e->getCode());
            }
        }
    }
}
