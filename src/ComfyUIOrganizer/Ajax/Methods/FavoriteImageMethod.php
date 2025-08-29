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
class FavoriteImageMethod extends BaseImageMethod
{
    public const string METHOD_NAME = 'FavoriteImage';
    const string REQUEST_PARAM_FAVORITE = 'favorite';

    public function getID(): string
    {
        return self::METHOD_NAME;
    }

    protected function processImage(ImageInfo $image): never
    {
        $favorite = $this->request->getBool(self::REQUEST_PARAM_FAVORITE);

        $image->prop()->setFavorite($favorite);

        $this->collection->save();

        $this->sendSuccess(
            'Favorite successfully set to ['.ConvertHelper::bool2string($favorite, true).'].',
            array(
                self::REQUEST_PARAM_FAVORITE => $favorite
            )
        );
    }
}
