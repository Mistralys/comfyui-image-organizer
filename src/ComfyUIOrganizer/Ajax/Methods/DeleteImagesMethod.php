<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Ajax\Methods;

use Mistralys\ComfyUIOrganizer\ImageCollection;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\OrganizerApp;
use Mistralys\X4\UI\Ajax\BaseAjaxMethod;

/**
 * Deletes several images in one request. IDs that are unknown are skipped.
 *
 * @property OrganizerApp $application
 */
class DeleteImagesMethod extends BaseAjaxMethod
{
    public const string METHOD_NAME = 'DeleteImages';
    public const string REQUEST_PARAM_IMAGE_IDS = 'imageIDs';
    public const string RESPONSE_DELETED = 'deleted';

    private ImageCollection $collection;

    public function getID(): string
    {
        return self::METHOD_NAME;
    }

    protected function init(): void
    {
    }

    protected function preProcess(): void
    {
        $this->collection = $this->application->createImageCollection();
    }

    protected function _process(): never
    {
        // Comma-separated MD5 IDs; sent via POST to stay clear of URL length limits.
        $raw = $this->request->registerParam(self::REQUEST_PARAM_IMAGE_IDS)->getString();

        /** @var ImageInfo[] $images */
        $images = array();

        foreach(array_unique(explode(',', (string)$raw)) as $id) {
            $id = trim($id);
            if(preg_match('/^[a-f0-9]{32}$/i', $id) === 1 && $this->collection->idExists($id)) {
                $images[] = $this->collection->getByID($id);
            }
        }

        $deleted = array();
        foreach($images as $image) {
            $deleted[] = $image->getID();
        }

        $this->collection->deleteImages($images);

        $this->sendSuccess('Images deleted successfully.', array(self::RESPONSE_DELETED => $deleted));
    }
}
