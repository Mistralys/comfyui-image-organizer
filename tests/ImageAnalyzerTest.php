<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests;

use AppUtils\ArrayDataCollection;
use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\JSONFile;
use AppUtils\ImageHelper_Exception;
use AppUtils\Microtime;
use AppUtils\OperationResult;
use AppUtils\OperationResult_Collection;
use Mistralys\ComfyUIOrganizer\ImageAnalyzer;
use Mistralys\ComfyUIOrganizer\ImageIndex;
use Mistralys\ComfyUIOrganizer\ImageIndexEntry;
use Mistralys\ComfyUIOrganizer\ImageIndexer;
use Mistralys\ComfyUIOrganizer\ImageProperties;
use Mistralys\ComfyUIOrganizer\Metadata\Formats\LegacySidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\Formats\StructuredSidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\ImageMetadata;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataException;
use Mistralys\ComfyUIOrganizer\Metadata\ResolvedSidecar;
use Mistralys\ComfyUIOrganizer\Metadata\SidecarFormatInterface;
use Mistralys\ComfyUIOrganizer\OrganizerException;
use Mistralys\X4\UI\Console;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('metadata')]
final class ImageAnalyzerTest extends TestCase
{
    private const string FOLDER_NAME = 'folder-a';
    private const string OTHER_FOLDER_NAME = 'folder-b';

    private string $workDir;
    private ImageIndex $index;
    private OperationResult_Collection $results;
    private ImageAnalyzer $analyzer;

    protected function setUp() : void
    {
        Console::setEnabled(false);

        $this->workDir = sys_get_temp_dir().'/comfyui-analyzer-'.uniqid('', true);
        mkdir($this->workDir.'/'.self::FOLDER_NAME, 0777, true);
        mkdir($this->workDir.'/'.self::OTHER_FOLDER_NAME, 0777, true);

        $this->index = new ImageIndex();
        $this->results = new OperationResult_Collection($this);
        $this->analyzer = new ImageAnalyzer($this->index, $this->results);
    }

    protected function tearDown() : void
    {
        foreach(array(self::FOLDER_NAME, self::OTHER_FOLDER_NAME) as $folder)
        {
            foreach((array)glob($this->workDir.'/'.$folder.'/*') as $file) {
                unlink((string)$file);
            }

            rmdir($this->workDir.'/'.$folder);
        }

        rmdir($this->workDir);

        Console::setEnabled(true);
    }

    private static function fixturePath(string $name) : string
    {
        return __DIR__.'/fixtures/sidecars/'.$name;
    }

    /**
     * Writes a small PNG; the color makes the content, and so the hash, unique.
     *
     * @param int<1,max> $width
     * @param int<1,max> $height
     */
    private function createPng(string $name, int $color = 1, int $width = 8, int $height = 8, string $folder = self::FOLDER_NAME) : FileInfo
    {
        $path = $this->workDir.'/'.$folder.'/'.$name;

        $image = imagecreatetruecolor($width, $height);
        $this->assertNotFalse($image);
        imagefill($image, 0, 0, $color);
        imagepng($image, $path);

        return FileInfo::factory($path);
    }

    private function hashOf(FileInfo $image) : string
    {
        return (string)hash_file('md5', $image->getPath());
    }

    /**
     * Places a sidecar next to the images and wraps it the way the registry does.
     *
     * @param string $fixture Fixture file name.
     * @param string $targetName Name of the sidecar in the work folder.
     */
    private function resolveFixture(string $fixture, string $targetName = 'sidecar.json', string $folder = self::FOLDER_NAME) : ResolvedSidecar
    {
        $path = $this->workDir.'/'.$folder.'/'.$targetName;
        copy(self::fixturePath($fixture), $path);

        $file = JSONFile::factory($path);
        $data = ArrayDataCollection::create($file->getData());

        foreach(array(new StructuredSidecarFormat(), new LegacySidecarFormat()) as $format)
        {
            if($format->detect($data)) {
                return new ResolvedSidecar($file, $format, $data);
            }
        }

        $this->fail('No format recognizes the fixture '.$fixture);
    }

    /**
     * @param array<int|string,mixed> $content
     */
    private function resolveContent(array $content, SidecarFormatInterface $format, string $targetName = 'synthetic.json') : ResolvedSidecar
    {
        $file = JSONFile::factory($this->workDir.'/'.self::FOLDER_NAME.'/'.$targetName);
        $file->putData($content);

        return new ResolvedSidecar($file, $format, ArrayDataCollection::create($content));
    }

