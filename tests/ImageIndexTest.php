<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests;

use AppUtils\FileHelper\JSONFile;
use Mistralys\ComfyUIOrganizer\ImageIndex;
use Mistralys\ComfyUIOrganizer\ImageIndexEntry;
use Mistralys\ComfyUIOrganizer\OrganizerException;
use PHPUnit\Framework\TestCase;

final class ImageIndexTest extends TestCase
{
    /**
     * @return array<string,mixed>
     */
    private static function entryData(string $id) : array
    {
        return array(
            'id' => $id,
            'imageFile' => 'C:/out/F/' . $id . '.png',
            'sidecarFile' => 'C:/out/F/' . $id . '.json',
            'date' => '2026-01-02 03:04:05.000000',
            'checkpoint' => 'model',
            'imageSize' => array('width' => 1, 'height' => 2),
            'properties' => array('folder' => 'F'),
        );
    }

    public function testNonArrayEntryThrows() : void
    {
        try {
            ImageIndex::fromArray(array('broken' => 'text'));
            $this->fail('No exception thrown.');
        } catch (OrganizerException $e) {
            $this->assertSame(OrganizerException::ERROR_INVALID_INDEX_ENTRY, $e->getCode());
            $this->assertStringContainsString('broken', $e->getDetails());
        }
    }

    public function testSetGetRemoveHas() : void
    {
        $index = ImageIndex::fromArray(array('a' => self::entryData('a'), 'b' => self::entryData('b')));

        $this->assertSame(2, $index->count());
        $this->assertTrue($index->has('a'));
        $this->assertFalse($index->has('c'));
        $this->assertNull($index->get('c'));

        $replacement = ImageIndexEntry::fromArray(array_merge(self::entryData('a'), array('label' => 'Replaced')));
        $index->set($replacement);

        $this->assertSame($replacement, $index->get('a'));
        $this->assertSame(2, $index->count());
        $this->assertSame(array('a', 'b'), array_keys($index->getEntries()));

        $index->remove('a');

        $this->assertFalse($index->has('a'));
        $this->assertNull($index->get('a'));
        $this->assertSame(1, $index->count());
    }

    public function testRealIndexRoundTrip() : void
    {
        $file = JSONFile::factory(__DIR__ . '/../data/images.json');
        if(!$file->exists()) {
            $this->markTestSkipped('data/images.json is not available.');
        }

        $original = $file->getData();
        $first = ImageIndex::fromArray($original)->toArray();

        $this->assertCount(count($original), $first);

        foreach($original as $key => $originalEntry)
        {
            $this->assertIsArray($originalEntry);
            $this->assertArrayHasKey($key, $first);

            $written = $first[$key];
            $added = array_diff_key($written, $originalEntry);

            $this->assertSame(
                array(),
                array_diff(array_keys($added), array('modified', 'label', 'upscaled')),
                'Unexpected keys added to entry ' . $key
            );

            foreach($originalEntry as $name => $value) {
                $this->assertArrayHasKey($name, $written);
                $this->assertEquals($value, $written[$name], sprintf('Entry %s, key %s', $key, $name));
            }
        }

        $second = ImageIndex::fromArray($first)->toArray();

        $this->assertSame(json_encode($first), json_encode($second));
    }

    public function testSaveMergePreservesUnknownKeys() : void
    {
        $storedData = self::entryData('a');
        $storedData['forGallery'] = true;
        $index = ImageIndex::fromArray(array('a' => $storedData));

        $changed = ImageIndexEntry::fromArray(array_merge(self::entryData('a'), array('label' => 'Changed', 'modified' => true)));

        $stored = $index->get('a');
        $this->assertNotNull($stored);
        $index->set($stored->withKnownFieldsFrom($changed));

        $result = $index->toArray();

        $this->assertTrue($result['a']['forGallery']);
        $this->assertSame('Changed', $result['a']['label']);
        $this->assertTrue($result['a']['modified']);
    }

    public function testLoadAndSaveReproduceContent() : void
    {
        $path = sys_get_temp_dir() . '/ImageIndexTest-' . uniqid('', true) . '.json';
        $file = JSONFile::factory($path);

        try {
            $data = array('a' => self::entryData('a'), 'b' => self::entryData('b'));
            $data['b']['forGallery'] = true;
            ImageIndex::fromArray($data)->save($file);

            $loaded = ImageIndex::load($file);
            $this->assertSame(2, $loaded->count());

            $contentBefore = $file->getContents();
            $loaded->save($file);

            $this->assertSame($contentBefore, $file->getContents());
            $this->assertSame(
                json_encode(ImageIndex::fromArray($data)->toArray()),
                json_encode($file->getData())
            );
        } finally {
            if(is_file($path)) {
                unlink($path);
            }
        }
    }
}
