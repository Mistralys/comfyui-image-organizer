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
use Mistralys\ComfyUIOrganizer\Ajax\Methods\SendToWebsiteMethod;
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
    private ImageIndex $index;

    /**
     * @param JSONFile $dataFile
     * @throws OrganizerException
     */
    public function __construct(JSONFile $dataFile)
    {
        $this->dataFile = $dataFile;
        $this->index = ImageIndex::load($dataFile);
    }

    public function getDefaultID(): string
    {
        return $this->getAutoDefault();
    }

    /**
     * Deletes the image, its sidecar, thumbnail and all linked low-resolution versions.
     * The index removal is persisted by the {@see self::save()} at the end.
     */
    public function deleteImage(ImageInfo $image) : void
    {
        unset($this->items[$image->getID()]);

        $image->getImageFile()->delete();
        $image->getSidecarFile()->delete();
        $image->getThumbnailFile()->delete();

        $this->index->remove($image->getID());

        foreach($image->findLowResVersions() as $lowResVersion) {
            $this->deleteImage($lowResVersion);
        }

        $this->save();
    }

    /**
     * Re-derives the index entry of every image whose data changed, keeping the unknown
     * keys of the stored entry, then writes the whole index, not only the changed entries.
     */
    public function save() : void
    {
        foreach($this->getAll() as $image) {
            if(!$image->isDataChanged()) {
                continue;
            }

            $entry = $image->toEntry();
            $stored = $this->index->get($image->getID());

            // Keeps the unknown keys of the stored entry.
            if($stored !== null) {
                $entry = $stored->withKnownFieldsFrom($entry);
            }

            $this->index->set($entry);
        }

        $this->index->save($this->dataFile);
    }

    /**
     * @var array<string,ImageInfo> $deleted
     */
    /** @var array<string, ImageInfo> */
    private array $missing = array();

    /**
     * Hands the AJAX method names to `js/ImageBrowser.js`. A method the browser UI calls must be
     * listed here and handled there. Each `METHOD_NAME` is the method's ID and must be unique.
     */
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
                'sendToWebsite' => SendToWebsiteMethod::METHOD_NAME,
                'imageID' => self::REQUEST_PARAM_IMAGE_ID,
                'payloadFavorite' => BaseImageMethod::REQUEST_PARAM_FAVORITE,
                'payloadForGallery' => BaseImageMethod::REQUEST_PARAM_FOR_GALLERY,
                'payloadForWebsite' => BaseImageMethod::REQUEST_PARAM_FOR_WEBSITE,
                'payloadLabel' => BaseImageMethod::REQUEST_PARAM_LABEL,
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

        foreach($this->index->getEntries() as $entry)
        {
            $image = ImageInfo::fromEntry($entry);

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

    /**
     * @return ImageInfo|null `null` when the image ID is absent, not an MD5 hash, or not in the collection.
     */
    public function getFromRequest() : ?ImageInfo
    {
        $id = Request::getInstance()->registerParam(self::REQUEST_PARAM_IMAGE_ID)->setMD5()->getString();
        if(!empty($id) && $this->idExists($id)) {
            return $this->getByID($id);
        }

        return null;
    }

    /**
     * @return string[]
     */
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