    /**
     * @param array<int|string,mixed> $properties
     * @param array<string,mixed> $extra
     */
    private function makeEntry(
        string $id,
        string $imagePath,
        string $sidecarPath,
        string $date,
        bool $modified,
        string $label = '',
        array $properties = array(),
        array $extra = array()
    ) : ImageIndexEntry
    {
        return new ImageIndexEntry(
            $id,
            $imagePath,
            $sidecarPath,
            $date,
            'stored-checkpoint',
            false,
            $modified,
            $label,
            array('width' => 1, 'height' => 1),
            $properties,
            $extra
        );
    }

    private function onlyEntry() : ImageIndexEntry
    {
        $entries = array_values($this->index->getEntries());

        $this->assertCount(1, $entries);

        return $entries[0];
    }

    /**
     * @return OperationResult[]
     */
    private function resultList() : array
    {
        return $this->results->getResults();
    }

    private function isoOf(int $timestamp) : string
    {
        return date('Y-m-d H:i:s', $timestamp).'.000000';
    }

    public function testIndexesLegacySidecar() : void
    {
        $image = $this->createPng('1-other-seed7-3.png');
        $sidecar = $this->resolveFixture('legacy-checkpoint.json');

        $this->analyzer->analyze($image, $sidecar);

        $entry = $this->onlyEntry();

        $expected = JSONFile::factory(self::fixturePath('legacy-checkpoint.expected-bag.json'))->getData();
        $expected[ImageProperties::KEY_FOLDER_NAME] = self::FOLDER_NAME;

        $this->assertSame($expected, $entry->getProperties());
        $this->assertSame('nevolutionXLIL_v2Base', $entry->getCheckpoint());
        $this->assertSame(Microtime::createFromString('2025-08-29 21:59:20')->getISODate(), $entry->getDate());
        $this->assertSame($this->hashOf($image), $entry->getID());
        $this->assertSame($image->getPath(), $entry->getImageFile());
        $this->assertSame($sidecar->getSidecar()->getPath(), $entry->getSidecarFile());
        $this->assertFalse($entry->isUpscaled());
        $this->assertFalse($entry->isModified());
        $this->assertSame(array('width' => 8, 'height' => 8), $entry->getImageSize());

        $this->assertSame(1, $this->results->countResults());
        $this->assertTrue($this->results->containsCode(ImageIndexer::ACTION_IMAGE_INDEXED));
        $this->assertCount(1, $this->results->getSuccesses());
    }

    public function testLegacyDateComesFromSidecarEvenWithStoredEntry() : void
    {
        $image = $this->createPng('1-other-seed7-3.png');
        $sidecar = $this->resolveFixture('legacy-checkpoint.json');

        $this->index->set($this->makeEntry(
            $this->hashOf($image),
            $image->getPath(),
            $sidecar->getSidecar()->getPath(),
            '2026-01-01 00:00:00.000000',
            false
        ));

        $this->analyzer->analyze($image, $sidecar);

        $this->assertSame(Microtime::createFromString('2025-08-29 21:59:20')->getISODate(), $this->onlyEntry()->getDate());
    }

    public function testIndexesStructuredSidecar() : void
    {
        $image = $this->createPng('x-seed1-nr1_00001_.png');
        $time = (int)strtotime('2024-03-04 10:20:30');
        touch($image->getPath(), $time);
        clearstatcache();

        $this->analyzer->analyze($image, $this->resolveFixture('structured-flux-v32.json'));

        $entry = $this->onlyEntry();
        $props = $entry->getProperties();

        $this->assertSame('FLUX 1 DEV', $entry->getCheckpoint());
        $this->assertSame($this->isoOf($time), $entry->getDate());
        $this->assertSame('FLUX1', $props['flowType']);
        $this->assertSame('32', $props['workflowVersion']);
        $this->assertSame('false', $props['isPreview']);
        $this->assertArrayNotHasKey('flowVersion', $props);
        $this->assertSame(self::FOLDER_NAME, $props[ImageProperties::KEY_FOLDER_NAME]);
        $this->assertTrue($this->results->containsCode(ImageIndexer::ACTION_IMAGE_INDEXED));
    }

    public function testStructuredReindexKeepsStoredDate() : void
    {
        $image = $this->createPng('x-seed1-nr1_00001_.png');
        touch($image->getPath(), (int)strtotime('2024-03-04 10:20:30'));
        clearstatcache();

        $sidecar = $this->resolveFixture('structured-flux-v32.json');

        $this->index->set($this->makeEntry(
            $this->hashOf($image),
            $image->getPath(),
            $sidecar->getSidecar()->getPath(),
            '2026-01-01',
            false
        ));

        $this->analyzer->analyze($image, $sidecar);

        $entry = $this->onlyEntry();

        $this->assertSame('2026-01-01', $entry->getDate());
        $this->assertSame('FLUX 1 DEV', $entry->getCheckpoint());
    }

