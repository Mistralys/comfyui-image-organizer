<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata;

use AppUtils\ArrayDataCollection;
use AppUtils\FileHelper\JSONFile;

/**
 * A sidecar file together with the format that recognized it
 * and its decoded content.
 */
final class ResolvedSidecar
{
    public function __construct(
        private readonly JSONFile $sidecar,
        private readonly SidecarFormatInterface $format,
        private readonly ArrayDataCollection $data
    )
    {
    }

    public function getSidecar() : JSONFile
    {
        return $this->sidecar;
    }

    public function getFormat() : SidecarFormatInterface
    {
        return $this->format;
    }

    public function getData() : ArrayDataCollection
    {
        return $this->data;
    }
}
