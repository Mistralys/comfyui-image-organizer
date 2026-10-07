<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata\Formats;

use AppUtils\ArrayDataCollection;
use AppUtils\FileHelper\FileInfo;
use Mistralys\ComfyUIOrganizer\ImageProperties;
use Mistralys\ComfyUIOrganizer\Metadata\BaseSidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\FlowInfo;
use Mistralys\ComfyUIOrganizer\Metadata\ImageMetadata;
use Mistralys\ComfyUIOrganizer\Metadata\LoRAUsage;
use Mistralys\ComfyUIOrganizer\Metadata\SamplerInfo;
use Mistralys\ComfyUIOrganizer\Metadata\UpscaleInfo;

/**
 * The structured sidecar: a JSON file with the `image`, `prompt`
 * and `sampler` objects, plus optional `model`, `flow`, `upscale`
 * and `loras` sections. Every section and key is optional; what is
 * missing is `null` in the metadata and absent from the property bag.
 */
class StructuredSidecarFormat extends BaseSidecarFormat
{
    public const string FORMAT_ID = 'structured';
    public const int PRIORITY = 100;

    private const string KEY_IMG_WIDTH = 'imgWidth';
    private const string KEY_IMG_HEIGHT = 'imgHeight';
    private const string KEY_UPSCALE_FACTOR = 'upscaleFactor';
    private const string KEY_WORKFLOW_VERSION = 'workflowVersion';
    private const string KEY_FLOW_TYPE = 'flowType';
    private const string KEY_IS_PREVIEW = 'isPreview';

    public function getID() : string
    {
        return self::FORMAT_ID;
    }

    public function getPriority() : int
    {
        return self::PRIORITY;
    }

    /**
     * `x-nr1_00001_.png` pairs with `x-nr1-meta.json` or `x-nr1-meta_00001.json`;
     * a name without the trailing counter pairs with `<name>-meta.json` only.
     */
    public function getSidecarCandidates(FileInfo $image) : array
    {
        // Not getFolder(): FolderInfo rejects a folder name that looks like a file name, e.g. `SD1.5`.
        $folder = dirname($image->getPath());
        $name = $image->getBaseName();

        if(preg_match('/^(.+)_(\d+)_$/', $name, $matches) !== 1) {
            return array($folder.'/'.$name.'-meta.json');
        }

        return array(
            $folder.'/'.$matches[1].'-meta.json',
            $folder.'/'.$matches[1].'-meta_'.$matches[2].'.json'
        );
    }

    public function detect(ArrayDataCollection $data) : bool
    {
        return $this->isObjectSection($data, 'image')
            && $this->isObjectSection($data, 'prompt')
            && $this->isObjectSection($data, 'sampler');
    }

