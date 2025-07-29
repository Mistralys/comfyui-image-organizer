<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Ajax\Methods;

use Mistralys\ComfyUIOrganizer\OrganizerApp;
use Mistralys\ComfyUIOrganizer\Pages\ImageBrowser;
use Mistralys\X4\UI\Ajax\BaseAjaxMethod;

/**
 * @property OrganizerApp $application
 */
class SetCardSizeMethod extends BaseAjaxMethod
{
    public const string METHOD_NAME = 'SetCardSize';
    const string REQUEST_PARAM_CARD_SIZE = 'cardSize';

    public function getID(): string
    {
        return self::METHOD_NAME;
    }

    protected function init(): void
    {
    }

    protected function _process(): never
    {
        $sizes = ImageBrowser::getCardSizes();

        $size = $this->request->registerParam(self::REQUEST_PARAM_CARD_SIZE)->setEnum(array_keys($sizes))->getString();
        if(!empty($size)) {
            ImageBrowser::setCardSize($size);
        }

        $this->sendSuccess('Size saved.');
    }

    protected function preProcess(): void
    {
    }
}
