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
class SetForWebsiteMethod extends BaseImageMethod
{
    public const string METHOD_NAME = 'SetForWebsite';
    const string REQUEST_PARAM_FOR_WEBSITE = 'forWebsite';

    public function getID(): string
    {
        return self::METHOD_NAME;
    }

    protected function processImage(ImageInfo $image): never
    {
        $enabled = $this->request->getBool(self::REQUEST_PARAM_FOR_WEBSITE);

        $image->prop()->setForWebsite($enabled);

        $this->collection->save();

        $this->sendSuccess(
            'ForWebsite successfully set to ['.ConvertHelper::bool2string($enabled, true).'].',
            array(
                self::REQUEST_PARAM_FOR_WEBSITE => $enabled
            )
        );
    }
}
