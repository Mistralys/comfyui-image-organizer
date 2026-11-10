<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\Metadata;

use AppUtils\Microtime;
use Mistralys\ComfyUIOrganizer\Metadata\FlowInfo;
use Mistralys\ComfyUIOrganizer\Metadata\ImageMetadata;
use Mistralys\ComfyUIOrganizer\Metadata\LoRAUsage;
use Mistralys\ComfyUIOrganizer\Metadata\SamplerInfo;
use Mistralys\ComfyUIOrganizer\Metadata\UpscaleInfo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[Group('metadata')]
final class ImageMetadataTest extends TestCase
{
    public function testEmptyMetadataReturnsNulls() : void
    {
        $metadata = new ImageMetadata(formatID: 'test');

        $this->assertSame('test', $metadata->getFormatID());
        $this->assertNull($metadata->getDate());
        $this->assertNull($metadata->getModelName());
        $this->assertNull($metadata->getVaeName());
        $this->assertNull($metadata->getClipNames());
        $this->assertNull($metadata->getSeed());
        $this->assertNull($metadata->getWidth());
        $this->assertNull($metadata->getHeight());
        $this->assertNull($metadata->getCFG());
        $this->assertNull($metadata->getPositivePrompt());
        $this->assertNull($metadata->getNegativePrompt());
        $this->assertNull($metadata->getTestName());
        $this->assertNull($metadata->getTestNumber());
        $this->assertNull($metadata->getBatchNumber());
        $this->assertNull($metadata->getFolderName());
        $this->assertNull($metadata->getFilePrefix());
        $this->assertNull($metadata->isEmbedFlow());
        $this->assertNull($metadata->getLoRATriggers());
        $this->assertNull($metadata->getLoRAs());
        $this->assertSame(array(), $metadata->getIndexProperties());
        $this->assertSame(array(), $metadata->getRawData());

        $flow = $metadata->getFlow();
        $this->assertNull($flow->getTypeName());
        $this->assertNull($flow->getVersion());
        $this->assertNull($flow->isPreview());
        $this->assertNull($flow->isNegativePromptEnabled());

        $sampler = $metadata->getSampler();
        $this->assertNull($sampler->getName());
        $this->assertNull($sampler->getScheduler());
        $this->assertNull($sampler->getSteps());
        $this->assertNull($sampler->getPreviewSteps());
        $this->assertNull($sampler->getRefinerSteps());

        $upscale = $metadata->getUpscale();
        $this->assertNull($upscale->getFactor());
        $this->assertNull($upscale->getSteps());
        $this->assertNull($upscale->getCalculatedFactor());
        $this->assertNull($upscale->isUpscaled());
        $this->assertNull($upscale->getModelName());
    }

    public function testSuppliedValuesAreReturned() : void
    {
        $date = Microtime::createFromString('2026-01-02 03:04:05');
        $lora = new LoRAUsage('faetastic', 0.3, 'fae');

        $metadata = new ImageMetadata(
            formatID: 'test',
            date: $date,
            modelName: 'model',
            seed: '123',
            width: 1024,
            cfg: 6.2,
            embedFlow: false,
            flow: new FlowInfo(typeName: 'FLUX1', version: 32, preview: false, negativePromptEnabled: true),
            sampler: new SamplerInfo(name: 'euler', steps: 20, refinerSteps: 0),
            upscale: new UpscaleInfo(factor: 4.0, upscaled: false),
            loras: array($lora),
            indexProperties: array('seed' => '123'),
            rawData: array('a' => 1)
        );

        $this->assertSame($date, $metadata->getDate());
        $this->assertSame('model', $metadata->getModelName());
        $this->assertSame('123', $metadata->getSeed());
        $this->assertSame(1024, $metadata->getWidth());
        $this->assertNull($metadata->getHeight());
        $this->assertSame(6.2, $metadata->getCFG());
        $this->assertFalse($metadata->isEmbedFlow());
        $this->assertSame('FLUX1', $metadata->getFlow()->getTypeName());
        $this->assertSame(32, $metadata->getFlow()->getVersion());
        $this->assertTrue($metadata->getFlow()->isNegativePromptEnabled());
        $this->assertSame('euler', $metadata->getSampler()->getName());
        $this->assertSame(0, $metadata->getSampler()->getRefinerSteps());
        $this->assertFalse($metadata->getUpscale()->isUpscaled());
        $this->assertSame(array($lora), $metadata->getLoRAs());
        $this->assertSame('faetastic', $lora->getID());
        $this->assertSame(0.3, $lora->getStrength());
        $this->assertSame('fae', $lora->getTriggerWords());
        $this->assertSame(array('seed' => '123'), $metadata->getIndexProperties());
        $this->assertSame(array('a' => 1), $metadata->getRawData());
    }

    public function testNoLoRAsIsDistinguishableFromUnknown() : void
    {
        $this->assertSame(array(), (new ImageMetadata(formatID: 'test', loras: array()))->getLoRAs());
        $this->assertNull((new ImageMetadata(formatID: 'test'))->getLoRAs());
    }

    /**
     * @return array<string,array{0:class-string}>
     */
    public static function valueObjectProvider() : array
    {
        return array(
            'ImageMetadata' => array(ImageMetadata::class),
            'FlowInfo' => array(FlowInfo::class),
            'SamplerInfo' => array(SamplerInfo::class),
            'UpscaleInfo' => array(UpscaleInfo::class),
            'LoRAUsage' => array(LoRAUsage::class),
        );
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('valueObjectProvider')]
    public function testValueObjectIsFinalAndImmutable(string $class) : void
    {
        $reflection = new ReflectionClass($class);

        $this->assertTrue($reflection->isFinal());

        foreach($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly(), sprintf('%s::$%s is not readonly.', $class, $property->getName()));
        }

        foreach($reflection->getMethods() as $method) {
            $this->assertStringStartsNotWith('set', $method->getName(), sprintf('%s has a setter.', $class));
        }
    }
}
