<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\Metadata;

use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\JSONFile;
use Mistralys\ComfyUIOrganizer\Metadata\Formats\LegacySidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\Formats\StructuredSidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataException;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('metadata')]
final class MetadataRegistryTest extends TestCase
{
    private string $workDir;

    protected function setUp() : void
    {
        $this->workDir = sys_get_temp_dir().'/comfyui-registry-'.uniqid('', true);
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
     * Copies a fixture (or writes literal content) into the work folder.
     */
    private function place(string $targetName, string $fixtureName) : void
    {
        copy(self::fixturePath($fixtureName), $this->workDir.'/'.$targetName);
    }

    private function write(string $targetName, string $content) : void
    {
        file_put_contents($this->workDir.'/'.$targetName, $content);
    }

    private function image(string $name) : FileInfo
    {
        $path = $this->workDir.'/'.$name;
        touch($path);

        return FileInfo::factory($path);
    }

    public function testDiscoversFormatsInPriorityOrder() : void
    {
        $registry = MetadataRegistry::create();

        $ids = array();
        foreach($registry->getFormats() as $format) {
            $ids[] = $format->getID();
        }

        $this->assertSame(
            array(StructuredSidecarFormat::FORMAT_ID, LegacySidecarFormat::FORMAT_ID),
            $ids
        );
        $this->assertSame(MetadataRegistry::getInstance(), MetadataRegistry::getInstance());
    }

    public function testResolvePrefersMetaOverWorkflowExport() : void
    {
        $this->place('a_00001_.json', 'workflow-export.json');
        $this->place('a-meta.json', 'structured-flux-v32.json');

        $resolution = MetadataRegistry::create()->resolve($this->image('a_00001_.png'));

        $sidecar = $resolution->getSidecar();
        $this->assertNotNull($sidecar);
        $this->assertSame(StructuredSidecarFormat::FORMAT_ID, $sidecar->getFormat()->getID());
        $this->assertSame('a-meta.json', basename($sidecar->getSidecar()->getPath()));
    }

    public function testResolveLegacy() : void
    {
        $this->place('b.json', 'legacy-checkpoint.json');

        $sidecar = MetadataRegistry::create()->resolve($this->image('b.png'))->getSidecar();

        $this->assertNotNull($sidecar);
        $this->assertSame(LegacySidecarFormat::FORMAT_ID, $sidecar->getFormat()->getID());
    }

    public function testResolveEarlyMetaCounterNaming() : void
    {
        $this->place('c-meta_00001.json', 'structured-v31-early.json');

        $sidecar = MetadataRegistry::create()->resolve($this->image('c_00001_.png'))->getSidecar();

        $this->assertNotNull($sidecar);
        $this->assertSame(StructuredSidecarFormat::FORMAT_ID, $sidecar->getFormat()->getID());
        $this->assertSame('c-meta_00001.json', basename($sidecar->getSidecar()->getPath()));
    }

    public function testResolveRejectsUnrecognizedContent() : void
    {
        $this->place('d.json', 'workflow-export.json');

        $resolution = MetadataRegistry::create()->resolve($this->image('d.png'));

        $this->assertFalse($resolution->isResolved());
        $this->assertNull($resolution->getSidecar());
        $this->assertCount(1, $resolution->getUnrecognizedCandidates());
    }

    public function testResolutionReportsUnrecognizedCandidates() : void
    {
        $this->place('a_00001_.json', 'workflow-export.json');
        $this->place('a-meta.json', 'structured-flux-v32.json');

        $resolution = MetadataRegistry::create()->resolve($this->image('a_00001_.png'));

        $this->assertTrue($resolution->isResolved());
        $unrecognized = $resolution->getUnrecognizedCandidates();
        $this->assertCount(1, $unrecognized);
        $this->assertSame('a_00001_.json', basename($unrecognized[0]->getPath()));

        $none = MetadataRegistry::create()->resolve($this->image('none.png'));

        $this->assertFalse($none->isResolved());
        $this->assertSame(array(), $none->getUnrecognizedCandidates());
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function invalidContentProvider() : array
    {
        return array(
            'invalid JSON' => array('{not json'),
            'empty file' => array(''),
            'scalar number' => array('42'),
            'scalar null' => array('null'),
            'list root' => array('[{"a":1}]'),
        );
    }

    #[DataProvider('invalidContentProvider')]
    public function testInvalidJsonCandidateIsUnrecognized(string $content) : void
    {
        $this->write('e.json', $content);

        $resolution = MetadataRegistry::create()->resolve($this->image('e.png'));

        $this->assertFalse($resolution->isResolved());
        $this->assertCount(1, $resolution->getUnrecognizedCandidates());
    }

    public function testParseFile() : void
    {
        $registry = MetadataRegistry::create();

        $legacy = $registry->parseFile(JSONFile::factory(self::fixturePath('legacy-checkpoint.json')));
        $this->assertSame(LegacySidecarFormat::FORMAT_ID, $legacy->getFormatID());

        $structured = $registry->parseFile(JSONFile::factory(self::fixturePath('structured-flux-v32.json')));
        $this->assertSame(StructuredSidecarFormat::FORMAT_ID, $structured->getFormatID());

        try {
            $registry->parseFile(JSONFile::factory(self::fixturePath('workflow-export.json')));
            $this->fail('A workflow export must not be parsed.');
        } catch (MetadataException $e) {
            $this->assertSame(MetadataException::ERROR_INVALID_STRUCTURE, $e->getCode());
        }
    }

    public function testParseFileRejectsInvalidJson() : void
    {
        $this->write('f.json', '{not json');

        $this->expectException(MetadataException::class);

        MetadataRegistry::create()->parseFile(JSONFile::factory($this->workDir.'/f.json'));
    }

    public function testCreateWithFormatsUsesExactlyTheGivenFormats() : void
    {
        $registry = MetadataRegistry::createWithFormats(array(new LegacySidecarFormat()));

        $this->assertCount(1, $registry->getFormats());

        $this->place('a-meta.json', 'structured-flux-v32.json');

        $this->assertFalse($registry->resolve($this->image('a.png'))->isResolved());
    }
}
