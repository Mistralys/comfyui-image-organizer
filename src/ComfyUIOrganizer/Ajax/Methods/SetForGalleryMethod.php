<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Ajax\Methods;

use AppUtils\ConvertHelper;
use Mistralys\ComfyUIOrganizer\Ajax\BaseImageMethod;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\OrganizerApp;

/**
 * @property OrganizerApp $application
 */
class SetForGalleryMethod extends BaseImageMethod
{
    public const string METHOD_NAME = 'SetForGallery';

    public function getID(): string
    {
        return self::METHOD_NAME;
    }

    protected function processImage(ImageInfo $image): never
    {
        $forGallery = $this->request->getBool(BaseImageMethod::REQUEST_PARAM_FOR_GALLERY);

        $image->prop()->setForGallery($forGallery);

        $this->collection->save();

        $this->sendSuccess(
            'Gallery successfully set to ['.ConvertHelper::bool2string($forGallery, true).'].'
        );
    }
}
