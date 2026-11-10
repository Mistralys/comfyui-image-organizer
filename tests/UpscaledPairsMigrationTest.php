<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests;

use AppUtils\FileHelper\JSONFile;
use Mistralys\ComfyUIOrganizer\ImageIndex;
use Mistralys\ComfyUIOrganizer\UpscaledPairsMigration;
use Mistralys\X4\UI\Console;
use PHPUnit\Framework\TestCase;

final class UpscaledPairsMigrationTest extends TestCase
{
    private string $workDir;
    private JSONFile $storage;

    protected function setUp() : void
    {
        Console::setEnabled(false);

        $this->workDir = sys_get_temp_dir().'/comfyui-migration-'.uniqid('', true);
        mkdir($this->workDir.'/images/Cave', 0777, true);
        mkdir($this->workDir.'/data', 0777, true);

        $this->storage = JSONFile::factory($this->workDir.'/data/images.json');
    }

    protected function tearDown() : void
    {
        $this->removeTree($this->workDir);

        Console::setEnabled(true);
    }

    private function removeTree(string $path) : void
    {
        foreach((array)scandir($path) as $name)
        {
            if($name === '.' || $name === '..') {
                continue;
            }

            $child = $path.'/'.$name;

            if(is_dir($child)) {
                $this->removeTree($child);
            } else {
                unlink($child);
            }
        }

        rmdir($path);
    }

    private function image(string $name) : string
    {
        return $this->workDir.'/images/Cave/'.$name;
    }

    private function touchFile(string $name, string $content = 'x') : void
    {
        file_put_contents($this->image($name), $content);
    }

    /**
     * @param array<string,mixed> $properties
     * @return array<string,mixed>
     */
    private function entry(string $id, string $imageName, array $properties = array()) : array
    {
        return array(
            'id' => $id,
            'imageFile' => $this->image($imageName),
            'sidecarFile' => $this->image(str_replace('.png', '.json', $imageName)),
            'date' => '2026-01-02 03:04:05.000000',
            'checkpoint' => 'model',
            'upscaled' => $id === 'up',
            'imageSize' => array('width' => 1, 'height' => 1),
            'modified' => false,
            'label' => '',
            'properties' => $properties,
        );
    }

    private function writePair(string $linkTarget = 'up') : void
    {
        $this->touchFile('144-initial_01.png', 'regular');
        $this->touchFile('144-initial_01.json');
        $this->touchFile('146-initial-upscale4_01.png', 'upscaled');
        $this->touchFile('146-initial-upscale4_01.json');
        $this->touchFile('146-initial-upscale4_01.jpg', 'edited');

        $this->storage->putData(array(
            'reg' => $this->entry('reg', '144-initial_01.png', array('upscaledImage' => $linkTarget, 'folder' => 'Cave')),
            'up' => $this->entry('up', '146-initial-upscale4_01.png', array('folder' => 'Cave', 'websiteImageID' => '77')),
        ));
    }

    public function testDryRunChangesNothing() : void
    {
        $this->writePair();
        $before = (string)file_get_contents($this->storage->getPath());

        $result = new UpscaledPairsMigration($this->storage)->run(false);

        $this->assertSame(1, $result['migrated']);
        $this->assertSame($before, file_get_contents($this->storage->getPath()));
        $this->assertFileDoesNotExist($this->image('144-initial_01_upscaled.png'));
        $this->assertFileExists($this->image('146-initial-upscale4_01.png'));
    }

    public function testApplyConvertsPairToSiblingFiles() : void
    {
        $this->writePair();

        $result = new UpscaledPairsMigration($this->storage)->run(true);

        $this->assertSame(array('migrated' => 1, 'skipped' => 0, 'staleLinks' => 0), $result);

        $this->assertSame('upscaled', file_get_contents($this->image('144-initial_01_upscaled.png')));
        $this->assertSame('edited', file_get_contents($this->image('144-initial_01.jpg')));
        $this->assertFileExists($this->image('144-initial_01.png'));
        $this->assertFileDoesNotExist($this->image('146-initial-upscale4_01.png'));
        $this->assertFileDoesNotExist($this->image('146-initial-upscale4_01.json'));
        $this->assertFileDoesNotExist($this->image('146-initial-upscale4_01.jpg'));

        $index = ImageIndex::load($this->storage);
        $this->assertFalse($index->has('up'));

        $regular = $index->get('reg');
        $this->assertNotNull($regular);
        $this->assertArrayNotHasKey('upscaledImage', $regular->getProperties());
        $this->assertSame('77', $regular->getProperties()['websiteImageID']);
        $this->assertTrue($regular->isModified());

        $this->assertNotEmpty(glob($this->workDir.'/data/backup/*-before-upscaled-migration.json'));
    }

    public function testRerunAfterInterruptedCopyStillMigrates() : void
    {
        $this->writePair();
        $this->touchFile('144-initial_01_upscaled.png', 'upscaled');

        $result = new UpscaledPairsMigration($this->storage)->run(true);

        $this->assertSame(1, $result['migrated']);
        $this->assertFileDoesNotExist($this->image('146-initial-upscale4_01.png'));
    }

    public function testDifferentFileAtTargetSkipsThePair() : void
    {
        $this->writePair();
        $this->touchFile('144-initial_01_upscaled.png', 'something else');

        $result = new UpscaledPairsMigration($this->storage)->run(true);

        $this->assertSame(array('migrated' => 0, 'skipped' => 1, 'staleLinks' => 0), $result);
        $this->assertFileExists($this->image('146-initial-upscale4_01.png'));
        $this->assertTrue(ImageIndex::load($this->storage)->has('up'));
    }

    public function testStaleLinkIsDropped() : void
    {
        $this->writePair('gone');

        $result = new UpscaledPairsMigration($this->storage)->run(true);

        $this->assertSame(array('migrated' => 0, 'skipped' => 0, 'staleLinks' => 1), $result);
        $this->assertArrayNotHasKey('upscaledImage', ImageIndex::load($this->storage)->get('reg')?->getProperties() ?? array());
        $this->assertFileExists($this->image('146-initial-upscale4_01.png'));
    }
}
