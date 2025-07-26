<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\ArrayDataCollection;
use AppUtils\Collections\BaseStringPrimaryCollection;
use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\JSONFile;
use AppUtils\Interfaces\StringPrimaryRecordInterface;
use AppUtils\Microtime;
use AppUtils\Request;

/**
 * @method ImageInfo[] getAll()
 * @method ImageInfo getByID(string $id)
 */
class ImageCollection extends BaseStringPrimaryCollection
{
    private JSONFile $dataFile;

    public function __construct(JSONFile $dataFile)
    {
        $this->dataFile = $dataFile;
    }

    public function getDefaultID(): string
    {
        return $this->getAutoDefault();
    }

    public function deleteImage(ImageInfo $image) : void
    {
        unset($this->items[$image->getID()]);

        $image->getImageFile()->delete();
        $image->getSidecarFile()->delete();
        $image->getThumbnailFile()->delete();

        $data = $this->dataFile->getData();

        foreach($data as $index => $entry) {
            if($entry[ImageInfo::KEY_ID] === $image->getID()) {
                unset($data[$index]);
                break;
            }
        }

        $this->dataFile->putData(array_values($data));
    }

    public function saveImage(ImageInfo $image) : void
    {
        $data = $this->dataFile->getData();
        $id = $image->getID();

        if(isset($data[$id])) {
            $data[$id] = $image->serialize();
            $this->dataFile->putData($data);
            return;
        }

        throw new OrganizerException(
            'Cannot save inexistent image.',
            sprintf(
                'Cannot save image [%s] to collection, no such ID found in the data.',
            $id
            ),
            OrganizerException::ERROR_CANNOT_SAVE_INEXISTENT_IMAGE
        );
    }

    protected function registerItems(): void
    {
        foreach($this->dataFile->getData() as $entry) {
            $this->registerItem(ImageInfo::fromSerialized($entry));
        }
    }

    /**
     * @param ImageInfo $a
     * @param ImageInfo $b
     * @return int
     */
    protected function sortItems(StringPrimaryRecordInterface $a, StringPrimaryRecordInterface $b): int
    {
        return strnatcasecmp($a->getImageFile()->getPath(), $b->getImageFile()->getPath());
    }

    public function getFromRequest() : ?ImageInfo
    {
        $id = Request::getInstance()->registerParam('imageID')->setMD5()->get();
        if(!empty($id) && $this->idExists($id)) {
            return $this->getByID($id);
        }

        return null;
    }
}
