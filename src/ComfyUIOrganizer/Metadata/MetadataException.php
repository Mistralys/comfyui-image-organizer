<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata;

use Mistralys\ComfyUIOrganizer\OrganizerException;

/**
 * Codes are in the `183xxx` range; allocate new ones from it.
 */
class MetadataException extends OrganizerException
{
    public const int ERROR_NO_MODEL_NAME = 183001;
    public const int ERROR_INVALID_STRUCTURE = 183002;
}
