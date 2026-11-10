<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata\Formats;

use AppUtils\ArrayDataCollection;
use AppUtils\ConvertHelper;
use AppUtils\FileHelper\FileInfo;
use AppUtils\Microtime;
use AppUtils\Microtime_Exception;
use Mistralys\ComfyUIOrganizer\ImageProperties;
use Mistralys\ComfyUIOrganizer\Metadata\BaseSidecarFormat;
use Mistralys\ComfyUIOrganizer\Metadata\FlowInfo;
use Mistralys\ComfyUIOrganizer\Metadata\ImageMetadata;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataException;
use Mistralys\ComfyUIOrganizer\Metadata\UpscaleInfo;

/**
 * The legacy sidecar: a JSON file whose first top-level key is the
 * generation date, holding the generation properties, among them a
 * `custom_text` string with `-name:value` pairs.
 */
class LegacySidecarFormat extends BaseSidecarFormat
{
    public const string FORMAT_ID = 'legacy';
    public const int PRIORITY = 200;

    private const string KEY_WORKFLOW_VERSION = 'workflowVersion';
    private const string KEY_UPSCALE_FACTOR = 'upscaleFactor';
    private const string KEY_LORA_TRIGGERS = 'loraTriggers';

    public function getID() : string
    {
        return self::FORMAT_ID;
    }

    public function getPriority() : int
    {
        return self::PRIORITY;
    }

    public function getSidecarCandidates(FileInfo $image) : array
    {
        return array(
            preg_replace('/\.png$/i', '', $image->getPath()).'.json'
        );
    }

    /**
     * Reads the first top-level key only, like the indexer always did:
     * multi-key sidecars exist and are indexed from their first key.
     */
    public function detect(ArrayDataCollection $data) : bool
    {
        // A structured sidecar and a workflow export can also hold an object as their first value.
        if($this->isObjectSection($data, 'image') && $this->isObjectSection($data, 'prompt') && $this->isObjectSection($data, 'sampler')) {
            return false;
        }

        if($this->isObjectSection($data, 'nodes') && $this->isObjectSection($data, 'links')) {
            return false;
        }

        $raw = $data->getData();

        if($raw === array()) {
            return false;
        }

        $first = reset($raw);

        if(!is_array($first)) {
            return false;
        }

        return $first === array() || !array_is_list($first);
    }

    /**
     * `getIndexProperties()` is the parsed `custom_text` bag, untouched, so that legacy indexing stays
     * byte-identical; the bag carries seed, CFG and size, while their typed getters stay `null`.
     * Parity quirk: a model named `"0"` counts as empty.
     *
     * @throws MetadataException {@see MetadataException::ERROR_NO_MODEL_NAME} for an empty model name,
     *         {@see MetadataException::ERROR_INVALID_STRUCTURE} when the first key is not a date string holding an object.
     */
    public function parse(ArrayDataCollection $data) : ImageMetadata
    {
        $raw = $data->getData();
        $date = key($raw);
        $entry = $date === null ? null : $raw[$date];

        if(!is_string($date) || !is_array($entry)) {
            throw new MetadataException(
                'Invalid legacy sidecar.',
                'The first top-level key of a legacy sidecar must be a date string holding an object.',
                MetadataException::ERROR_INVALID_STRUCTURE
            );
        }

        $properties = ArrayDataCollection::create($entry);
        $bag = $this->parseText($properties->getString('custom_text'));

        $modelName = self::resolveCheckpoint(
            $properties->getKey('checkpoint'),
            $bag[ImageProperties::KEY_MODEL_NAME] ?? ''
        );

        if(empty($modelName)) {
            throw new MetadataException(
                'No model name found.',
                'The legacy sidecar has neither a checkpoint nor a model name.',
                MetadataException::ERROR_NO_MODEL_NAME
            );
        }

        try
        {
            $generated = Microtime::createFromString(str_replace('/', '-', $date));
        }
        catch (Microtime_Exception $e)
        {
            throw new MetadataException(
                'Invalid legacy sidecar.',
                'The first top-level key of a legacy sidecar is not a date.',
                MetadataException::ERROR_INVALID_STRUCTURE,
                $e
            );
        }

        return new ImageMetadata(
            formatID: self::FORMAT_ID,
            date: $generated,
            modelName: $modelName,
            vaeName: $bag[ImageProperties::KEY_VAE_NAME] ?? null,
            clipNames: $bag[ImageProperties::KEY_CLIP_NAMES] ?? null,
            positivePrompt: $bag[ImageProperties::KEY_PROMPT_POSITIVE] ?? $this->readString($properties, 'positive_prompt'),
            negativePrompt: $bag[ImageProperties::KEY_PROMPT_NEGATIVE] ?? $this->readString($properties, 'negative_prompt'),
            loraTriggers: $bag[self::KEY_LORA_TRIGGERS] ?? null,
            flow: new FlowInfo(version: $this->bagInt($bag, self::KEY_WORKFLOW_VERSION)),
            upscale: new UpscaleInfo(
                factor: $this->bagFloat($bag, self::KEY_UPSCALE_FACTOR),
                modelName: $this->readString($properties, 'upscale_model')
            ),
            loras: null,
            indexProperties: $bag,
            rawData: $raw
        );
    }

    /**
     * A string sidecar value wins, even when empty; any other value falls back.
     *
     * @param mixed $sidecarValue
     * @param string $fallback
     * @return string
     */
    public static function resolveCheckpoint(mixed $sidecarValue, string $fallback) : string
    {
        if(is_string($sidecarValue)) {
            return $sidecarValue;
        }

        return $fallback;
    }

    /**
     * @param array<string,string> $bag
     */
    private function bagInt(array $bag, string $key) : ?int
    {
        return isset($bag[$key]) && is_numeric($bag[$key]) ? (int)$bag[$key] : null;
    }

    /**
     * @param array<string,string> $bag
     */
    private function bagFloat(array $bag, string $key) : ?float
    {
        return isset($bag[$key]) && is_numeric($bag[$key]) ? (float)$bag[$key] : null;
    }

    /**
     * @return array<string, string>
     */
    private function parseText(string $text) : array
    {
        if(empty($text)) {
            return array();
        }

        preg_match_all('/`([^`]*)`/', $text, $matches);

        $punctuation = array(
            '__HYPHEN__' => '-',
            '__COLON__' => ':'
        );

        foreach($matches[1] as $match) {
            $replace = str_replace(array_values($punctuation), array_keys($punctuation), $match);
            $text = str_replace($match, $replace, $text);
        }

        $properties = ConvertHelper::explodeTrim('-', $text);

        $result = array();
        foreach($properties as $propertyString) {
            $parts = explode(':', $propertyString);
            $name = $parts[0];
            $value = $parts[1] ?? '';
            $result[$name] = str_replace(array_keys($punctuation), array_values($punctuation), trim($value, '`'));
        }

        return $result;
    }
}
