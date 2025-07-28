<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\ArrayDataCollection;
use AppUtils\ClassHelper;
use AppUtils\ConvertHelper\JSONConverter;
use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\FolderInfo;
use AppUtils\FileHelper\JSONFile;
use AppUtils\ImageHelper;
use AppUtils\Interfaces\StringPrimaryRecordInterface;
use AppUtils\Microtime;
use AppUtils\Request;
use Closure;
use Mistralys\ComfyUIOrganizer\Pages\ImageDetails;
use Mistralys\X4\UI\Page\BasePage;
use Mistralys\X4\UI\UserInterface;
use const Mistralys\ComfyUIOrganizer\Config\APP_WEBROOT_URL;

class ImageInfo implements StringPrimaryRecordInterface
{
    public const string KEY_PROPERTIES = 'properties';
    public const string KEY_CHECKPOINT = 'checkpoint';
    public const string KEY_ID = 'id';
    public const string KEY_IMAGE_FILE = 'imageFile';
    public const string KEY_DATE = 'date';
    public const string KEY_SIDECAR_FILE = 'sidecarFile';
    public const string KEY_UPSCALED = 'upscaled';
    public const string KEY_IMAGE_SIZE = 'imageSize';
    public const string KEY_MODIFIED = 'modified';

    private FileInfo $imageFile;
    private Microtime $date;
    private string $checkpoint;
    private ImageProperties $properties;
    private string $id;
    private JSONFile $sidecarFile;
    private bool $upscaled;

    /**
     * @var array{width:int, height:int}
     */
    private array $imageSize;

    /**
     * @param string $id
     * @param FileInfo $imageFile
     * @param JSONFile $sidecarFile
     * @param Microtime $date
     * @param string $checkpoint
     * @param bool $upscaled
     * @param bool $modified
     * @param array{width:int, height:int} $imageSize
     * @param ArrayDataCollection $properties
     */
    public function __construct(string $id, FileInfo $imageFile, JSONFile $sidecarFile, Microtime $date, string $checkpoint, bool $upscaled, bool $modified, array $imageSize, ArrayDataCollection $properties)
    {
        $this->id = $id;
        $this->imageFile = $imageFile;
        $this->sidecarFile = $sidecarFile;
        $this->imageSize = $imageSize;
        $this->date = $date;
        $this->upscaled = $upscaled;
        $this->modified = $modified;
        $this->checkpoint = $checkpoint;
        $this->properties = new ImageProperties($properties, $this->onPropertiesModified(...));
    }

    public function prop() : ImageProperties
    {
        return $this->properties;
    }

    public function isFavorite() : bool
    {
        return $this->properties->isFavorite();
    }

    public function getViewDetailsURL() : string
    {
        return Request::getInstance()
            ->setBaseURL(APP_WEBROOT_URL)
            ->buildURL(array(
                BasePage::REQUEST_PARAM_PAGE => ImageDetails::URL_NAME,
                ImageCollection::REQUEST_PARAM_IMAGE_ID => $this->getID()
            ));
    }

    public function injectJS(UserInterface $ui,  string $objName) : void
    {
        $ui->addJSHead(sprintf(
            "%s.RegisterImage('%s', %s);",
            $objName,
            $this->getID(),
            JSONConverter::var2json($this->getSearchWords())
        ));
    }

    /**
     * Returns a list of words that can be used to search for this image.
     * The words are all lowercased.
     * @return string[]
     */
    public function getSearchWords() : array
    {
        $words = array(
            $this->getID(),
            $this->imageFile->getName(),
            $this->prop()->getSeed(),
            $this->imageFile->getFolder()->getName(),
            $this->checkpoint,
            $this->prop()->getTestName()
        );

        return array_map('mb_strtolower', $words);
    }

    private function resolveTargetFile(FolderInfo $targetFolder, FileInfo $sourceFile) : FileInfo
    {
        $targetFile = FileInfo::factory($targetFolder.'/'.$sourceFile->getName());

        if(!$targetFile->exists()) {
            return $targetFile;
        }

        throw new OrganizerException(
            'File already exists in the target folder.',
            sprintf(
                'Attempted to move image [%s] to folder [%s], but the file already exists at [%s].',
                $sourceFile->getPath(),
                $targetFolder->getPath(),
                $targetFile->getPath()
            ),
            OrganizerException::ERROR_CANNOT_MOVE_FILE_EXISTS
        );
    }

    /**
     * Moves the image and sidecar file to the specified folder,
     * and updates the properties to reflect the new folder name.
     *
     * @param string $folderName
     * @return $this
     */
    public function moveToFolder(string $folderName) : self
    {
        if($folderName === $this->prop()->getFolderName()) {
            return $this;
        }

        $targetFolder = FolderInfo::factory($this->getImageFile()->getFolder()->getParentFolder().'/'.$folderName);

        $newImageFile = $this->resolveTargetFile($targetFolder, $this->imageFile);
        $newSidecarFile = ClassHelper::requireObjectInstanceOf(JSONFile::class, $this->resolveTargetFile($targetFolder, $this->sidecarFile));

        $this->imageFile->copyTo($newImageFile);
        $this->sidecarFile->copyTo($newSidecarFile);

        // Update the folder name in the properties.
        $this->prop()->setFolderName($folderName);

        $oldImage = $this->imageFile;
        $oldSidecar = $this->sidecarFile;

        $this->imageFile = $newImageFile;
        $this->sidecarFile = $newSidecarFile;

        $this->save();

        // Do this last, so that the image is not deleted if the save fails.
        $oldImage->delete();
        $oldSidecar->delete();

        // Also move all the low-resolution versions of this image
        // in case this is an upscaled image. We do this because
        // the low-resolution versions are not shown in the UI.
        foreach($this->findLowResVersions() as $lowResImage) {
            $lowResImage->moveToFolder($folderName);
        }

        return $this;
    }