    public function parse(ArrayDataCollection $data) : ImageMetadata
    {
        $image = $this->readSection($data, 'image');
        $prompt = $this->readSection($data, 'prompt');
        $sampler = $this->readSection($data, 'sampler');
        $upscale = $this->readSection($data, 'upscale');
        $flow = $this->readSection($data, 'flow');

        $seed = $this->readScalarString($image, 'seed');
        $testNumber = $this->readInt($image, 'counter');
        $batchNumber = $this->readInt($image, 'batchNumber');
        $width = $this->readInt($image, 'width');
        $height = $this->readInt($image, 'height');
        $cfg = $this->readFloat($image, 'cfg');
        $testName = $this->readString($image, 'seriesName');
        $positive = $this->readString($prompt, 'positive');
        $negative = $this->readString($prompt, 'negative');
        $samplerName = $this->readString($sampler, 'name');
        $samplerSteps = $this->readInt($sampler, 'steps');
        $scheduler = $this->readString($sampler, 'scheduler');
        $upscaleFactor = $this->readFloat($upscale, 'factor');
        $isUpscaled = $this->readBool($upscale, 'isUpscaled');
        $flowVersion = $this->readInt($flow, 'version');
        $flowType = $this->readString($flow, 'typeName');
        $isPreview = $this->readBool($flow, 'isPreview');

        $loras = $this->parseLoRAs($data);

        // The LoRA keys come first, so that a LoRA named like a core key cannot override it.
        $bag = array();

        foreach((array)$loras as $lora)
        {
            if($lora->getStrength() !== null) {
                $bag[$lora->getID()] = $this->normalizeFloat($lora->getStrength());
            }
        }

        $values = array(
            ImageProperties::KEY_TEST_NAME => $testName,
            ImageProperties::KEY_TEST_NUMBER => $testNumber,
            ImageProperties::KEY_IMG_BATCH_NR => $batchNumber,
            ImageProperties::KEY_SEED => $seed,
            ImageProperties::KEY_CFG => $cfg === null ? null : $this->normalizeFloat($cfg),
            self::KEY_IMG_WIDTH => $width,
            self::KEY_IMG_HEIGHT => $height,
            ImageProperties::KEY_PROMPT_POSITIVE => $positive,
            ImageProperties::KEY_PROMPT_NEGATIVE => $negative,
            ImageProperties::KEY_SAMPLER => $samplerName,
            ImageProperties::KEY_SAMPLER_STEPS => $samplerSteps,
            ImageProperties::KEY_SCHEDULER => $scheduler,
            self::KEY_UPSCALE_FACTOR => $isUpscaled === true && $upscaleFactor !== null ? $this->normalizeFloat($upscaleFactor) : null,
            self::KEY_WORKFLOW_VERSION => $flowVersion,
            self::KEY_FLOW_TYPE => $flowType,
            self::KEY_IS_PREVIEW => $isPreview === null ? null : ($isPreview ? 'true' : 'false'),
        );

        foreach($values as $key => $value)
        {
            if($value !== null) {
                $bag[$key] = (string)$value;
            }
        }

        return new ImageMetadata(
            formatID: self::FORMAT_ID,
            modelName: $this->readString($this->readSection($data, 'model'), 'prettyName'),
            seed: $seed,
            width: $width,
            height: $height,
            cfg: $cfg,
            positivePrompt: $positive,
            negativePrompt: $negative,
            testName: $testName,
            testNumber: $testNumber,
            batchNumber: $batchNumber,
            folderName: $this->readString($image, 'folderName'),
            filePrefix: $this->readString($image, 'filePrefix'),
            embedFlow: $this->readBool($image, 'embedFlow'),
            loraTriggers: $this->readString($data, 'loraTriggers'),
            flow: new FlowInfo(
                typeName: $flowType,
                version: $flowVersion,
                preview: $isPreview,
                negativePromptEnabled: $this->readBool($flow, 'isNegativePromptEnabled')
            ),
            sampler: new SamplerInfo(
                name: $samplerName,
                scheduler: $scheduler,
                steps: $samplerSteps,
                previewSteps: $this->readInt($sampler, 'previewSteps'),
                refinerSteps: $this->readInt($sampler, 'refinerSteps')
            ),
            upscale: new UpscaleInfo(
                factor: $upscaleFactor,
                steps: $this->readInt($upscale, 'steps'),
                calculatedFactor: $this->readFloat($upscale, 'calculatedFactor'),
                upscaled: $isUpscaled
            ),
            loras: $loras,
            indexProperties: $bag,
            rawData: $data->getData()
        );
    }

    /**
     * @return LoRAUsage[]|null `null` without a `loras` section.
     */
    private function parseLoRAs(ArrayDataCollection $data) : ?array
    {
        $section = $data->getKey('loras');

        if(!is_array($section)) {
            return null;
        }

        $result = array();

        foreach($section as $id => $entry)
        {
            // Scalar entries such as `_collectedTriggerWords` are not LoRAs.
            if(!is_array($entry)) {
                continue;
            }

            $item = ArrayDataCollection::create($entry);

            $result[] = new LoRAUsage(
                (string)$id,
                $this->readFloat($item, 'strength'),
                $this->readString($item, 'triggerWords')
            );
        }

        return $result;
    }

    /**
     * A missing or non-object section reads as an empty one.
     */
    private function readSection(ArrayDataCollection $data, string $key) : ArrayDataCollection
    {
        $value = $data->getKey($key);

        return ArrayDataCollection::create(is_array($value) ? $value : array());
    }

    /**
     * Integers are accepted as well, for values like the seed that
     * are numbers in the sidecar but strings in the property bag.
     */
    private function readScalarString(ArrayDataCollection $data, string $key) : ?string
    {
        $value = $data->getKey($key);

        if(is_int($value)) {
            return (string)$value;
        }

        return is_string($value) ? $value : null;
    }
}
