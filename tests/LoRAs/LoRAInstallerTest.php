<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\LoRAs;

use FilesystemIterator;
use Mistralys\ComfyUIOrganizer\LoRAs\CivitaiImporter;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRAFile;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRAInstaller;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRAModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class LoRAInstallerTest extends TestCase
{
    private string $folder;
    private string $root;
    private string $registry;
    private string $source;
    private const string URL = 'https://civitai.com/models/832858?modelVersionId=1376386';

    protected function setUp() : void
    {
        $this->folder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lora-install-'.bin2hex(random_bytes(8));
        $this->root = $this->folder.DIRECTORY_SEPARATOR.'models';
        foreach(array('FLUX/ArtStyles', 'FLUX/Fantasy', 'SDXL/ArtStyles', 'SDXL/Detailers') as $relative) {
            mkdir($this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative), 0777, true);
        }
        mkdir($this->folder.DIRECTORY_SEPARATOR.'registry');
        $this->registry = $this->folder.DIRECTORY_SEPARATOR.'registry'.DIRECTORY_SEPARATOR.'loras.json';
        file_put_contents($this->registry, '{}');
        $this->source = $this->folder.DIRECTORY_SEPARATOR.'Anime Art V3.safetensors';
        file_put_contents($this->source, 'model fixture contents');
    }

    protected function tearDown() : void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach($iterator as $file) {
            if(!$file instanceof SplFileInfo) {
                throw new RuntimeException('Unexpected temporary fixture entry.');
            }
            if($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->folder);
    }

    private function importer(LoRAInstaller $installer, string $baseModel = 'Flux.1 D') : CivitaiImporter
    {
        return new CivitaiImporter(dirname($this->registry), static fn(string $url) : string => json_encode(array(
            'id' => 1376386,
            'modelId' => 832858,
            'baseModel' => $baseModel,
            'trainedWords' => array('Anime art'),
            'model' => array('name' => 'Anime art', 'type' => 'LORA')
        ), JSON_THROW_ON_ERROR), $installer);
    }

    private function target(string $relative = 'FLUX/ArtStyles') : string
    {
        return $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative)
            .DIRECTORY_SEPARATOR.'Anime Art V3.safetensors';
    }

    public function testFullPathCopiesThenSavesUsingSuggestedFolder() : void
    {
        $installer = new LoRAInstaller($this->root, chooseDestination:
            function(?string $suggested, array $folders, string $model, string $category) : string {
                $this->assertSame('FLUX\ArtStyles', $suggested);
                $this->assertContains('FLUX'.DIRECTORY_SEPARATOR.'ArtStyles', $folders);
                $this->assertSame('FLUX', $model);
                $this->assertSame('Styles', $category);
                return 'FLUX\ArtStyles';
            }
        );
        $lora = $this->importer($installer)->import(self::URL, $this->source, 'Styles');

        $this->assertFileEquals($this->source, $this->target());
        $this->assertFileExists($this->source);
        $this->assertSame(array('Anime Art V3.safetensors'), $lora->getFiles());
        $entries = LoRAFile::load($this->registry)->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame($lora->getSource(), $entries[0]->getSource());
        $this->assertSame(array('Anime Art V3.safetensors'),
            array_values(array_diff(scandir(dirname($this->target())) ?: array(), array('.', '..'))));
    }

    public function testDryRunPreviewsDestinationWithoutCopyingOrSaving() : void
    {
        $installer = new LoRAInstaller($this->root, destination: 'FLUX\ArtStyles');
        $this->importer($installer)->import(self::URL, $this->source, 'Styles', true);
        $this->assertSame($this->target(), $installer->getLastDestination());
        $this->assertFileDoesNotExist($this->target());
        $this->assertSame('{}', file_get_contents($this->registry));
        $this->assertSame(array('.', '..'), scandir(dirname($this->target())));
    }

    public function testBasenameStaysRegistryOnlyWithoutReadingModelRoot() : void
    {
        $installer = new LoRAInstaller($this->root.DIRECTORY_SEPARATOR.'missing');
        $this->importer($installer)->import(self::URL, 'Anime Art V3.safetensors', 'Styles');
        $this->assertNull($installer->getLastDestination());
        $this->assertCount(1, LoRAFile::load($this->registry)->getEntries());
    }

    public function testMissingPathFailsBeforeNetworkAndRegistryChanges() : void
    {
        $installer = new LoRAInstaller($this->root);
        $importer = new CivitaiImporter(dirname($this->registry), function(string $url) : string {
            $this->fail('Missing source paths must fail before the API request.');
        }, $installer);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing or unreadable');
        $importer->import(self::URL, $this->folder.DIRECTORY_SEPARATOR.'missing.safetensors', 'Styles');
    }

    public function testIdenticalExistingFileIsReused() : void
    {
        copy($this->source, $this->target());
        $installer = new LoRAInstaller($this->root, destination: 'FLUX\ArtStyles');
        $this->importer($installer)->import(self::URL, $this->source, 'Styles');
        $this->assertTrue($installer->isReusingFile());
        $this->assertFileEquals($this->source, $this->target());
        $this->assertCount(1, LoRAFile::load($this->registry)->getEntries());
    }

    public function testDifferentExistingContentsAreNeverOverwritten() : void
    {
        file_put_contents($this->target(), 'other fixture contents');
        $installer = new LoRAInstaller($this->root, destination: 'FLUX\ArtStyles');
        try {
            $this->importer($installer)->import(self::URL, $this->source, 'Styles');
            $this->fail('Different model contents must not be overwritten.');
        } catch(RuntimeException $e) {
            $this->assertStringContainsString('different contents', $e->getMessage());
            $this->assertSame('other fixture contents', file_get_contents($this->target()));
            $this->assertSame('{}', file_get_contents($this->registry));
        }
    }

    public function testFailedRegistrySaveRollsBackNewCopyAndKeepsSourceAndConcurrentEdit() : void
    {
        $concurrent = '{"concurrent":{"label":"Keep this edit"}}';
        $installer = new LoRAInstaller($this->root, chooseDestination: function() use ($concurrent) : string {
            file_put_contents($this->registry, $concurrent);
            return 'FLUX\ArtStyles';
        });
        try {
            $this->importer($installer)->import(self::URL, $this->source, 'Styles');
            $this->fail('A stale registry save must fail.');
        } catch(RuntimeException $e) {
            $this->assertStringContainsString('changed during import', $e->getMessage());
            $this->assertFileDoesNotExist($this->target());
            $this->assertFileExists($this->source);
            $this->assertSame($concurrent, file_get_contents($this->registry));
            $this->assertSame(array('.', '..'), scandir(dirname($this->target())));
        }
    }

    public function testFailedRegistrySaveNeverDeletesReusedFile() : void
    {
        copy($this->source, $this->target());
        $installer = new LoRAInstaller($this->root, chooseDestination: function() : string {
            file_put_contents($this->registry, '{"concurrent":{}}');
            return 'FLUX\ArtStyles';
        });
        try {
            $this->importer($installer)->import(self::URL, $this->source, 'Styles');
            $this->fail('A stale registry save must fail.');
        } catch(RuntimeException) {
            $this->assertFileEquals($this->source, $this->target());
        }
    }

    public function testRollbackDoesNotDeleteACopyChangedDuringSaving() : void
    {
        $installer = new LoRAInstaller($this->root, destination: 'FLUX\ArtStyles');
        $model = new LoRAModel('FLUX', self::URL, basename($this->source), array(), '', '');
        try {
            $installer->run($this->source, $model, 'Styles', false, function() : never {
                $this->assertFileExists($this->target(), 'The copy must exist before registry saving.');
                file_put_contents($this->target(), 'An external edit');
                throw new RuntimeException('Save failed.');
            });
            $this->fail('Registry saving must fail.');
        } catch(RuntimeException $e) {
            $this->assertStringContainsString('changed and was not removed', $e->getMessage());
            $this->assertSame('An external edit', file_get_contents($this->target()));
            $this->assertSame('model fixture contents', file_get_contents($this->source));
            $this->assertSame('{}', file_get_contents($this->registry));
            $this->assertSame(array('.', '..', 'Anime Art V3.safetensors'), scandir(dirname($this->target())));
        }
    }

    public function testSourceAlreadyInDestinationIsReused() : void
    {
        copy($this->source, $this->target());
        $installer = new LoRAInstaller($this->root, destination: 'FLUX\ArtStyles');
        $this->importer($installer)->import(self::URL, $this->target(), 'Styles');
        $this->assertTrue($installer->isReusingFile());
        $this->assertFileExists($this->target());
        $this->assertCount(1, LoRAFile::load($this->registry)->getEntries());
    }

    public function testDestinationCanBeOverriddenAndMappingsAreConfigurable() : void
    {
        $installer = new LoRAInstaller($this->root, array('FLUX' => array('Styles' => 'FLUX\Fantasy')));
        $this->importer($installer)->import(self::URL, $this->source, 'Styles');
        $this->assertFileEquals($this->source, $this->target('FLUX/Fantasy'));
        $this->assertFileDoesNotExist($this->target());
    }

    public function testKnownSDXLMappingAndExplicitUnknownModelDestination() : void
    {
        $installer = new LoRAInstaller($this->root);
        $this->importer($installer, 'SDXL 1.0')->import(self::URL, $this->source, 'Details', true);
        $this->assertSame($this->target('SDXL/Detailers'), $installer->getLastDestination());

        $explicit = new LoRAInstaller($this->root, destination: 'FLUX\Fantasy');
        $this->importer($explicit, 'ZImageTurbo')->import(self::URL, $this->source, 'Styles', true);
        $this->assertSame($this->target('FLUX/Fantasy'), $explicit->getLastDestination());
    }

    /** @return array<string,array{string}> */
    public static function otherModelProvider() : array
    {
        return array(
            'unrelated base model' => array('ZImageTurbo'),
            'Flux Schnell is not Dev' => array('Flux.1 S')
        );
    }

    #[DataProvider('otherModelProvider')]
    public function testOtherModelsDoNotSilentlySelectFluxDev(string $baseModel) : void
    {
        $installer = new LoRAInstaller($this->root);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No destination mapping');
        $this->importer($installer, $baseModel)->import(self::URL, $this->source, 'Styles');
    }

    public function testMergeUsesExistingCategoryAndCopiesOnlyIncomingVariant() : void
    {
        file_put_contents($this->registry, '{"animeArt":{"category":"Styles","models":{"SDXL 1.0":{"file":"old.safetensors"}}}}');
        $installer = new LoRAInstaller($this->root);
        $lora = $this->importer($installer)->import(self::URL, $this->source, 'Fantasy');
        $this->assertSame(array('SDXL 1.0', 'FLUX'), $lora->getModelNames());
        $this->assertFileExists($this->target());
        $this->assertFileDoesNotExist($this->target('FLUX/Fantasy'));
    }

    /** @return array<string,array{string}> */
    public static function unsafeDestinationProvider() : array
    {
        return array(
            'parent traversal' => array('..\outside'),
            'embedded traversal' => array('FLUX\..\SDXL'),
            'drive path' => array('C:\models'),
            'rooted path' => array('\FLUX\ArtStyles'),
            'UNC path' => array('\\\\server\share'),
            'missing folder' => array('FLUX\Missing'),
            'empty path' => array('')
        );
    }

    #[DataProvider('unsafeDestinationProvider')]
    public function testInvalidDestinationDoesNotCopyOrSave(string $destination) : void
    {
        $installer = new LoRAInstaller($this->root, destination: $destination);
        try {
            $this->importer($installer)->import(self::URL, $this->source, 'Styles');
            $this->fail('Invalid destination must fail.');
        } catch(RuntimeException) {
            $this->assertSame('{}', file_get_contents($this->registry));
            $this->assertFileDoesNotExist($this->target());
        }
    }

    public function testDestinationRequiresAnExplicitSourcePath() : void
    {
        $installer = new LoRAInstaller($this->root, destination: 'FLUX\ArtStyles');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--destination requires a local source path');
        $this->importer($installer)->import(self::URL, 'Anime Art V3.safetensors', 'Styles');
    }
}
