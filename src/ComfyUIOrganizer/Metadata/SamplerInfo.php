<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata;

/**
 * Immutable sampler details of an image. A field is `null`
 * when the sidecar does not carry the information.
 */
final class SamplerInfo
{
    public function __construct(
        private readonly ?string $name = null,
        private readonly ?string $scheduler = null,
        private readonly ?int $steps = null,
        private readonly ?int $previewSteps = null,
        private readonly ?int $refinerSteps = null
    )
    {
    }

    public function getName() : ?string
    {
        return $this->name;
    }

    public function getScheduler() : ?string
    {
        return $this->scheduler;
    }

    public function getSteps() : ?int
    {
        return $this->steps;
    }

    public function getPreviewSteps() : ?int
    {
        return $this->previewSteps;
    }

    public function getRefinerSteps() : ?int
    {
        return $this->refinerSteps;
    }
}