    /**
     * Returns a list of low-resolution versions of this image.
     * @return ImageInfo[]
     */
    public function findLowResVersions() : array
    {
        if(!$this->isUpscaled()) {
            return array();
        }

        $id = $this->getID();
        $result = array();
        foreach(OrganizerApp::create()->createImageCollection()->getAll() as $image) {
            $upscaled = $image->prop()->getUpscaledImage();
            if($upscaled !== null && $upscaled->getID() === $id) {
                $result[] = $image;
            }
        }

        return $result;
    }

    private function onPropertiesModified() : void
    {
        $this->setDataChanged();
    }

    public static function fromSerialized(mixed $entry) : ImageInfo
    {
        return new ImageInfo(
            $entry['id'],
            FileInfo::factory($entry[ImageInfo::KEY_IMAGE_FILE]),
            JSONFile::factory($entry[ImageInfo::KEY_SIDECAR_FILE]),
            Microtime::createFromString($entry[ImageInfo::KEY_DATE]),
            $entry[ImageInfo::KEY_CHECKPOINT],
            $entry[ImageInfo::KEY_UPSCALED] === true,
            $entry[ImageInfo::KEY_MODIFIED] ?? false,
            $entry[ImageInfo::KEY_IMAGE_SIZE],
            ArrayDataCollection::create($entry[ImageInfo::KEY_PROPERTIES])
        );
    }

    public function serialize() : array
    {
        return array(
            self::KEY_ID => $this->getID(),
            self::KEY_IMAGE_FILE => $this->imageFile->getPath(),
            self::KEY_SIDECAR_FILE => $this->sidecarFile->getPath(),
            self::KEY_DATE => $this->date->getISODate(),
            self::KEY_CHECKPOINT => $this->checkpoint,
            self::KEY_UPSCALED => $this->upscaled,
            self::KEY_IMAGE_SIZE => $this->imageSize,
            self::KEY_MODIFIED => $this->modified,
            self::KEY_PROPERTIES => $this->properties->serialize()
        );
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function isUpscaled(): bool
    {
        return $this->upscaled;
    }

    public function getImageFile(): FileInfo
    {
        return $this->imageFile;
    }

    /**
     * @return array{width: int, height: int}
     */
    public function getImageSize(): array
    {
        return $this->imageSize;
    }

    public function getSidecarFile(): JSONFile
    {
        return $this->sidecarFile;
    }

    public function getLabel() : string
    {
        return $this->imageFile->getBaseName();
    }

    public function getDate() : Microtime
    {
        return $this->date;
    }

    public function getCheckpoint() : string
    {
        return $this->checkpoint;
    }

    public function getProperties() : ImageProperties
    {
        return $this->properties;
    }

    public function getThumbnailURL() : string
    {
        return $this->getURL().'&thumbnail=yes';
    }

    public function getURL() : string
    {
        return APP_WEBROOT_URL.'/image.php?imageID='.$this->getID();
    }

    public function displayThumbnail() : never
    {
        $thumbnailFile = $this->getThumbnailFile();
        if(!$thumbnailFile->exists()) {
            $this->createThumbnail($thumbnailFile);
        }

        ImageHelper::displayImage($thumbnailFile->getPath());

        exit;
    }

    public const int THUMBNAIL_WIDTH = 540;

    public function getThumbnailFile() : FileInfo
    {
        return FileInfo::factory(sprintf(
            '%s/%s-%s.jpg',
            OrganizerApp::create()->getCacheFolder(),
            $this->getID(),
            self::THUMBNAIL_WIDTH
        ));
    }

    private function createThumbnail(FileInfo $thumbnailFile): void
    {
        $thumbnailFile->getFolder()->create();

        $helper = ImageHelper::createFromFile($this->imageFile);
        $helper->resampleByWidth(self::THUMBNAIL_WIDTH);
        $helper->setQuality(82);
        $helper->sharpen(18);
        $helper->save($thumbnailFile->getPath());
    }

    public function displayFullSize() : never
    {
        ImageHelper::displayImage($this->imageFile->getPath());

        exit;
    }

    public function setFavorite(bool $favorite): self
    {
        $this->properties->setFavorite($favorite);
        return $this;
    }

    public function save() : self
    {
        if($this->dataChanged)
        {
            // Set the modified flag if any data has changed,
            // to protect the image from being overwritten by the
            // indexer.
            $this->modified = true;

            OrganizerApp::create()->createImageCollection()->saveImage($this);
        }

        return $this;
    }

    private bool $modified = false;

    private bool $dataChanged = false;
    private function setDataChanged() : void
    {
        $this->dataChanged = true;
    }
}