    public function testStructuredWithoutModelIsIndexedWithEmptyCheckpoint() : void
    {
        $image = $this->createPng('x-seed1-nr1_00001_.png');

        $this->analyzer->analyze($image, $this->resolveFixture('structured-v31-early.json'));

        $this->assertSame('', $this->onlyEntry()->getCheckpoint());
        $this->assertTrue($this->results->containsCode(ImageIndexer::ACTION_IMAGE_INDEXED));
        $this->assertSame(0, $this->results->countWarnings());
    }

    public function testModifiedEntryOnlyHasLocationSynced() : void
    {
        $image = $this->createPng('x-seed1-nr1_00001_.png', 2, 8, 8, self::OTHER_FOLDER_NAME);
        $sidecar = $this->resolveFixture('structured-flux-v32.json', 'moved.json', self::OTHER_FOLDER_NAME);

        $properties = array(
            ImageProperties::KEY_FOLDER_NAME => self::FOLDER_NAME,
            ImageProperties::KEY_FAVORITE => true,
            ImageProperties::KEY_SEED => 'stored-seed'
        );

        $stored = $this->makeEntry(
            $this->hashOf($image),
            $this->workDir.'/'.self::FOLDER_NAME.'/x-seed1-nr1_00001_.png',
            $this->workDir.'/'.self::FOLDER_NAME.'/old.json',
            '2025-05-05',
            true,
            'My label',
            $properties,
            array('forGallery' => true)
        );
        $this->index->set($stored);

        $this->analyzer->analyze($image, $sidecar);

        $entry = $this->onlyEntry();

        $this->assertSame($stored, $entry);
        $this->assertSame($image->getPath(), $entry->getImageFile());
        $this->assertSame($sidecar->getSidecar()->getPath(), $entry->getSidecarFile());
        $this->assertSame(self::OTHER_FOLDER_NAME, $entry->getProperties()[ImageProperties::KEY_FOLDER_NAME]);

        $this->assertSame('My label', $entry->getLabel());
        $this->assertTrue($entry->isModified());
        $this->assertSame('stored-checkpoint', $entry->getCheckpoint());
        $this->assertSame('2025-05-05', $entry->getDate());
        $this->assertTrue($entry->getProperties()[ImageProperties::KEY_FAVORITE]);
        $this->assertSame('stored-seed', $entry->getProperties()[ImageProperties::KEY_SEED]);
        $this->assertSame(array('forGallery' => true), $entry->getExtra());

        $this->assertSame(1, $this->results->countResults());
        $this->assertTrue($this->results->containsCode(ImageIndexer::ACTION_UPDATE_PATHS));
        $this->assertCount(1, $this->results->getNotices());
    }

    public function testModifiedEntryWithSameLocationIsSkippedSilently() : void
    {
        $image = $this->createPng('x-seed1-nr1_00001_.png');
        $sidecar = $this->resolveFixture('structured-flux-v32.json');

        $stored = $this->makeEntry(
            $this->hashOf($image),
            $image->getPath(),
            $sidecar->getSidecar()->getPath(),
            '2025-05-05',
            true,
            'My label',
            array(ImageProperties::KEY_FOLDER_NAME => self::FOLDER_NAME)
        );
        $this->index->set($stored);

        $before = $stored->toArray();

        $this->analyzer->analyze($image, $sidecar);

        $this->assertSame(0, $this->results->countResults());
        $this->assertSame($before, $this->onlyEntry()->toArray());
    }

    public function testReindexKeepsUnknownKeysOfStoredEntry() : void
    {
        $image = $this->createPng('x-seed1-nr1_00001_.png');
        $sidecar = $this->resolveFixture('structured-flux-v32.json');

        $this->index->set($this->makeEntry(
            $this->hashOf($image),
            $image->getPath(),
            $sidecar->getSidecar()->getPath(),
            '2025-05-05',
            false,
            '',
            array(),
            array('forGallery' => true, 'zzz' => array('a' => 1))
        ));

        $this->analyzer->analyze($image, $sidecar);

        $entry = $this->onlyEntry();

        $this->assertSame('FLUX 1 DEV', $entry->getCheckpoint());
        $this->assertSame('2025-05-05', $entry->getDate());
        $this->assertSame(array('forGallery' => true, 'zzz' => array('a' => 1)), $entry->getExtra());

        $keys = array_keys($entry->toArray());
        $this->assertSame(array('forGallery', 'zzz'), array_slice($keys, -2));
    }

