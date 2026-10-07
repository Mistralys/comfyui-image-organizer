<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests;

use Mistralys\ComfyUIOrganizer\ImageIndexer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImageIndexerTest extends TestCase
{
    /**
     * @return array<string,array{0:mixed,1:string,2:string}>
     */
    public static function checkpointProvider() : array
    {
        return array(
            'non-empty string wins' => array('model.safetensors', 'fallback', 'model.safetensors'),
            'explicit empty string is kept' => array('', 'fallback', ''),
            'null falls back' => array(null, 'fallback', 'fallback'),
            'int falls back' => array(5, 'fallback', 'fallback'),
            'array falls back' => array(array('a'), 'fallback', 'fallback'),
            'empty fallback' => array(null, '', ''),
        );
    }

    #[DataProvider('checkpointProvider')]
    public function testResolveCheckpoint(mixed $sidecarValue, string $fallback, string $expected) : void
    {
        $this->assertSame($expected, ImageIndexer::resolveCheckpoint($sidecarValue, $fallback));
    }
}
