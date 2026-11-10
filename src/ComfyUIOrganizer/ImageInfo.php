<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\ArrayDataCollection;
use AppUtils\ClassHelper;
use AppUtils\ConvertHelper;
use AppUtils\ConvertHelper\JSONConverter;
use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\FolderInfo;
use AppUtils\FileHelper\JSONFile;
use AppUtils\ImageHelper;
use AppUtils\Interfaces\StringPrimaryRecordInterface;
use AppUtils\Microtime;
use AppUtils\OutputBuffering;
use AppUtils\Request;
use AppUtils\StringHelper;
use Closure;
use Mistralys\ComfyUIOrganizer\Metadata\ImageMetadata;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataException;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataRegistry;
use Mistralys\ComfyUIOrganizer\Pages\ImageDetails;
use Mistralys\X4\UI\Page\BasePage;
use Mistralys\X4\UI\UserInterface;
use function AppLocalize\t;
use function AppLocalize\pt;
use const Mistralys\ComfyUIOrganizer\Config\APP_WEBROOT_URL;

class ImageInfo implements StringPrimaryRecordInterface
{
    public const string KEY_PROPERTIES = 'properties';
    public const string KEY_CHECKPOINT = 'checkpoint';
    public const string KEY_ID = 'id';
    public const string KEY_IMAGE_FILE = 'imageFile';
    public const string KEY_DATE = 'date';
    public const string KEY_LABEL = 'label';
    public const string KEY_SIDECAR_FILE = 'sidecarFile';
    public const string KEY_IMAGE_SIZE = 'imageSize';
    public const string KEY_MODIFIED = 'modified';

    /**
     * Name suffix of the upscaled version of an image, which sits beside the image.
     * Such a file has no sidecar and is never indexed; see {@see self::resolveUpscaledPath()}.
     */
    public const string UPSCALED_SUFFIX = '_upscaled';

    private FileInfo $imageFile;
    private Microtime $date;
    private string $checkpoint;
    private ImageProperties $properties;
    private string $id;
    private JSONFile $sidecarFile;

    /**
     * @var array{width:int, height:int}
     */
    private array $imageSize;
    private string $label;

    /**
     * @param string $id
     * @param FileInfo $imageFile
     * @param JSONFile $sidecarFile
     * @param Microtime $date
     * @param string $checkpoint
     * @param bool $modified
     * @param string $label
     * @param array{width:int, height:int} $imageSize
     * @param ArrayDataCollection $properties
     */
    public function __construct(
        string $id,
        FileInfo $imageFile,
        JSONFile $sidecarFile,
        Microtime $date,
        string $checkpoint,
        bool $modified,
        string $label,
        array $imageSize,
        ArrayDataCollection $properties
    ) {
        $this->id = $id;
        $this->imageFile = $imageFile;
        $this->sidecarFile = $sidecarFile;
        $this->imageSize = $imageSize;
        $this->date = $date;
        $this->label = $label;
        $this->modified = $modified;
        $this->checkpoint = $checkpoint;
        $this->properties = new ImageProperties($properties, $this->onPropertiesModified(...));
    }

    public function prop() : ImageProperties
    {
        return $this->properties;
    }

    /**
     * @param array<string, string|int|float|bool> $params
     */
    public function getViewDetailsURL(array $params=array()) : string
    {
        $params[BasePage::REQUEST_PARAM_PAGE] = ImageDetails::URL_NAME;
        $params[ImageCollection::REQUEST_PARAM_IMAGE_ID] = $this->getID();

        return Request::getInstance()
            ->setBaseURL(APP_WEBROOT_URL)
            ->buildURL($params);
    }

