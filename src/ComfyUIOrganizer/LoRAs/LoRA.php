<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\LoRAs;

use AppUtils\Interfaces\StringPrimaryRecordInterface;

class LoRA implements StringPrimaryRecordInterface
{
    private string $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getID(): string
    {
        return $this->id;
    }
}
