<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\LoRAs;

use AppUtils\FileHelper\JSONFile;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRA;
use Mistralys\ComfyUIOrganizer\OrganizerException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LoRATest extends TestCase
{
    /**
     * @return array<string,mixed>
     */
    private static function nestedEntry() : array
    {
        return array(
            'category' => 'Styles',
            'label' => 'Two Models',
            'models' => array(
                'SDXL 1.0' => array(
                    'source' => 'https://example.com/sdxl',
                    'file' => 'two-sdxl.safetensors',
                    'triggerWords' => array('alpha', 'shared')
                ),
                'FLUX' => array(
                    'file' => 'two-flux.safetensors',
                    'triggerWords' => array('shared', 'beta'),
                    'notes' => 'Flux notes',
                    'weight' => '0.8'
                )
            )
        );
    }

    public function testNestedShape() : void
    {
        $lora = LoRA::fromJSONEntry('two', self::nestedEntry());

        $this->assertSame('two', $lora->getID());
        $this->assertSame('Two Models', $lora->getLabel());
        $this->assertSame('Styles', $lora->getCategory());

        $models = $lora->getModels();
        $this->assertCount(2, $models);

        $this->assertSame('SDXL 1.0', $models[0]->getModelName());
        $this->assertSame('two-sdxl.safetensors', $models[0]->getFile());
        $this->assertSame('https://example.com/sdxl', $models[0]->getSource());
        $this->assertSame(array('alpha', 'shared'), $models[0]->getTriggerWords());

        $this->assertSame('FLUX', $models[1]->getModelName());
        $this->assertSame('two-flux.safetensors', $models[1]->getFile());
        $this->assertSame('', $models[1]->getSource());
        $this->assertSame('Flux notes', $models[1]->getNotes());
        $this->assertSame('0.8', $models[1]->getWeight());

        $this->assertSame(array('SDXL 1.0', 'FLUX'), $lora->getModelNames());
        $this->assertSame('https://example.com/sdxl', $lora->getSource());
        $this->assertSame('Flux notes', $lora->getNotes());
        $this->assertSame('0.8', $lora->getWeight());
    }

    public function testFlatShape() : void
    {
        $lora = LoRA::fromJSONEntry('flat', array(
            'category' => 'XXX',
            'label' => 'Flat',
            'model' => 'SDXL 1.0',
            'source' => 'https://example.com/flat',
            'file' => 'flat.safetensors',
            'triggerWords' => array('flat')
        ));

        $models = $lora->getModels();
        $this->assertCount(1, $models);
        $this->assertSame('SDXL 1.0', $models[0]->getModelName());
        $this->assertSame('flat.safetensors', $models[0]->getFile());
        $this->assertSame(array('SDXL 1.0'), $lora->getModelNames());
        $this->assertSame(array('flat.safetensors'), $lora->getFiles());
        $this->assertSame('flat', $lora->getTriggerWordsFlattened());
    }

    public function testMissingOptionalKeys() : void
    {
        $lora = LoRA::fromJSONEntry('bare', array('label' => 'Bare'));

        $this->assertSame('Bare', $lora->getLabel());
        $this->assertSame('', $lora->getCategory());
        $this->assertSame('', $lora->getSource());
        $this->assertSame('', $lora->getNotes());
        $this->assertSame('', $lora->getWeight());
        $this->assertSame(array(), $lora->getTriggerWords());
        $this->assertSame(array(), $lora->getFiles());
        $this->assertSame(array(), $lora->getModelNames());
    }

    public function testTriggerWordsAreDeduplicatedUnion() : void
    {
        $lora = LoRA::fromJSONEntry('two', self::nestedEntry());

        $this->assertSame(array('alpha', 'shared', 'beta'), $lora->getTriggerWords());
        $this->assertSame('alpha, shared, beta', $lora->getTriggerWordsFlattened());
    }

    public function testGetFilesSkipsEmpty() : void
    {
        $entry = self::nestedEntry();
        $entry['models'] = array(
            'SDXL 1.0' => array('file' => ''),
            'FLUX' => array('file' => 'only.safetensors')
        );

        $lora = LoRA::fromJSONEntry('two', $entry);

        $this->assertSame(array('only.safetensors'), $lora->getFiles());
    }

    public function testLabelLinkedSourceUsesFirstModelWithSource() : void
    {
        $entry = self::nestedEntry();
        $entry['models'] = array(
            'A' => array('source' => ''),
            'B' => array('source' => 'https://example.com/b')
        );

        $this->assertStringContainsString(
            'https://example.com/b',
            LoRA::fromJSONEntry('x', $entry)->getLabelLinkedSource()
        );
    }

    /**
     * @return array<string,array{0:mixed}>
     */
    public static function nonObjectProvider() : array
    {
        return array(
            'string' => array('text'),
            'int' => array(5),
            'null' => array(null),
        );
    }

    #[DataProvider('nonObjectProvider')]
    public function testNonObjectEntryThrows(mixed $entry) : void
    {
        try {
            LoRA::fromJSONEntry('broken', $entry);
            $this->fail('No exception thrown.');
        } catch (OrganizerException $e) {
            $this->assertSame(OrganizerException::ERROR_INVALID_LORA_ENTRY, $e->getCode());
        }
    }

    public function testTrackedLoraFileMatchesRawJson() : void
    {
        $file = JSONFile::factory(__DIR__ . '/../../data/loras/loras.json');
        if(!$file->exists()) {
            $this->markTestSkipped('data/loras/loras.json is not available.');
        }

        $exposesFileAndSource = false;

        foreach($file->getData() as $id => $raw)
        {
            $this->assertIsArray($raw);
            $this->assertIsArray($raw['models'] ?? null, 'Entry ' . $id);

            $lora = LoRA::fromJSONEntry((string)$id, $raw);
            $models = $lora->getModels();

            $this->assertCount(count($raw['models']), $models, 'Entry ' . $id);

            $index = 0;
            foreach($raw['models'] as $modelName => $rawModel)
            {
                $this->assertIsArray($rawModel);
                $model = $models[$index++];

                $this->assertSame((string)$modelName, $model->getModelName());
                $this->assertSame($rawModel['file'] ?? '', $model->getFile(), 'Entry ' . $id);
                $this->assertSame($rawModel['source'] ?? '', $model->getSource(), 'Entry ' . $id);
                $this->assertSame($rawModel['triggerWords'] ?? array(), $model->getTriggerWords(), 'Entry ' . $id);

                if($model->getFile() !== '' && $model->getSource() !== '') {
                    $exposesFileAndSource = true;
                }
            }
        }

        $this->assertTrue($exposesFileAndSource);
    }
}