    public function injectJS(UserInterface $ui,  string $objName) : void
    {
        $ui->addJSHead(sprintf(
            "%s.RegisterImage('%s', %s, %s);",
            $objName,
            $this->getID(),
            $this->prop()->getTestNumber(),
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
            (string)$this->prop()->getSeed(),
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
     * Moves the image, its sidecar file and its {@see self::getSiblingFiles()} to the specified
     * folder, and updates the properties to reflect the new folder name.
     *
     * @param string $folderName
     * @return $this
     * @throws OrganizerException {@see OrganizerException::ERROR_CANNOT_MOVE_FILE_EXISTS} if any target file exists; nothing is overwritten.
     */
    public function moveToFolder(string $folderName) : self
    {
        if ($folderName === $this->prop()->getFolderName()) {
            return $this;
        }

        $targetFolder = FolderInfo::factory($this->getImageFile()->getFolder()->getParentFolder() . '/' . $folderName);

        $newImageFile = $this->resolveTargetFile($targetFolder, $this->imageFile);
        $newSidecarFile = ClassHelper::requireObjectInstanceOf(JSONFile::class, $this->resolveTargetFile($targetFolder, $this->sidecarFile));

        // Resolved before anything is copied, so a clash leaves everything in place.
        $siblingMoves = array();
        foreach($this->getSiblingFiles() as $sibling) {
            $siblingMoves[] = array($sibling, $this->resolveTargetFile($targetFolder, $sibling));
        }

        $this->imageFile->copyTo($newImageFile);
        $this->sidecarFile->copyTo($newSidecarFile);

        foreach($siblingMoves as list($oldSibling, $newSibling)) {
            $oldSibling->copyTo($newSibling);
        }

        // Update the folder name in the properties.
        $this->prop()->setFolderName($folderName);

        $oldImage = $this->imageFile;
        $oldSidecar = $this->sidecarFile;

        $this->imageFile = $newImageFile;
        $this->sidecarFile = $newSidecarFile;

        OrganizerApp::create()->createImageCollection()->save();

        // Do this last, so that the image is not deleted if the save fails.
        $oldImage->delete();
        $oldSidecar->delete();

        foreach($siblingMoves as list($oldSibling)) {
            $oldSibling->delete();
        }

        return $this;
    }

    /**
     * Copies the upscaled version of the image when there is one, otherwise the image itself.
     */
    public function copyToOutput() : void
    {
        $outputFile = FileInfo::factory(sprintf(
            '%s/%s',
            OUTPUT_FOLDER,
            $this->getOutputFileName()
        ));

        if($outputFile->exists()) {
            $outputFile->delete();
        }

        $source = $this->isUpscaled() ? $this->getUpscaledFile() : $this->imageFile;

        $source->copyTo($outputFile);
    }

    public function getOutputFileName() : string
    {
        $label = $this->getLabel();
        if(empty($label)) {
            $label = $this->getID();
        }

        return sprintf(
            '%s-%s-%s.png',
            ConvertHelper::transliterate($label),
            $this->prop()->getTestNumber(),
            $this->prop()->getBatchNumber()
        );
    }

    /**
     * @return $this
     */
    public function save() : self
    {
        OrganizerApp::create()->createImageCollection()->save();
        return $this;
    }

    private function onPropertiesModified() : void
    {
        $this->setDataChanged();
    }

    public static function fromEntry(ImageIndexEntry $entry) : ImageInfo
    {
        return new ImageInfo(
            $entry->getID(),
            FileInfo::factory($entry->getImageFile()),
            JSONFile::factory($entry->getSidecarFile()),
            Microtime::createFromString($entry->getDate()),
            $entry->getCheckpoint(),
            $entry->isModified(),
            $entry->getLabel(),
            $entry->getImageSize(),
            ArrayDataCollection::create($entry->getProperties())
        );
    }

    /**
     * Known fields only; unknown index keys live on the stored entry.
     */
    public function toEntry() : ImageIndexEntry
    {
        return new ImageIndexEntry(
            $this->getID(),
            $this->imageFile->getPath(),
            $this->sidecarFile->getPath(),
            $this->date->getISODate(),
            $this->checkpoint,
            $this->modified,
            $this->label,
            $this->imageSize,
            $this->properties->serialize()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize() : array
    {
        return $this->toEntry()->toArray();
    }

    public function getID(): string
    {
        return $this->id;
    }

    /**
     * Whether the upscaled version of the image exists on disk; read live, never stored.
     */
    public function isUpscaled(): bool
    {
        return $this->getUpscaledFile()->exists();
    }

    public function getUpscaledFile() : FileInfo
    {
        return FileInfo::factory(self::resolveUpscaledPath($this->imageFile->getPath()));
    }

    /**
     * `x_00001_.png` gives `x_00001_upscaled.png`, `x.png` gives `x_upscaled.png`.
     */
    public static function resolveUpscaledPath(string $imagePath) : string
    {
        return dirname($imagePath).'/'.rtrim(pathinfo($imagePath, PATHINFO_FILENAME), '_').self::UPSCALED_SUFFIX.'.png';
    }

    public static function isUpscaledFileName(string $fileName) : bool
    {
        return str_ends_with(strtolower($fileName), self::UPSCALED_SUFFIX.'.png');
    }

    /**
     * The files that belong to the image beside its PNG and sidecar, as far as they exist:
     * the upscaled version, the PSD and JPG edits and the website image.
     * They move and get deleted with the image.
     *
     * @return FileInfo[]
     */
    public function getSiblingFiles() : array
    {
        return array_values(array_filter(
            array($this->getUpscaledFile(), $this->getPSDFile(), $this->getJPGFile(), $this->getWebsiteImage()),
            static fn(FileInfo $file) : bool => $file->exists()
        ));
    }

    public function getImageFile(): FileInfo
    {
        return $this->imageFile;
    }

    public function getPSDFile() : FileInfo
    {
        return FileInfo::factory(str_replace('.png', '.psd', $this->imageFile->getPath()));
    }

    public function getJPGFile() : FileInfo
    {
        return FileInfo::factory(str_replace('.png', '.jpg', $this->imageFile->getPath()));
    }

    public function getWebsiteImage() : FileInfo
    {
        $path = str_replace('_.png', '.png', $this->imageFile->getPath());
        $path = str_replace('.png', '_websiteImage.png', $path);
        return FileInfo::factory($path);
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

    private ?ImageMetadata $metadata = null;

    // TODO: Neither the browser nor any page calls this yet; only ImageInfoTest does.
    /**
     * Parses the sidecar on first access and keeps the result for this
     * instance. Reading it never touches the index or the changed flag.
     *
     * @return ImageMetadata
     * @throws MetadataException If the sidecar is missing or not recognized.
     */
    public function getMetadata() : ImageMetadata
    {
        if($this->metadata === null) {
            $this->metadata = MetadataRegistry::getInstance()->parseFile($this->sidecarFile);
        }

        return $this->metadata;
    }

    public function getLabel() : string
    {
        return $this->label;
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

    public static function createThumbnail(FileInfo $sourceImage, FileInfo $thumbnailFile): void
    {
        $thumbnailFile->getFolder()->create();

        $helper = ImageHelper::createFromFile($sourceImage);
        $helper->resampleByWidth(self::THUMBNAIL_WIDTH);
        $helper->setQuality(82);
        $helper->sharpen(18);
        $helper->save($thumbnailFile->getPath());
    }

    public function setLabel(string $label) : self
    {
        $this->label = $label;
        $this->setDataChanged();
        return $this;
    }

    private bool $modified = false;

    public bool $dataChanged = false;

    private function setDataChanged() : void
    {
        // Set the modified flag if any data has changed,
        // to protect the image from being overwritten by the
        // indexer.
        $this->modified = true;

        $this->dataChanged = true;
    }

    public function isDataChanged() : bool
    {
        return $this->dataChanged;
    }

    public function getUpscalingBadge() : string
    {
        OutputBuffering::start();

        if($this->isUpscaled()) {
            ?>
            <span class="badge text-bg-success"><?php echo mb_strtoupper(t('Upscaled')) ?></span>
            <?php
        } else {
            ?>
            <span class="badge text-bg-secondary"><?php echo mb_strtoupper(t('Regular')) ?></span>
            <?php
        }

        return OutputBuffering::get();
    }

    public function getEditedBadge() : string
    {
        OutputBuffering::start();

        if($this->getPSDFile()->exists()) {
            ?>
            <span class="badge text-bg-success"><?php echo mb_strtoupper(t('Edited')) ?></span>
            <?php
        }

        return OutputBuffering::get();
    }

    public function getWebsiteBadge() : string
    {
        if(!$this->prop()->isForWebsite()) {
            return '';
        }

        if(!empty($this->prop()->getWebsiteImageID())) {
            $statusClass = 'success';
            $statusIcon = '✓';
            $statusTitle = t('The image has been copied to the website.');
        } else if($this->getWebsiteImage()->exists()) {
            $statusClass = 'warning';
            $statusIcon = '↑';
            $statusTitle = t('Ready to copy to the website, dedicated image exists.');
        } else {
            $statusClass = 'danger';
            $statusIcon = '✗';
            $statusTitle = t('No dedicated image exists yet for the website.');
        }

        OutputBuffering::start();

        ?>
        <span class="badge bg-primary badge-toggle badge-for-website toggle-<?php if($this->prop()->isForWebsite()) { echo 'enabled'; } else { echo 'disabled'; } ?>" 
              style="margin-right: 0;"
              title="<?php pt('This image is marked to be used on the website.') ?>"
        >
            <?php echo mb_strtoupper(t('Website')) ?>
        </span><span class="badge bg-<?php echo $statusClass ?> badge-toggle" 
                title="<?php echo $statusTitle ?>"
        >
            <?php echo $statusIcon ?>
        </span>
        <?php


        return OutputBuffering::get();
    }
}
