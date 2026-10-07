<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata;

/**
 * Immutable record of one LoRA used for an image.
 */
final class LoRAUsage
{
    public function __construct(
        private readonly string $id,
        private readonly ?float $strength = null,
        private readonly ?string $triggerWords = null
    )
    {
    }

    public function getID() : string
    {
        return $this->id;
    }

    public function getStrength() : ?float
    {
        return $this->strength;
    }

    public function getTriggerWords() : ?string
    {
        return $this->triggerWords;
    }
}
