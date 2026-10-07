<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata;

/**
 * Immutable flow details of an image. A field is `null`
 * when the sidecar does not carry the information.
 */
final class FlowInfo
{
    public function __construct(
        private readonly ?string $typeName = null,
        private readonly ?int $version = null,
        private readonly ?bool $preview = null,
        private readonly ?bool $negativePromptEnabled = null
    )
    {
    }

    public function getTypeName() : ?string
    {
        return $this->typeName;
    }

    public function getVersion() : ?int
    {
        return $this->version;
    }

    public function isPreview() : ?bool
    {
        return $this->preview;
    }

    public function isNegativePromptEnabled() : ?bool
    {
        return $this->negativePromptEnabled;
    }
}
