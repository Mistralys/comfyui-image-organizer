<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata;

use AppUtils\FileHelper\FileInfo;

/**
 * Read-only census of how the sidecars of a set of images resolve and parse.
 *
 * It flags sidecars that no format recognizes and parse errors nobody
 * expected, so schema drift on the real image folder shows up early.
 *
 * It holds only the registry and stays read-only: no reference to the index types or `ImageAnalyzer`,
 * no read of `data/images.json`, no file writes. Only `MetadataException` is caught per image; any
 * other throwable aborts the census.
 */
class MetadataVerifier
{
    public function __construct(private readonly MetadataRegistry $registry)
    {
    }

    /**
     * Lists hold the file names of the images. A missing model name is reported
     * but is not a failure, since it is a known state of legacy sidecars.
     *
     * @param FileInfo[] $images
     * @return array{formats: array<string,int>, noSidecar: string[], unrecognized: string[], noModelName: string[], failures: string[]}
     */
    public function census(array $images) : array
    {
        $result = array(
            'formats' => array(),
            'noSidecar' => array(),
            'unrecognized' => array(),
            'noModelName' => array(),
            'failures' => array()
        );

        foreach($images as $image)
        {
            $name = $image->getName();
            $resolution = $this->registry->resolve($image);
            $sidecar = $resolution->getSidecar();

            if($sidecar === null)
            {
                if($resolution->getUnrecognizedCandidates() === array()) {
                    $result['noSidecar'][] = $name;
                } else {
                    $result['unrecognized'][] = $name;
                }

                continue;
            }

            try
            {
                $metadata = $sidecar->getFormat()->parse($sidecar->getData());
            }
            catch (MetadataException $e)
            {
                if($e->getCode() === MetadataException::ERROR_NO_MODEL_NAME) {
                    $result['noModelName'][] = $name;
                } else {
                    $result['failures'][] = $name;
                }

                continue;
            }

            $formatID = $metadata->getFormatID();
            $result['formats'][$formatID] = ($result['formats'][$formatID] ?? 0) + 1;
        }

        ksort($result['formats']);

        return $result;
    }
}
