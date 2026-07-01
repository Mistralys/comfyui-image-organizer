<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\LoRAs;

class LoRAReferenceRenderer
{
    private LoRAsCollection $collection;

    public function __construct()
    {
        $this->collection = LoRAsCollection::getInstance();
    }

    public function renderMarkdown() : string
    {
        $lines = array('# LoRA Reference', '');
    }
}
