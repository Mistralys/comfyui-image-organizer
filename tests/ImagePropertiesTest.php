<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests;

use Mistralys\ComfyUIOrganizer\ImageIndexEntry;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\ImageProperties;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImagePropertiesTest extends TestCase
{
    /**
     * @param array<string,mixed> $properties
     */
    private static function createProperties(array $properties) : ImageProperties
    {
        $image = ImageInfo::fromEntry(ImageIndexEntry::fromArray(array(
            'id' => 'abc',
            'imageFile' => 'C:/out/Folder/001.png',
            'sidecarFile' => 'C:/out/Folder/001.json',
            'date' => '2026-01-02 03:04:05.000000',
            'checkpoint' => 'model.safetensors',
            'imageSize' => array('width' => 512, 'height' => 768),
            'properties' => $properties
        )));

        return $image->prop();
    }

    public function testFlowGetters() : void
    {
        $props = self::createProperties(array(
            ImageProperties::KEY_FLOW_TYPE => 'flux',
            ImageProperties::KEY_IS_PREVIEW => 'true',
            ImageProperties::KEY_WORKFLOW_VERSION => '32'
        ));

        $this->assertSame('flux', $props->getFlowType());
        $this->assertSame(32, $props->getWorkflowVersion());
        $this->assertTrue($props->isPreview());
    }

    public function testFlowGettersWhenAbsent() : void
    {
        $props = self::createProperties(array());

        $this->assertNull($props->getFlowType());
        $this->assertNull($props->getWorkflowVersion());
        $this->assertFalse($props->isPreview());
    }

    public function testWorkflowVersionIsNullWhenEmptyOrNotNumeric() : void
    {
        $this->assertNull(self::createProperties(array(ImageProperties::KEY_WORKFLOW_VERSION => ''))->getWorkflowVersion());
        $this->assertNull(self::createProperties(array(ImageProperties::KEY_WORKFLOW_VERSION => 'abc'))->getWorkflowVersion());
        $this->assertSame(31, self::createProperties(array(ImageProperties::KEY_WORKFLOW_VERSION => 31))->getWorkflowVersion());
    }

    public function testIsPreviewFalse() : void
    {
        $this->assertFalse(self::createProperties(array(ImageProperties::KEY_IS_PREVIEW => 'false'))->isPreview());
    }

    /**
     * @return array<string,array{0:mixed,1:int|null}>
     */
    public static function workflowVersionProvider() : array
    {
        return array(
            'int' => array(31, 31),
            'zero stays zero' => array(0, 0),
            'numeric string' => array('32', 32),
            'decimal string is truncated' => array('32.7', 32),
            'exponent string is cast' => array('1e3', 1000),
            'float is not accepted' => array(32.7, null),
            'bool is not accepted' => array(true, null),
            'array is not accepted' => array(array(32), null),
            'empty string' => array('', null),
        );
    }

    #[DataProvider('workflowVersionProvider')]
    public function testWorkflowVersionTypeRules(mixed $stored, ?int $expected) : void
    {
        $props = self::createProperties(array(ImageProperties::KEY_WORKFLOW_VERSION => $stored));

        $this->assertSame($expected, $props->getWorkflowVersion());
    }

    public function testFlowVersionKeyIsNotAFallback() : void
    {
        $this->assertNull(self::createProperties(array('flowVersion' => 32))->getWorkflowVersion());
    }

    public function testEnablingForWebsiteCascadesToGalleryAndFavorite() : void
    {
        $props = self::createProperties(array());

        $props->setForWebsite(true);

        $this->assertTrue($props->isForWebsite());
        $this->assertTrue($props->isForGallery());
        $this->assertTrue($props->isFavorite());
    }

    public function testEnablingForGalleryCascadesToFavoriteOnly() : void
    {
        $props = self::createProperties(array());

        $props->setForGallery(true);

        $this->assertTrue($props->isForGallery());
        $this->assertTrue($props->isFavorite());
        $this->assertFalse($props->isForWebsite());
    }

    public function testDisablingDoesNotCascade() : void
    {
        $props = self::createProperties(array());
        $props->setForWebsite(true);

        $props->setForWebsite(false);

        $this->assertFalse($props->isForWebsite());
        $this->assertTrue($props->isForGallery());

        $props->setForGallery(false);

        $this->assertFalse($props->isForGallery());
        $this->assertTrue($props->isFavorite());
    }
}
