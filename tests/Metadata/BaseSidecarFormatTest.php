<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\Metadata;

use AppUtils\ArrayDataCollection;
use AppUtils\FileHelper\FileInfo;
use Mistralys\ComfyUIOrganizer\Metadata\BaseSidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\ImageMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('metadata')]
final class BaseSidecarFormatTest extends TestCase
{
    private ExposedSidecarFormat $format;

    protected function setUp() : void
    {
        $this->format = new ExposedSidecarFormat();
    }

    public function testReadersReturnNullForAbsentKey() : void
    {
        $data = ArrayDataCollection::create(array());

        $this->assertNull($this->format->string($data, 'a'));
        $this->assertNull($this->format->int($data, 'a'));
        $this->assertNull($this->format->float($data, 'a'));
        $this->assertNull($this->format->bool($data, 'a'));
    }

    public function testReadersReturnMatchingTypes() : void
    {
        $data = ArrayDataCollection::create(array('s' => 'x', 'i' => 5, 'f' => 1.5, 'b' => false));

        $this->assertSame('x', $this->format->string($data, 's'));
        $this->assertSame(5, $this->format->int($data, 'i'));
        $this->assertSame(1.5, $this->format->float($data, 'f'));
        $this->assertFalse($this->format->bool($data, 'b'));
    }

    public function testEmptyValuesAreNotNull() : void
    {
        $data = ArrayDataCollection::create(array('s' => '', 'i' => 0, 'f' => 0.0));

        $this->assertSame('', $this->format->string($data, 's'));
        $this->assertSame(0, $this->format->int($data, 'i'));
        $this->assertSame(0.0, $this->format->float($data, 'f'));
    }

    public function testReadersReturnNullForOtherTypes() : void
    {
        $data = ArrayDataCollection::create(array('s' => 5, 'i' => '5', 'f' => '1.5', 'b' => 1, 'a' => array()));

        $this->assertNull($this->format->string($data, 's'));
        $this->assertNull($this->format->int($data, 'i'));
        $this->assertNull($this->format->float($data, 'f'));
        $this->assertNull($this->format->bool($data, 'b'));
        $this->assertNull($this->format->string($data, 'a'));
    }

    public function testFloatReaderWidensIntegers() : void
    {
        $data = ArrayDataCollection::create(array('f' => 4));

        $this->assertSame(4.0, $this->format->float($data, 'f'));
    }

    /**
     * @return array<string,array{0:float|int,1:string}>
     */
    public static function normalizeProvider() : array
    {
        return array(
            'cfg noise' => array(6.200000000000001, '6.2'),
            'strength noise' => array(0.4000000000000001, '0.4'),
            'sum noise' => array(0.1 + 0.2, '0.3'),
            'whole float' => array(1.0, '1'),
            'integer' => array(4, '4'),
        );
    }

    #[DataProvider('normalizeProvider')]
    public function testNormalizeFloat(float|int $value, string $expected) : void
    {
        $this->assertSame($expected, $this->format->normalize($value));
    }
}

/**
 * Exposes the protected readers of the base class to the test.
 */
final class ExposedSidecarFormat extends BaseSidecarFormat
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

    public function string(ArrayDataCollection $data, string $key) : ?string
    {
        return $this->readString($data, $key);
    }

    public function int(ArrayDataCollection $data, string $key) : ?int
    {
        return $this->readInt($data, $key);
    }

    public function float(ArrayDataCollection $data, string $key) : ?float
    {
        return $this->readFloat($data, $key);
    }

    public function bool(ArrayDataCollection $data, string $key) : ?bool
    {
        return $this->readBool($data, $key);
    }

    public function normalize(float|int $value) : string
    {
        return $this->normalizeFloat($value);
    }
}
