<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\BaseException;

class OrganizerException extends BaseException
{
    public const int ERROR_CANNOT_SAVE_INEXISTENT_IMAGE = 179401;
    public const int ERROR_CANNOT_MOVE_FILE_EXISTS = 179402;
}