    public function testLegacySidecarWithoutModelNameIsSkipped() : void
    {
        $image = $this->createPng('1-other-seed7-3.png');
        $sidecar = $this->resolveContent(
            array('29/08/2025 21:59:20' => array('custom_text' => '-sampler:euler')),
            new LegacySidecarFormat()
        );

        $this->analyzer->analyze($image, $sidecar);

        $this->assertSame(0, $this->index->count());
        $this->assertSame(1, $this->results->countResults());
        $this->assertSame(1, $this->results->countWarnings());
        $this->assertTrue($this->results->containsCode(ImageIndexer::ACTION_SKIP_NO_CHECKPOINT));
    }

    public function testInvalidSidecarIsSkippedWithWarning() : void
    {
        $image = $this->createPng('1-other-seed7-3.png');
        $sidecar = $this->resolveContent(array('anything' => array('a' => 1)), $this->createFailingFormat('details'));

        $this->analyzer->analyze($image, $sidecar);

        $this->assertSame(0, $this->index->count());
        $this->assertSame(1, $this->results->countResults());
        $this->assertSame(1, $this->results->countWarnings());
        $this->assertTrue($this->results->containsCode(ImageIndexer::ACTION_SKIP_INVALID_SIDECAR));
        $this->assertSame(180906, $this->resultList()[0]->getCode());
    }

    public function testUnreadableImageThrowsHashError() : void
    {
        $missing = FileInfo::factory($this->workDir.'/'.self::FOLDER_NAME.'/missing.png');
        $sidecar = $this->resolveFixture('structured-flux-v32.json');

        try
        {
            $this->analyzer->analyze($missing, $sidecar);
            $this->fail('The missing image must throw.');
        }
        catch (OrganizerException $e)
        {
            $this->assertSame(OrganizerException::ERROR_CANNOT_HASH_IMAGE_FILE, $e->getCode());
        }

        $this->assertSame(0, $this->index->count());
    }

    public function testCompletePropertiesFileNameFallbacks() : void
    {
        $image = FileInfo::factory($this->workDir.'/'.self::FOLDER_NAME.'/3-castle-seed99-2.png');

        $bag = ImageAnalyzer::completeProperties(array(), $image);

        $this->assertSame('3', $bag[ImageProperties::KEY_TEST_NUMBER]);
        $this->assertSame('castle', $bag[ImageProperties::KEY_TEST_NAME]);
        $this->assertSame('99', $bag[ImageProperties::KEY_SEED]);
        $this->assertSame('2', $bag[ImageProperties::KEY_IMG_BATCH_NR]);
        $this->assertSame(self::FOLDER_NAME, $bag[ImageProperties::KEY_FOLDER_NAME]);

        $present = array(
            ImageProperties::KEY_TEST_NUMBER => '10',
            ImageProperties::KEY_TEST_NAME => 'other',
            ImageProperties::KEY_SEED => '5',
            ImageProperties::KEY_IMG_BATCH_NR => '7',
            ImageProperties::KEY_FOLDER_NAME => 'ignored'
        );

        $kept = ImageAnalyzer::completeProperties($present, $image);

        $this->assertSame(
            array_merge($present, array(ImageProperties::KEY_FOLDER_NAME => self::FOLDER_NAME)),
            $kept
        );

        $noSeed = ImageAnalyzer::completeProperties(
            array(),
            FileInfo::factory($this->workDir.'/'.self::FOLDER_NAME.'/3-castle-2.png')
        );

        $this->assertSame('', $noSeed[ImageProperties::KEY_TEST_NAME]);
        $this->assertSame('', $noSeed[ImageProperties::KEY_SEED]);
    }

    /**
     * `FileInfo::getFolder()` throws for a dotted folder name such as `SD1.5` when that
     * folder does not exist on disk; the name is taken from the path instead.
     */
    public function testCompletePropertiesTakesDottedFolderNameFromPathNotFolderInfo() : void
    {
        $image = FileInfo::factory($this->workDir.'/SD1.5/3-castle-seed99-2.png');

        $bag = ImageAnalyzer::completeProperties(array(), $image);

        $this->assertSame('SD1.5', $bag[ImageProperties::KEY_FOLDER_NAME]);
    }

