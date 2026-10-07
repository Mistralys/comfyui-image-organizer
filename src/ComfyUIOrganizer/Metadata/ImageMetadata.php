<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata;

use AppUtils\Microtime;

/**
 * Immutable, format-independent result of parsing an image sidecar.
 *
 * Every scalar getter is nullable: `null` means "the sidecar does
 * not say", which is distinct from an empty value. Build it with
 * named arguments, so that a field a format does not supply is
 * `null` by construction.
 */
final class ImageMetadata
{
    private readonly FlowInfo $flow;
    private readonly SamplerInfo $sampler;
    private readonly UpscaleInfo $upscale;

    /**
     * @param string $formatID ID of the format that produced the metadata.
     * @param LoRAUsage[]|null $loras `null` when the format cannot say, `[]` when there are none.
     * @param array<string,string> $indexProperties The flat property bag persisted in the image index.
     * @param array<int|string,mixed> $rawData The decoded sidecar.
     */
    public function __construct(
        private readonly string $formatID,
        private readonly ?Microtime $date = null,
        private readonly ?string $modelName = null,
        private readonly ?string $vaeName = null,
        private readonly ?string $clipNames = null,
        private readonly ?string $seed = null,
        private readonly ?int $width = null,
        private readonly ?int $height = null,
        private readonly ?float $cfg = null,
        private readonly ?string $positivePrompt = null,
        private readonly ?string $negativePrompt = null,
        private readonly ?string $testName = null,
        private readonly ?int $testNumber = null,
        private readonly ?int $batchNumber = null,
        private readonly ?string $folderName = null,
        private readonly ?string $filePrefix = null,
        private readonly ?bool $embedFlow = null,
        private readonly ?string $loraTriggers = null,
        ?FlowInfo $flow = null,
        ?SamplerInfo $sampler = null,
        ?UpscaleInfo $upscale = null,
        private readonly ?array $loras = null,
        private readonly array $indexProperties = array(),
        private readonly array $rawData = array()
    )
    {
        $this->flow = $flow ?? new FlowInfo();
        $this->sampler = $sampler ?? new SamplerInfo();
        $this->upscale = $upscale ?? new UpscaleInfo();
    }

    public function getFormatID() : string
    {
        return $this->formatID;
    }

    public function getDate() : ?Microtime
    {
        return $this->date;
    }

    public function getModelName() : ?string
    {
        return $this->modelName;
    }

    public function getVaeName() : ?string
    {
        return $this->vaeName;
    }

    /**
     * The raw clip names string as stored in the sidecar.
     */
    public function getClipNames() : ?string
    {
        return $this->clipNames;
    }

    public function getSeed() : ?string
    {
        return $this->seed;
    }

    public function getWidth() : ?int
    {
        return $this->width;
    }

    public function getHeight() : ?int
    {
        return $this->height;
    }

    public function getCFG() : ?float
    {
        return $this->cfg;
    }

    public function getPositivePrompt() : ?string
    {
        return $this->positivePrompt;
    }

    public function getNegativePrompt() : ?string
    {
        return $this->negativePrompt;
    }

    public function getTestName() : ?string
    {
        return $this->testName;
    }

    public function getTestNumber() : ?int
    {
        return $this->testNumber;
    }

    public function getBatchNumber() : ?int
    {
        return $this->batchNumber;
    }

    public function getFolderName() : ?string
    {
        return $this->folderName;
    }

    public function getFilePrefix() : ?string
    {
        return $this->filePrefix;
    }

    public function isEmbedFlow() : ?bool
    {
        return $this->embedFlow;
    }

    /**
     * The raw trigger words string as stored in the sidecar.
     */
    public function getLoRATriggers() : ?string
    {
        return $this->loraTriggers;
    }

    public function getFlow() : FlowInfo
    {
        return $this->flow;
    }

    public function getSampler() : SamplerInfo
    {
        return $this->sampler;
    }

    public function getUpscale() : UpscaleInfo
    {
        return $this->upscale;
    }

    /**
     * @return LoRAUsage[]|null `null` when the format cannot say, `[]` when there are none.
     */
    public function getLoRAs() : ?array
    {
        return $this->loras;
    }

    /**
     * @return array<string,string>
     */
    public function getIndexProperties() : array
    {
        return $this->indexProperties;
    }

    /**
     * @return array<int|string,mixed>
     */
    public function getRawData() : array
    {
        return $this->rawData;
    }
}
