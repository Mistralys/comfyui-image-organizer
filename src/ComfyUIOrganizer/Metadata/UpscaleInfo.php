<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata;

/**
 * Immutable upscale details of an image. A field is `null`
 * when the sidecar does not carry the information.
 *
 * Note that a factor alone is only the configured setting;
 * {@see self::isUpscaled()} says whether the image was upscaled.
 */
final class UpscaleInfo
{
    public function __construct(
        private readonly ?float $factor = null,
        private readonly ?int $steps = null,
        private readonly ?float $calculatedFactor = null,
        private readonly ?bool $upscaled = null,
        private readonly ?string $modelName = null
    )
    {
    }

    public function getFactor() : ?float
    {
        return $this->factor;
    }

    public function getSteps() : ?int
    {
        return $this->steps;
    }

    public function getCalculatedFactor() : ?float
    {
        return $this->calculatedFactor;
    }

    public function isUpscaled() : ?bool
    {
        return $this->upscaled;
    }

    public function getModelName() : ?string
    {
        return $this->modelName;
    }
}
