<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\Collections\BaseStringPrimaryCollection;
use AppUtils\ConvertHelper\JSONConverter;
use AppUtils\FileHelper\JSONFile;
use AppUtils\Interfaces\StringPrimaryRecordInterface;
use AppUtils\JSHelper;
use AppUtils\Request;
use Mistralys\ComfyUIOrganizer\Ajax\BaseImageMethod;
use Mistralys\ComfyUIOrganizer\Ajax\Methods\CopyToOutputMethod;
use Mistralys\ComfyUIOrganizer\Ajax\Methods\DeleteImageMethod;
use Mistralys\ComfyUIOrganizer\Ajax\Methods\FavoriteImageMethod;
use Mistralys\ComfyUIOrganizer\Ajax\Methods\MoveImageMethod;
use Mistralys\ComfyUIOrganizer\Ajax\Methods\SetCardSizeMethod;
use Mistralys\ComfyUIOrganizer\Ajax\Methods\SetForGalleryMethod;
use Mistralys\ComfyUIOrganizer\Ajax\Methods\SetForWebsiteMethod;
use Mistralys\ComfyUIOrganizer\Ajax\Methods\SetLabelMethod;
use Mistralys\ComfyUIOrganizer\Ajax\Methods\SetUpscaledImageMethod;
use Mistralys\X4\UI\UserInterface;

/**
 * @method ImageInfo[] getAll()
 * @method ImageInfo getByID(string $id)
 */
class ImageCollection extends BaseStringPrimaryCollection
{
    const string REQUEST_PARAM_IMAGE_ID = 'imageID';
    private JSONFile $dataFile;
    /**
     * @var array<int|string,mixed>
     */
    private array $data;

    public function __construct(JSONFile $dataFile)
    {
        $this->dataFile = $dataFile;
        $this->data = $dataFile->getData();
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

        foreach($this->data as $index => $entry) {
            if($entry[ImageInfo::KEY_ID] === $image->getID()) {
                unset($this->data[$index]);
                break;
            }
        }

        foreach($image->findLowResVersions() as $lowResVersion) {
            $this->deleteImage($lowResVersion);
        }

        $this->save();
    }

    public function save() : void
    {
        foreach($this->getAll() as $image) {
            if($image->isDataChanged()) {
                $this->data[$image->getID()] = $image->serialize();
            }
        }

        $this->dataFile->putData($this->data);
    }

    /**
     * @var array<string,ImageInfo> $deleted
     */
    private array $missing = array();

    public function injectJS(UserInterface $ui, string $baseURL) : string
    {
        $objName = 'IB'.JSHelper::nextElementID();

        $ui->addInternalJS('ImageBrowser.js');
        $ui->addInternalJS('ImageHandler.js');
        $ui->addInternalJS('UI.js');

        $ui->addJSHead(sprintf(
            "const %s = new ImageBrowser('%s', %s);",
            $objName,
            $baseURL,
            JSONConverter::var2json(array(
                'deleteImage' => DeleteImageMethod::METHOD_NAME,
                'favoriteImage' => FavoriteImageMethod::METHOD_NAME,
                'setUpscaledImage' => SetUpscaledImageMethod::METHOD_NAME,
                'moveImage' => MoveImageMethod::METHOD_NAME,
                'setCardSize' => SetCardSizeMethod::METHOD_NAME,
                'setForGallery' => SetForGalleryMethod::METHOD_NAME,
                'setForWebsite' => SetForWebsiteMethod::METHOD_NAME,
                'setLabel' => SetLabelMethod::METHOD_NAME,
                'copyToOutput' => CopyToOutputMethod::METHOD_NAME,
                'imageID' => self::REQUEST_PARAM_IMAGE_ID,
                'payloadFavorite' => BaseImageMethod::REQUEST_PARAM_FAVORITE,
                'payloadForGallery' => BaseImageMethod::REQUEST_PARAM_FOR_GALLERY,
                'payloadForWebsite' => BaseImageMethod::REQUEST_PARAM_FOR_WEBSITE,
            ))
        ));

        return $objName;
    }

    /**
     * Removes all images in the index that were found to be missing on the disk.
     *
     * @return $this
     */
    public function removeMissingImages() : self
    {
        foreach($this->getMissingImages() as $image) {
            $this->deleteImage($image);
        }

        return $this;
    }

    protected function registerItems(): void
    {
        $this->missing = array();

        foreach($this->data as $entry)
        {
            $image = ImageInfo::fromSerialized($entry);

            // Automatic cleanup of images that no longer exist.
            if(!$image->getImageFile()->exists()) {
                $this->missing[$image->getID()] = $image;
                continue;
            }

            $this->registerItem($image);
        }
    }

    /**
     * Gets all missing images that were found in the index file,
     * if any.
     *
     * @return array<string,ImageInfo>
     */
    public function getMissingImages(): array
    {
        $this->initItems();

        return $this->missing;
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
        $id = Request::getInstance()->registerParam(self::REQUEST_PARAM_IMAGE_ID)->setMD5()->get();
        if(!empty($id) && $this->idExists($id)) {
            return $this->getByID($id);
        }

        return null;
    }

    public function getFolderNames() : array
    {
        $result = array();

        foreach($this->getAll() as $image) {
            $folderName = $image->prop()->getFolderName();
            if (!empty($folderName) && !in_array($folderName, $result)) {
                $result[] = $folderName;
            }
        }

        usort($result, 'strnatcasecmp');

        return $result;
    }
}
