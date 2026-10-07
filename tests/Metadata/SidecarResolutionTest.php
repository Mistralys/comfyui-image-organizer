<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\Metadata;

use AppUtils\ArrayDataCollection;
use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\JSONFile;
use Mistralys\ComfyUIOrganizer\Metadata\BaseSidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\ImageMetadata;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataException;
use Mistralys\ComfyUIOrganizer\Metadata\ResolvedSidecar;
use Mistralys\ComfyUIOrganizer\Metadata\SidecarResolution;
use Mistralys\ComfyUIOrganizer\OrganizerException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('metadata')]
final class SidecarResolutionTest extends TestCase
{
    public function testExceptionCodes() : void
    {
        $codes = array(
            183001 => MetadataException::ERROR_NO_MODEL_NAME,
            183002 => MetadataException::ERROR_INVALID_STRUCTURE,
        );

        foreach($codes as $expected => $code)
        {
            try {
                throw new MetadataException('Test', 'Details', $code);
            } catch (OrganizerException $e) {
                $this->assertSame($expected, $e->getCode());
            }
        }
    }

    public function testResolutionWithoutSidecarIsNotResolved() : void
    {
        $resolution = new SidecarResolution();

        $this->assertFalse($resolution->isResolved());
        $this->assertNull($resolution->getSidecar());
        $this->assertSame(array(), $resolution->getUnrecognizedCandidates());
    }

    public function testResolutionExposesSidecarAndCandidates() : void
    {
        $file = JSONFile::factory(__DIR__.'/../fixtures/sidecars/workflow-export.json');
        $format = new class extends BaseSidecarFormat
        {
            public function getID() : string
            {
                return 'stub';
            }

            public function getPriority() : int
            {
                return 1;
            }

            public function getSidecarCandidates(FileInfo $image) : array
            {
                return array();
            }

            public function detect(ArrayDataCollection $data) : bool
            {
                return true;
            }

            public function parse(ArrayDataCollection $data) : ImageMetadata
            {
                return new ImageMetadata(formatID: $this->getID());
            }
        };
        $data = ArrayDataCollection::create(array('a' => 1));
        $resolved = new ResolvedSidecar($file, $format, $data);

        $resolution = new SidecarResolution($resolved, array($file));

        $this->assertTrue($resolution->isResolved());
        $this->assertSame($resolved, $resolution->getSidecar());
        $this->assertSame(array($file), $resolution->getUnrecognizedCandidates());
        $this->assertSame($file, $resolved->getSidecar());
        $this->assertSame($format, $resolved->getFormat());
        $this->assertSame($data, $resolved->getData());
    }
}
