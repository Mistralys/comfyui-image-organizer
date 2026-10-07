<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\Metadata;

use AppUtils\ArrayDataCollection;
use AppUtils\FileHelper\FileInfo;
use Mistralys\ComfyUIOrganizer\Metadata\Formats\LegacySidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\Formats\StructuredSidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\ImageMetadata;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataException;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataRegistry;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataVerifier;
use Mistralys\ComfyUIOrganizer\Metadata\SidecarFormatInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('metadata')]
final class MetadataVerifierTest extends TestCase
{
    private string $workDir;

    protected function setUp() : void
    {
        $this->workDir = sys_get_temp_dir().'/comfyui-verifier-'.uniqid('', true);
        mkdir($this->workDir, 0777, true);
    }

    protected function tearDown() : void
    {
        foreach((array)glob($this->workDir.'/*') as $file) {
            unlink((string)$file);
        }

        rmdir($this->workDir);
    }

    private static function fixturePath(string $name) : string
    {
        return __DIR__.'/../fixtures/sidecars/'.$name;
    }

    /**
     * @param string|null $fixture Fixture to copy as the sidecar; `null` for an image without sidecar.
     */
    private function image(string $name, ?string $sidecarName = null, ?string $fixture = null) : FileInfo
    {
        $path = $this->workDir.'/'.$name;
        touch($path);

        if($sidecarName !== null && $fixture !== null) {
            copy(self::fixturePath($fixture), $this->workDir.'/'.$sidecarName);
        }

        return FileInfo::factory($path);
    }

    public function testCensusCountsOutcomes() : void
    {
        $images = array(
            $this->image('legacy.png', 'legacy.json', 'legacy-checkpoint.json'),
            $this->image('structured-nr1_00001_.png', 'structured-nr1-meta.json', 'structured-flux-v32.json'),
            $this->image('workflow.png', 'workflow.json', 'workflow-export.json'),
            $this->image('none.png'),
            $this->image('nomodel.png')
        );

        file_put_contents(
            $this->workDir.'/nomodel.json',
            '{"29/08/2025 21:59:20": {"custom_text": "-sampler:euler"}}'
        );

        $census = new MetadataVerifier(MetadataRegistry::create())->census($images);

        $this->assertSame(
            array(LegacySidecarFormat::FORMAT_ID => 1, StructuredSidecarFormat::FORMAT_ID => 1),
            $census['formats']
        );
        $this->assertSame(array('none.png'), $census['noSidecar']);
        $this->assertSame(array('workflow.png'), $census['unrecognized']);
        $this->assertSame(array('nomodel.png'), $census['noModelName']);
        $this->assertSame(array(), $census['failures']);
    }

    public function testCensusReportsUnexpectedParseFailures() : void
    {
        $image = $this->image('broken.png', 'broken.json', 'legacy-checkpoint.json');

        $registry = MetadataRegistry::createWithFormats(array(new class() implements SidecarFormatInterface
        {
            public function getID() : string
            {
                return 'failing';
            }

            public function getPriority() : int
            {
                return 1;
            }

            public function getSidecarCandidates(FileInfo $image) : array
            {
                return array(str_replace('.png', '.json', $image->getPath()));
            }

            public function detect(ArrayDataCollection $data) : bool
            {
                return true;
            }

            public function parse(ArrayDataCollection $data) : ImageMetadata
            {
                throw new MetadataException(
                    'Invalid structure.',
                    'The stub cannot parse anything.',
                    MetadataException::ERROR_INVALID_STRUCTURE
                );
            }
        }));

        $census = new MetadataVerifier($registry)->census(array($image));

        $this->assertSame(array('broken.png'), $census['failures']);
        $this->assertSame(array(), $census['noModelName']);
        $this->assertSame(array(), $census['formats']);
    }

    public function testCensusHasNoFailuresForCleanFolder() : void
    {
        $images = array(
            $this->image('legacy.png', 'legacy.json', 'legacy-model-vae.json'),
            $this->image('structured-nr1_00001_.png', 'structured-nr1-meta.json', 'structured-upscaled.json')
        );

        $census = new MetadataVerifier(MetadataRegistry::create())->census($images);

        $this->assertSame(array(), $census['failures']);
        $this->assertSame(array(), $census['noModelName']);
        $this->assertSame(array(), $census['noSidecar']);
        $this->assertSame(array(), $census['unrecognized']);
        $this->assertSame(2, array_sum($census['formats']));
    }
}
