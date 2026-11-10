<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests;

use AppUtils\FileHelper\JSONFile;
use Mistralys\ComfyUIOrganizer\FileIndex;
use PHPUnit\Framework\TestCase;

final class FileIndexTest extends TestCase
{
    private string $path;
    private JSONFile $file;

    protected function setUp() : void
    {
        $this->path = sys_get_temp_dir().'/FileIndexTest-'.uniqid('', true).'.json';
        $this->file = JSONFile::factory($this->path);
    }

    protected function tearDown() : void
    {
        if(file_exists($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * @return array<string,string>
     */
    private static function entry(string $folder) : array
    {
        return array('imageFile' => 'C:/out/'.$folder.'/1.png', 'thumbnailFile' => 'cache/'.$folder.'.jpg');
    }

    public function testPatchReplacesAndRemovesOnlyTheGivenEntries() : void
    {
        $this->file->putData(array(
            'moved' => self::entry('Old'),
            'gone' => self::entry('Gone'),
            'other' => self::entry('Other'),
        ));

        $this->assertTrue(FileIndex::patch($this->file, array('moved' => self::entry('New')), array('gone')));

        $this->assertSame(
            array('moved' => self::entry('New'), 'other' => self::entry('Other')),
            $this->file->getData()
        );
    }

    public function testPatchAddsUnknownEntry() : void
    {
        $this->file->putData(array('other' => self::entry('Other')));

        FileIndex::patch($this->file, array('fresh' => self::entry('Fresh')));

        $this->assertSame(array('other', 'fresh'), array_keys($this->file->getData()));
    }

    public function testPatchWritesNothingWhenFileIsMissing() : void
    {
        $this->assertFalse(FileIndex::patch($this->file, array('moved' => self::entry('New'))));
        $this->assertFileDoesNotExist($this->path);
    }
}
