<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests;

use Mistralys\ComfyUIOrganizer\ImageIndexEntry;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\ImageProperties;
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
}
