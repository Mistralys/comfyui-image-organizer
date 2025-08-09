<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Ajax\Methods;

use Mistralys\ComfyUIOrganizer\Ajax\BaseImageMethod;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\OrganizerApp;

/**
 * @property OrganizerApp $application
 */
class SetLabelMethod extends BaseImageMethod
{
    public const string METHOD_NAME = 'SetLabel';
    const string REQUEST_PARAM_LABEL = 'label';
    public const int ERROR_EMPTY_LABEL = 180701;

    public function getID(): string
    {
        return self::METHOD_NAME;
    }

    protected function processImage(ImageInfo $image): never
    {
        $label = $this->request->registerParam(self::REQUEST_PARAM_LABEL)->setLabel()->getString();

        $image->setLabel($label);

        $this->collection->save();

        $this->sendSuccess(
            'Image label set successfully to ['.$label.'].',
            $image->serialize()
        );
    }
}
