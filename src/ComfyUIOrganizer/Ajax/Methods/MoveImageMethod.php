<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Ajax\Methods;

use Mistralys\ComfyUIOrganizer\Ajax\BaseImageMethod;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\OrganizerApp;

/**
 * @property OrganizerApp $application
 */
class MoveImageMethod extends BaseImageMethod
{
    public const string METHOD_NAME = 'MoveImage';
    const string REQUEST_PARAM_FOLDER_NAME = 'folderName';
    public const int ERROR_EMPTY_FOLDER_NAME = 179601;

    public function getID(): string
    {
        return self::METHOD_NAME;
    }

    protected function processImage(ImageInfo $image): never
    {
        $folderName = $this->request->registerParam(self::REQUEST_PARAM_FOLDER_NAME)->setRegex('/^[A-Za-z0-9_-]+$/')->getString();

        if(empty($folderName)) {
            $this->sendError('No valid folder name specified.', self::ERROR_EMPTY_FOLDER_NAME);
        }

        $image->moveToFolder($folderName);

        $this->sendSuccess(
            'Image successfully moved to ['.$folderName.'].',
            $image->serialize()
        );
    }
}
