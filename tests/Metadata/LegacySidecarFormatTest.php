<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\Metadata;

use AppUtils\ArrayDataCollection;
use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\JSONFile;
use Mistralys\ComfyUIOrganizer\ImageProperties;
use Mistralys\ComfyUIOrganizer\Metadata\Formats\LegacySidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('metadata')]
final class LegacySidecarFormatTest extends TestCase
{
    private const string FIXTURE_DATE = '01/09/2025 13:44:02';

    private LegacySidecarFormat $format;

    protected function setUp() : void
    {
        $this->format = new LegacySidecarFormat();
    }

    private static function fixturePath(string $name) : string
    {
        return __DIR__.'/../fixtures/sidecars/'.$name;
    }

    private static function loadFixture(string $name) : ArrayDataCollection
    {
        return ArrayDataCollection::create(JSONFile::factory(self::fixturePath($name))->getData());
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function synthetic(array $entry, string $key = self::FIXTURE_DATE) : ArrayDataCollection
    {
        return ArrayDataCollection::create(array($key => $entry));
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function legacyFixtureProvider() : array
    {
        $result = array();

        foreach((array)glob(self::fixturePath('legacy-*.json')) as $path)
        {
            $name = basename((string)$path);

            if(!str_ends_with($name, '.expected-bag.json')) {
                $result[$name] = array($name);
            }
        }

        return $result;
    }

    #[DataProvider('legacyFixtureProvider')]
    public function testBagMatchesPreChangeParser(string $fixture) : void
    {
        $expectedFile = self::fixturePath(str_replace('.json', '.expected-bag.json', $fixture));
        $expected = JSONFile::factory($expectedFile)->getData();

        $data = self::loadFixture($fixture);

        $this->assertTrue($this->format->detect($data));
        $this->assertSame($expected, $this->format->parse($data)->getIndexProperties());
    }

    public function testFixturesAreFound() : void
    {
        $this->assertArrayHasKey('legacy-two-date-keys.json', self::legacyFixtureProvider());
    }

    public function testSkipsWhenNoModelName() : void
    {
        $withoutModel = self::synthetic(array('custom_text' => '-sampler:euler'));
        $emptyCheckpoint = self::synthetic(array('checkpoint' => '', 'custom_text' => '-modelName:`m.safetensors`'));
        $noText = self::synthetic(array());

        foreach(array($withoutModel, $emptyCheckpoint, $noText) as $data)
        {
            $this->assertTrue($this->format->detect($data));

            try {
                $this->format->parse($data);
                $this->fail('Expected a MetadataException.');
            } catch (MetadataException $e) {
                $this->assertSame(MetadataException::ERROR_NO_MODEL_NAME, $e->getCode());
            }
        }
    }

    public function testNonDateFirstKeyThrowsInvalidStructure() : void
    {
        $data = self::synthetic(array('checkpoint' => 'model'), 'Microtime');

        $this->assertTrue($this->format->detect($data));

        try {
            $this->format->parse($data);
            $this->fail('Expected a MetadataException.');
        } catch (MetadataException $e) {
            $this->assertSame(MetadataException::ERROR_INVALID_STRUCTURE, $e->getCode());
        }
    }

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
        $this->assertSame($expected, LegacySidecarFormat::resolveCheckpoint($sidecarValue, $fallback));
    }

    public function testTypedGettersFromCheckpointFixture() : void
    {
        $metadata = $this->format->parse(self::loadFixture('legacy-checkpoint.json'));

        $this->assertSame('legacy', $metadata->getFormatID());
        $this->assertSame('nevolutionXLIL_v2Base', $metadata->getModelName());
        $this->assertSame('2025-08-29', $metadata->getDate()?->format('Y-m-d'));
        $this->assertNull($metadata->getLoRAs());
        $this->assertNull($metadata->getClipNames());
        $this->assertNull($metadata->getVaeName());
        $this->assertNull($metadata->getLoRATriggers());
        $this->assertNull($metadata->getFlow()->getVersion());
        $this->assertNull($metadata->getUpscale()->getFactor());
        $this->assertSame('4x-UltraSharp', $metadata->getUpscale()->getModelName());
    }

    public function testTypedGettersFromModelBagFixture() : void
    {
        $metadata = $this->format->parse(self::loadFixture('legacy-model-vae.json'));

        $this->assertSame('RealDream_flux1V1.safetensors', $metadata->getModelName());
        $this->assertSame('ae.safetensors', $metadata->getVaeName());
        $this->assertSame('clip_l.safetensors|t5xxl_fp16.safetensors', $metadata->getClipNames());
        $this->assertSame(1, $metadata->getFlow()->getVersion());
        $this->assertNull($metadata->getLoRAs());
    }

    public function testDateComesFromTheFirstKey() : void
    {
        $metadata = $this->format->parse(self::loadFixture('legacy-two-date-keys.json'));

        $this->assertSame('2025-09-30 10:31:22', $metadata->getDate()?->format('Y-m-d H:i:s'));
        $this->assertSame('flux_dev.safetensors', $metadata->getModelName());
    }

    public function testTypedGettersFromSyntheticData() : void
    {
        $data = self::synthetic(array(
            'checkpoint' => 'model',
            'custom_text' => '-upscaleFactor:2.5-workflowVersion:28-loraTriggers:`a, b`',
            'positive_prompt' => 'top positive',
            'negative_prompt' => 'top negative',
            'upscale_model' => '4x-Foo'
        ));

        $metadata = $this->format->parse($data);

        $this->assertSame('top positive', $metadata->getPositivePrompt());
        $this->assertSame('top negative', $metadata->getNegativePrompt());
        $this->assertSame(28, $metadata->getFlow()->getVersion());
        $this->assertSame(2.5, $metadata->getUpscale()->getFactor());
        $this->assertSame('4x-Foo', $metadata->getUpscale()->getModelName());
        $this->assertSame('a, b', $metadata->getLoRATriggers());
        $this->assertNull($metadata->getLoRAs());
        $this->assertSame($data->getData(), $metadata->getRawData());
    }

    public function testBagPromptsWinOverTopLevelFields() : void
    {
        $metadata = $this->format->parse(self::loadFixture('legacy-positive-prompt-top-level.json'));
        $bag = $metadata->getIndexProperties();

        $this->assertSame($bag[ImageProperties::KEY_PROMPT_POSITIVE], $metadata->getPositivePrompt());
        $this->assertSame($bag[ImageProperties::KEY_PROMPT_NEGATIVE], $metadata->getNegativePrompt());
    }

    public function testDetect() : void
    {
        foreach(array_keys(self::legacyFixtureProvider()) as $name) {
            $this->assertTrue($this->format->detect(self::loadFixture($name)), $name);
        }

        $rejected = array(
            'structured-flux-v32.json',
            'structured-no-flow-no-typename.json',
            'structured-preview.json',
            'structured-upscaled.json',
            'structured-v31-early.json',
            'workflow-export.json',
        );

        foreach($rejected as $name) {
            $this->assertFalse($this->format->detect(self::loadFixture($name)), $name);
        }
    }

    public function testDetectRejectsNonObjectFirstValue() : void
    {
        $this->assertFalse($this->format->detect(ArrayDataCollection::create(array())));
        $this->assertFalse($this->format->detect(ArrayDataCollection::create(array(self::FIXTURE_DATE => 'text'))));
        $this->assertFalse($this->format->detect(ArrayDataCollection::create(array(self::FIXTURE_DATE => array('a', 'b')))));
        $this->assertFalse($this->format->detect(ArrayDataCollection::create(array(self::FIXTURE_DATE => 5, 'x' => array('a' => 1)))));
    }

    public function testDetectIgnoresFurtherKeys() : void
    {
        $data = ArrayDataCollection::create(array(
            self::FIXTURE_DATE => array('checkpoint' => 'm'),
            'second' => 'scalar'
        ));

        $this->assertTrue($this->format->detect($data));
    }

    public function testIdentityAndSidecarCandidates() : void
    {
        $this->assertSame('legacy', $this->format->getID());
        $this->assertSame(200, $this->format->getPriority());

        $image = FileInfo::factory('/output/a.png.d/12-initial-seed1_01.png');

        $this->assertSame(
            array('/output/a.png.d/12-initial-seed1_01.json'),
            array_map(static fn(string $path) : string => str_replace('\\', '/', $path), $this->format->getSidecarCandidates($image))
        );
    }

    public function testVaeNameIsReadThroughTheConstant() : void
    {
        $data = self::synthetic(array('checkpoint' => 'm', 'custom_text' => '-VAEName:`x.safetensors`'));
        $metadata = $this->format->parse($data);

        $this->assertSame('x.safetensors', $metadata->getVaeName());
        $this->assertArrayHasKey(ImageProperties::KEY_VAE_NAME, $metadata->getIndexProperties());
    }
}
