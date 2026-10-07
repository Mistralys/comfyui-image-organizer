<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata;

use AppUtils\FileHelper\JSONFile;

/**
 * Outcome of looking for the sidecar of one image.
 */
final class SidecarResolution
{
    /**
     * @param ResolvedSidecar|null $sidecar
     * @param JSONFile[] $unrecognizedCandidates Existing candidate files that no format recognized.
     */
    public function __construct(
        private readonly ?ResolvedSidecar $sidecar = null,
        private readonly array $unrecognizedCandidates = array()
    )
    {
    }

    public function getSidecar() : ?ResolvedSidecar
    {
        return $this->sidecar;
    }

    public function isResolved() : bool
    {
        return $this->sidecar !== null;
    }

    /**
     * @return JSONFile[]
     */
    public function getUnrecognizedCandidates() : array
    {
        return $this->unrecognizedCandidates;
    }
}