    /**
     * Pins a known defect: the size lookup is not guarded, so one unreadable PNG
     * aborts the analysis instead of being skipped with a warning.
     */
    public function testUnreadablePngAbortsAnalysisKnownDefectCharacterisation() : void
    {
        $path = $this->workDir.'/'.self::FOLDER_NAME.'/1-x-seed1-1.png';
        file_put_contents($path, 'not a png');

        $this->expectException(ImageHelper_Exception::class);

        // getimagesize() raises a notice for the unreadable file before the exception is thrown.
        set_error_handler(static fn() : bool => true);

        try
        {
            $this->analyzer->analyze(FileInfo::factory($path), $this->resolveFixture('structured-flux-v32.json'));
        }
        finally
        {
            restore_error_handler();

            $this->assertSame(0, $this->index->count());
            $this->assertSame(0, $this->results->countResults());
        }
    }

    /**
     * Pins the upscale heuristics as they are today, including the swapped
     * arguments of `str_contains()` that make the file name check dead.
     *
     * Plan `2026-10-07-structured-sidecar-metadata` keeps this behavior on
     * purpose. The follow-up plan that fixes the check must update this test:
     * a file name containing "upscale" will then mark the image upscaled.
     */
    public function testUpscaleFlagCharacterisation() : void
    {
        $byFactor = $this->createPng('1-x-seed1-1.png', 1);
        $this->analyzer->analyze($byFactor, $this->resolveFixture('structured-upscaled.json', 'a.json'));
        $this->assertTrue($this->index->get($this->hashOf($byFactor))?->isUpscaled());

        $bySize = $this->createPng('2-x-seed1-1.png', 2, 2000, 10);
        $this->analyzer->analyze($bySize, $this->resolveFixture('structured-v31-early.json', 'b.json'));
        $this->assertTrue($this->index->get($this->hashOf($bySize))?->isUpscaled());

        $byName = $this->createPng('3-upscale-seed1-1.png', 3);
        $this->analyzer->analyze($byName, $this->resolveFixture('structured-v31-early.json', 'c.json'));
        $this->assertFalse($this->index->get($this->hashOf($byName))?->isUpscaled());
    }

    public function testResultMessagesContainNoSidecarContent() : void
    {
        $secretPrompt = 'SECRET-PROMPT-CONTENT';
        $secretError = 'SECRET-ERROR-DETAILS';

        $noModel = $this->createPng('1-a-seed1-1.png', 1);
        $this->analyzer->analyze($noModel, $this->resolveContent(
            array('29/08/2025 21:59:20' => array('custom_text' => '-promptPositive:`'.$secretPrompt.'`')),
            new LegacySidecarFormat(),
            'no-model.json'
        ));

        $invalid = $this->createPng('2-a-seed1-1.png', 2);
        $this->analyzer->analyze($invalid, $this->resolveContent(
            array('x' => array('y' => $secretPrompt)),
            $this->createFailingFormat($secretError),
            'invalid.json'
        ));

        $indexed = $this->createPng('3-a-seed1-1.png', 3);
        $this->analyzer->analyze($indexed, $this->resolveFixture('legacy-checkpoint.json', 'indexed.json'));

        $moved = $this->createPng('4-a-seed1-1.png', 4);
        $this->index->set($this->makeEntry(
            $this->hashOf($moved),
            $this->workDir.'/'.self::OTHER_FOLDER_NAME.'/4-a-seed1-1.png',
            $this->workDir.'/'.self::OTHER_FOLDER_NAME.'/old.json',
            '2025-05-05',
            true
        ));
        $this->analyzer->analyze($moved, $this->resolveFixture('legacy-checkpoint.json', 'moved.json'));

        $results = $this->resultList();
        $this->assertCount(4, $results);

        $names = array($noModel, $invalid, $indexed, $moved);

        foreach($results as $position => $result)
        {
            $message = $result->getMessage();

            $this->assertStringContainsString($names[$position]->getName(), $message);
            $this->assertStringNotContainsString($secretPrompt, $message);
            $this->assertStringNotContainsString($secretError, $message);
            $this->assertStringNotContainsString('lich princess', $message);
            $this->assertStringNotContainsString($this->workDir, $message);
        }
    }

    private function createFailingFormat(string $details) : SidecarFormatInterface
    {
        return new class($details) implements SidecarFormatInterface
        {
            public function __construct(private readonly string $details)
            {
            }

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
                return array();
            }

            public function detect(ArrayDataCollection $data) : bool
            {
                return true;
            }

            public function parse(ArrayDataCollection $data) : ImageMetadata
            {
                throw new MetadataException(
                    'Invalid structure.',
                    $this->details,
                    MetadataException::ERROR_INVALID_STRUCTURE
                );
            }
        };
    }
}
