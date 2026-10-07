<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\Metadata;

use AppUtils\ArrayDataCollection;
use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\JSONFile;
use Mistralys\ComfyUIOrganizer\Metadata\Formats\StructuredSidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\ImageMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('metadata')]
final class StructuredSidecarFormatTest extends TestCase
{
    private StructuredSidecarFormat $format;

    protected function setUp() : void
    {
        $this->format = new StructuredSidecarFormat();
    }

    private static function fixturePath(string $name) : string
    {
        return __DIR__.'/../fixtures/sidecars/'.$name;
    }

    private static function loadFixture(string $name) : ArrayDataCollection
    {
        return ArrayDataCollection::create(JSONFile::factory(self::fixturePath($name))->getData());
    }

    private function parseFixture(string $name) : ImageMetadata
    {
        return $this->format->parse(self::loadFixture($name));
    }

    /**
     * A minimal structured sidecar with the three required sections.
     *
     * @param array<string,mixed> $sections
     */
    private static function synthetic(array $sections = array()) : ArrayDataCollection
    {
        return ArrayDataCollection::create(array_merge(
            array('image' => array(), 'prompt' => array(), 'sampler' => array()),
            $sections
        ));
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function structuredFixtureProvider() : array
    {
        $result = array();

        foreach((array)glob(self::fixturePath('structured-*.json')) as $path) {
            $name = basename((string)$path);
            $result[$name] = array($name);
        }

        return $result;
    }

    public function testFluxV32Mapping() : void
    {
        $metadata = $this->parseFixture('structured-flux-v32.json');

        $this->assertSame(
            array(
                'faetastic' => '0.3',
                'greg' => '0.4',
                'testName' => 'flux',
                'testNumber' => '2',
                'imgBatchNr' => '1',
                'seed' => '678193439927262',
                'imgCFG' => '1',
                'imgWidth' => '1600',
                'imgHeight' => '900',
                'promptPositive' => $metadata->getPositivePrompt(),
                'promptNegative' => '',
                'sampler' => 'euler',
                'samplerSteps' => '26',
                'scheduler' => 'simple',
                'workflowVersion' => '32',
                'flowType' => 'FLUX1',
                'isPreview' => 'false',
            ),
            $metadata->getIndexProperties()
        );

        $this->assertSame('structured', $metadata->getFormatID());
        $this->assertSame('FLUX 1 DEV', $metadata->getModelName());
        $this->assertArrayNotHasKey('flowVersion', $metadata->getIndexProperties());
    }

    public function testFloatNoiseIsNormalized() : void
    {
        $early = $this->parseFixture('structured-v31-early.json');

        $this->assertSame('6.2', $early->getIndexProperties()['imgCFG']);
        $this->assertSame('0.4', $early->getIndexProperties()['eyeDetailer']);

        $data = self::synthetic(array(
            'image' => array('cfg' => 4),
            'loras' => array(
                'sum' => array('strength' => 0.1 + 0.2),
                'whole' => array('strength' => 1.0),
                'integer' => array('strength' => 4),
            )
        ));

        $bag = $this->format->parse($data)->getIndexProperties();

        $this->assertSame('4', $bag['imgCFG']);
        $this->assertSame('0.3', $bag['sum']);
        $this->assertSame('1', $bag['whole']);
        $this->assertSame('4', $bag['integer']);
    }

    public function testCollectedTriggerWordsEntryIgnored() : void
    {
        $data = self::synthetic(array(
            'loras' => array(
                'one' => array('strength' => 0.5, 'name' => 'one', 'triggerWords' => 'abc'),
                '_collectedTriggerWords' => ', , ',
                'loraTriggers' => 'x, y',
            )
        ));

        $metadata = $this->format->parse($data);
        $loras = $metadata->getLoRAs();

        $this->assertNotNull($loras);
        $this->assertCount(1, $loras);
        $this->assertSame('one', $loras[0]->getID());
        $this->assertSame(0.5, $loras[0]->getStrength());
        $this->assertSame('abc', $loras[0]->getTriggerWords());
        $this->assertSame(array('one' => '0.5'), $metadata->getIndexProperties());
    }

    public function testNumericLoRAIDIsKeptAsString() : void
    {
        $data = self::synthetic(array('loras' => array('123' => array('strength' => 0.5))));

        $loras = $this->format->parse($data)->getLoRAs();

        $this->assertNotNull($loras);
        $this->assertSame('123', $loras[0]->getID());
    }

    public function testLoRAsNullWithoutLoRASection() : void
    {
        $this->assertNull($this->format->parse(self::synthetic())->getLoRAs());
        $this->assertSame(array(), $this->format->parse(self::synthetic(array('loras' => array())))->getLoRAs());
    }

    public function testFlowVersionWrittenToWorkflowVersionKey() : void
    {
        $v32 = $this->parseFixture('structured-flux-v32.json');
        $v31 = $this->parseFixture('structured-v31-early.json');
        $none = $this->parseFixture('structured-preview.json');

        $this->assertSame('32', $v32->getIndexProperties()['workflowVersion']);
        $this->assertSame(32, $v32->getFlow()->getVersion());
        $this->assertSame('31', $v31->getIndexProperties()['workflowVersion']);
        $this->assertSame(31, $v31->getFlow()->getVersion());
        $this->assertArrayNotHasKey('workflowVersion', $none->getIndexProperties());
        $this->assertNull($none->getFlow()->getVersion());
    }

    public function testEarlyV31WithoutModelAndTypeName() : void
    {
        $metadata = $this->parseFixture('structured-v31-early.json');
        $bag = $metadata->getIndexProperties();

        $this->assertNull($metadata->getModelName());
        $this->assertNull($metadata->getFlow()->getTypeName());
        $this->assertNull($metadata->getFlow()->isPreview());
        $this->assertArrayNotHasKey('flowType', $bag);
        $this->assertArrayNotHasKey('isPreview', $bag);
        $this->assertSame(', , , mad-cbrpnksplshrt, ', $metadata->getLoRATriggers());
        $this->assertTrue($metadata->isEmbedFlow());
    }

    public function testNoFlowTypeName() : void
    {
        $metadata = $this->parseFixture('structured-no-flow-no-typename.json');
        $bag = $metadata->getIndexProperties();

        $this->assertSame('SDXL AlbedoBase', $metadata->getModelName());
        $this->assertNull($metadata->getFlow()->getTypeName());
        $this->assertNull($metadata->getFlow()->getVersion());
        $this->assertFalse($metadata->getFlow()->isPreview());
        $this->assertTrue($metadata->getFlow()->isNegativePromptEnabled());
        $this->assertArrayNotHasKey('flowType', $bag);
        $this->assertArrayNotHasKey('workflowVersion', $bag);
        $this->assertSame('false', $bag['isPreview']);
        $this->assertNull($metadata->getLoRATriggers());
    }

    public function testMissingSectionsYieldNulls() : void
    {
        $metadata = $this->format->parse(self::synthetic());

        $this->assertNull($metadata->getModelName());
        $this->assertNull($metadata->getSeed());
        $this->assertNull($metadata->getCFG());
        $this->assertNull($metadata->getFlow()->getVersion());
        $this->assertNull($metadata->getSampler()->getName());
        $this->assertNull($metadata->getUpscale()->getFactor());
        $this->assertNull($metadata->getLoRAs());
        $this->assertSame(array(), $metadata->getIndexProperties());
    }

    public function testNonObjectSectionsAreTreatedAsMissing() : void
    {
        $data = self::synthetic(array('model' => 'text', 'flow' => 5, 'upscale' => null, 'loras' => 'x'));
        $metadata = $this->format->parse($data);

        $this->assertNull($metadata->getModelName());
        $this->assertNull($metadata->getFlow()->getTypeName());
        $this->assertNull($metadata->getLoRAs());
    }

    public function testPreviewFlagAndSteps() : void
    {
        $metadata = $this->parseFixture('structured-preview.json');

        $this->assertSame('true', $metadata->getIndexProperties()['isPreview']);
        $this->assertTrue($metadata->getFlow()->isPreview());
        $this->assertSame(11, $metadata->getSampler()->getPreviewSteps());
        $this->assertSame(20, $metadata->getSampler()->getSteps());
        $this->assertNull($metadata->getSampler()->getRefinerSteps());

        $refiner = $this->parseFixture('structured-v31-early.json');

        $this->assertSame(24, $refiner->getSampler()->getRefinerSteps());
        $this->assertNull($refiner->getSampler()->getPreviewSteps());
    }

    public function testUpscaleFactorOnlyWhenUpscaled() : void
    {
        $upscaled = $this->parseFixture('structured-upscaled.json');
        $configured = $this->parseFixture('structured-flux-v32.json');

        $this->assertSame('4', $upscaled->getIndexProperties()['upscaleFactor']);
        $this->assertArrayNotHasKey('upscaleFactor', $configured->getIndexProperties());
        $this->assertSame(4.0, $configured->getUpscale()->getFactor());
        $this->assertNull($configured->getUpscale()->isUpscaled());
    }

    public function testRawDataAndExtrasExposed() : void
    {
        $data = self::loadFixture('structured-upscaled.json');
        $metadata = $this->format->parse($data);

        $this->assertSame($data->getData(), $metadata->getRawData());
        $this->assertTrue($metadata->isEmbedFlow());
        $this->assertSame(4.0, $metadata->getUpscale()->getCalculatedFactor());
        $this->assertTrue($metadata->getUpscale()->isUpscaled());
        $this->assertSame(30, $metadata->getUpscale()->getSteps());
        $this->assertSame(656765794489516 . '', $metadata->getSeed());
        $this->assertSame(1600, $metadata->getWidth());
        $this->assertSame(904, $metadata->getHeight());
        $this->assertSame(3.2, $metadata->getCFG());
        $this->assertSame(170, $metadata->getTestNumber());
        $this->assertSame(1, $metadata->getBatchNumber());
        $this->assertSame('continued', $metadata->getTestName());
        $this->assertSame('AmazonBlonde-EpicRealism', $metadata->getFolderName());
        $this->assertSame('170-continued-seed656765794489516', $metadata->getFilePrefix());

        $loras = $metadata->getLoRAs();

        $this->assertNotNull($loras);
        $this->assertSame('neonCyberpunk', $loras[2]->getID());
        $this->assertSame('mad-cbrpnksplshrt', $loras[2]->getTriggerWords());
    }

    #[DataProvider('structuredFixtureProvider')]
    public function testDateIsNullAndParsingRaisesNoWarning(string $fixture) : void
    {
        $this->assertNull($this->parseFixture($fixture)->getDate());
    }

    /**
     * @return array<string,array{0:string,1:string[]}>
     */
    public static function candidateProvider() : array
    {
        return array(
            'counter' => array('/out/x-nr1_00001_.png', array('/out/x-nr1-meta.json', '/out/x-nr1-meta_00001.json')),
            'preview' => array('/out/x-nr1-preview_00001_.png', array('/out/x-nr1-preview-meta.json', '/out/x-nr1-preview-meta_00001.json')),
            'no counter' => array('/out/x-nr1.png', array('/out/x-nr1-meta.json')),
            'dots in folder' => array('/out/a.png.d/x_00002_.png', array('/out/a.png.d/x-meta.json', '/out/a.png.d/x-meta_00002.json')),
        );
    }

    /**
     * @param string[] $expected
     */
    #[DataProvider('candidateProvider')]
    public function testSidecarCandidates(string $imagePath, array $expected) : void
    {
        $candidates = array_map(
            static fn(string $path) : string => str_replace('\\', '/', $path),
            $this->format->getSidecarCandidates(FileInfo::factory($imagePath))
        );

        $this->assertSame($expected, $candidates);
    }

    public function testIdentity() : void
    {
        $this->assertSame('structured', $this->format->getID());
        $this->assertSame(100, $this->format->getPriority());
    }

    public function testDetect() : void
    {
        foreach(array_keys(self::structuredFixtureProvider()) as $name) {
            $this->assertTrue($this->format->detect(self::loadFixture($name)), $name);
        }

        $rejected = array(
            'legacy-checkpoint.json',
            'legacy-model-vae.json',
            'legacy-positive-prompt-top-level.json',
            'legacy-two-date-keys.json',
            'workflow-export.json',
        );

        foreach($rejected as $name) {
            $this->assertFalse($this->format->detect(self::loadFixture($name)), $name);
        }

        $this->assertFalse($this->format->detect(ArrayDataCollection::create(array('image' => array('a' => 1), 'prompt' => array('a' => 1)))));
        $this->assertFalse($this->format->detect(ArrayDataCollection::create(array('image' => 'x', 'prompt' => array('a' => 1), 'sampler' => array('a' => 1)))));
    }
}
