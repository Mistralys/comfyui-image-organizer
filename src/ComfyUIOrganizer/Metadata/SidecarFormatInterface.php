<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata;

use AppUtils\ArrayDataCollection;
use AppUtils\FileHelper\FileInfo;

/**
 * One sidecar schema family: finds the candidate sidecar files of an
 * image, recognizes its own content and parses it into {@see ImageMetadata}.
 *
 * Implementations live in `Metadata/Formats/` and are discovered by a folder scan.
 */
interface SidecarFormatInterface
{
    public function getID() : string;

    /**
     * Lower numbers are tried first.
     */
    public function getPriority() : int;

    /**
     * @param FileInfo $image
     * @return string[] Candidate sidecar file paths, best match first.
     */
    public function getSidecarCandidates(FileInfo $image) : array;

    /**
     * Whether the decoded content of a candidate belongs to this format.
     *
     * The registry calls it for every format against every existing candidate and keeps
     * walking after a match, so it must be cheap and free of side effects. It takes
     * already decoded content and must not read files.
     */
    public function detect(ArrayDataCollection $data) : bool;

    /**
     * Takes already decoded content and must not read files.
     *
     * @param ArrayDataCollection $data
     * @return ImageMetadata
     * @throws MetadataException Never the base {@see \Mistralys\ComfyUIOrganizer\OrganizerException}.
     */
    public function parse(ArrayDataCollection $data) : ImageMetadata;
}
