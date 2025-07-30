<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Pages;

use AppUtils\ConvertHelper;
use AppUtils\OutputBuffering;
use Mistralys\ComfyUIOrganizer\BaseOrganizerPage;
use Mistralys\ComfyUIOrganizer\ImageCollection;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\OrganizerApp;
use Mistralys\X4\UI\Icon;
use function AppLocalize\pt;
use function AppLocalize\pts;
use function AppLocalize\t;

class ImageBrowser extends BaseOrganizerPage
{
    public const string URL_NAME = 'image-browser';
    public const string REQUEST_PARAM_UPSCALED = 'upscaled';
    public const string REQUEST_PARAM_FAVORITES = 'favorites';
    public const string REQUEST_PARAM_GALLERY = 'gallery';
    public const string REQUEST_PARAM_FOLDER_NAME = 'folderName';
    const string REQUEST_VAR_CARD_SIZE = 'cardSize';
    const string CARD_SIZE_S = 's';
    const string CARD_SIZE_M = 'm';
    const string CARD_SIZE_L = 'l';
    const string CARD_SIZE_XL = 'xl';

    private ImageCollection $collection;
    private string $defaultSize;

    /**
     * @var array<string, string>
     */
    private array $cardSizes;
    private string $activeCardSize;

    public function getID(): string
    {
        return self::URL_NAME;
    }

    public function getTitle(): string
    {
        return t('Folder selection');
    }

    public function getSubtitle(): string
    {
        return t('Select an image folder to organize');
    }

    public function getAbstract(): string
    {
        return '';
    }

    public function getNavTitle(): string
    {
        return t('Image browser');
    }

    protected function preRender(): void
    {
        $this->collection = OrganizerApp::create()->createImageCollection();

        $this->defaultSize = self::CARD_SIZE_L;

        $this->cardSizes = self::getCardSizes();

        $this->activeCardSize = $this->resolveActiveCardSize();
    }

    public static function getCardSizes() : array
    {
        return array(
            self::CARD_SIZE_S => t('Small'),
            self::CARD_SIZE_M => t('Medium'),
            self::CARD_SIZE_L => t('Large'),
            self::CARD_SIZE_XL => t('Extra large')
        );
    }

    public static function setCardSize(string $size) : void
    {
        if(in_array($size, array_keys(self::getCardSizes()))) {
            $_SESSION[self::REQUEST_VAR_CARD_SIZE] = $size;
        }
    }

    private function resolveActiveCardSize() : string
    {
        $size = $this->defaultSize;

        if(isset($_SESSION[self::REQUEST_VAR_CARD_SIZE])) {
            $size = $_SESSION[self::REQUEST_VAR_CARD_SIZE];
        }

        $requestSize = $this->request->registerParam(self::REQUEST_VAR_CARD_SIZE)->setEnum(array_keys($this->cardSizes))->getString();
        if(!empty($requestSize)) {
            $size = $requestSize;
        }

        self::setCardSize($size);

        return $size;
    }

    private function resolveBooleanToggle(string $name) : bool
    {
        $result = false;

        if(isset($_SESSION[$name])) {
            $result = $_SESSION[$name] === true;
        }

        $requestVal = $this->request->registerParam($name)->setEnum(array('yes', 'no'))->getString();
        if(!empty($requestVal)) {
            $result = $requestVal === 'yes';
        }

        $_SESSION[$name] = $result;

        return $result;
    }

    private function resolveActiveFolder() : string
    {
        $result = '';

        if(isset($_SESSION[self::REQUEST_PARAM_FOLDER_NAME])) {
            $result = $_SESSION[self::REQUEST_PARAM_FOLDER_NAME];
        }

        $folder = $this->request->registerParam(self::REQUEST_PARAM_FOLDER_NAME)->setEnum($this->folders)->getString();
        if(!empty($folder)) {
            $result = $folder;
        }

        $_SESSION[self::REQUEST_PARAM_FOLDER_NAME] = $result;

        return $result;
    }

    private string $objName;
    private bool $upscaledOnly = false;
    private bool $favoritesOnly = false;
    private bool $galleryOnly = false;
    private string $activeFolder = '';

    /**
     * @var string[]
     */
    private array $folders = array();

    protected function _render(): void
    {
        $this->objName = $this->collection->injectJS($this->ui, $this->getURL());

        $this->upscaledOnly = $this->resolveBooleanToggle(self::REQUEST_PARAM_UPSCALED);
        $this->favoritesOnly = $this->resolveBooleanToggle(self::REQUEST_PARAM_FAVORITES);
        $this->galleryOnly = $this->resolveBooleanToggle(self::REQUEST_PARAM_GALLERY);

        $this->folders = $this->collection->getFolderNames();
        $this->activeFolder = $this->resolveActiveFolder();

        OutputBuffering::start();

        $missing = $this->collection->getMissingImages();
        $images = $this->resolveFilteredImages();

        ?>
        <div id="status-bar" hidden="hidden"></div>
        <?php

        if(!empty($missing)) {
            ?>
            <div class="alert alert-warning">
                <?php
                    pts('The following images are missing.');
                    pts('This can happen if the image files were deleted or moved outside of the organizer.');
                    pts('It is recommended to refresh the index, then delete the images if they are indeed missing.');
                ?><br>
                <ul>
                    <?php
                    foreach($missing as $image) {
                        ?>
                        <li><?php echo $image->getID() ?></li>
                        <?php
                    }
                    ?>
                </ul>
            </div>
            <?php
        }

        $this->renderFilterToolbar(count($images));
        $this->renderSizeSelector();

        ?>
        <div id="image-list">
            <?php
            foreach($images as $image) {
                $this->renderImage($image);
            }
            ?>
        </div>
        <p style="padding-bottom: 6rem">&#160;</p>
        <?php

        $this->ui
            ->makeFooterFixed()
            ->setFooterContent($this->renderFooter());

        OutputBuffering::flush();
    }

    private function renderFilterToolbar(int $imageCount) : void
    {
        ?>
        <h3><?php pt('Filtering') ?></h3>
        <div class="filter-toolbar">
            <?php
            $this->renderFilterToggle(
                t('Upscaling'),
                $this->upscaledOnly,
                $this->getURL(array(self::REQUEST_PARAM_UPSCALED => 'yes')),
                $this->getURL(array(self::REQUEST_PARAM_UPSCALED => 'no'))
            );

            $this->renderFilterToggle(
                t('Favorites'),
                $this->favoritesOnly,
                $this->getURL(array(self::REQUEST_PARAM_FAVORITES => 'yes')),
                $this->getURL(array(self::REQUEST_PARAM_FAVORITES => 'no'))
            );

            $this->renderFilterToggle(
                t('Gallery'),
                $this->galleryOnly,
                $this->getURL(array(self::REQUEST_PARAM_GALLERY => 'yes')),
                $this->getURL(array(self::REQUEST_PARAM_GALLERY => 'no'))
            );

            $this->renderFolderFilter();
            ?>

            <div style="display: inline-block; position:relative;" id="image-search">
                <input type="text"
                       placeholder="<?php pt('Filter by search...') ?>"
                       onkeyup="<?php echo $this->objName ?>.FilterImages(this.value);return false;"
                       class="form-control"
                       style="width: 300px; display: inline-block; margin-left: 8px;">
                <span class="reset-filters" onclick="<?php echo $this->objName ?>.ResetFilter()" title="<?php pt('Reset the filter terms') ?>"><?php echo Icon::delete() ?></span>
            </div>
        </div>
        <p><?php pt('Found %1$s images.', $imageCount); ?></p>
        <?php
    }

    private function renderSizeSelector() : void
    {
        ?>
        <div id="size-selector" data-size="<?php echo $this->activeCardSize ?>">
            <div class="btn-group">
                <?php
                foreach($this->cardSizes as $size => $label)
                {
                    $active = '';
                    if($size === $this->activeCardSize) {
                        $active = ' active';
                    }

                    ?>
                    <button
                        class="btn btn-secondary<?php echo $active ?> size-<?php echo $size ?>"
                        onclick="<?php echo $this->objName ?>.SwitchImageSize('<?php echo $size ?>')"
                        title="<?php echo $label ?>"
                    >
                        <?php echo strtoupper($size) ?>
                    </button>
                    <?php
                }

                ?>
                <button
                    class="btn btn-info"
                    onclick="<?php echo $this->objName ?>.ApplyImageSize();"
                    title="<?php pt('Apply the size preference') ?>"
                >
                    <?php echo Icon::save().' '; pt('Apply'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    private function resolveFilteredImages() : array
    {
        $result = array();

        foreach($this->collection->getAll() as $image) {
            if($this->isImageMatch($image)) {
                $result[] = $image;
            }
        }

        return $result;
    }

    private function isImageMatch(ImageInfo $image) : bool
    {
        if($this->upscaledOnly && !$image->isUpscaled()) {
            return false;
        }

        if($this->favoritesOnly && !$image->isFavorite()) {
            return false;
        }

        if($this->galleryOnly && !$image->isForGallery()) {
            return false;
        }

        if($this->activeFolder !== '' && $image->getProperties()->getFolderName() !== $this->activeFolder) {
            return false;
        }

        // Skip images that have an upscaled version, we only want to display the upscaled images.
        if($image->prop()->getUpscaledImage() !== null) {
            return false;
        }

        return true;
    }

    private function renderFolderFilter() : void
    {
        $baseURL = $this->getURL(array(self::REQUEST_PARAM_FOLDER_NAME => '__FOLDERNAME__'));

        ?>
        <select onchange="document.location.href='<?php echo $baseURL ?>'.replace('__FOLDERNAME__', this.value);" class="form-select" style="display:inline-block; width: 200px">
            <option><?php pt('Select a folder...') ?></option>
            <?php

            foreach($this->folders as $folderName) {
                $active = '';
                if($folderName === $this->activeFolder) {
                    $active = ' selected="selected"';
                }
                ?>
                <option value="<?php echo $folderName ?>" <?php echo $active ?>>
                    <?php echo $folderName ?>
                </option>
                <?php
            }
            ?>
        </select>
        <?php
    }

    private function renderFooter() : string
    {
        OutputBuffering::start();

        ?>
        <div id="footer-empty-selection" hidden="hidden">
            <?php pt('No images selected.'); ?> &#160;
        </div>
        <div id="footer-selection" hidden="hidden">
            <span class="selected-count"></span> <?php pt('images selected.') ?>
            <button class="btn btn-danger btn-sm" onclick="<?php echo $this->objName ?>.DeleteSelected()">
                <?php echo Icon::delete() ?>
                <?php pt('Delete') ?>
            </button>
            <button class="btn btn-primary btn-sm" onclick="<?php echo $this->objName ?>.FavoriteSelected()">
                <i class="fas fa-heart"></i>
                <?php pt('Favorite') ?>
            </button>
            <button class="btn btn-secondary btn-sm" onclick="<?php echo $this->objName ?>.UnfavoriteSelected()">
                <i class="far fa-heart"></i>
                <?php pt('Unfavorite') ?>
            </button>
            <button class="btn btn-secondary btn-sm" onclick="<?php echo $this->objName ?>.MoveSelected()">
                <i class="fas fa-file-export"></i>
                <?php pt('Move folder...') ?>
            </button>
            <button class="btn btn-info btn-sm" onclick="<?php echo $this->objName ?>.DeselectAll()">
                <i class="fas fa-toggle-on"></i>
                <?php pt('Deselect all') ?>
            </button>
        </div>
        <?php

        return OutputBuffering::get();
    }

    private function renderImage(ImageInfo $image) : void
    {
        $image->injectJS($this->ui, $this->objName);

        $props = $image->getProperties();

        $classes = array();
        $classes[] = 'size-'.$this->activeCardSize;
        if($image->isFavorite()) { $classes[] = 'favorite'; }
        if($image->isUpscaled()) { $classes[] = 'upscaled'; }
        if($image->isForGallery()) { $classes[] = 'forGallery'; }

        ?>
        <div id="wrapper-<?php echo $image->getID() ?>"
             class="image-wrapper <?php echo implode(' ', $classes) ?>"
        >
            <a href="<?php echo $image->getURL()  ?>" class="image-link" target="_blank">
                <img src="<?php echo $image->getThumbnailURL() ?>" alt="<?php echo $image->getLabel() ?>" loading="lazy" class="image-thumbnail thumbnail-xl"/>
            </a>
            <div class="image-details">
                <div class="image-toolbar">
                    <?php $this->renderImageToolbar($image) ?>
                </div>
                <div class="image-badges">
                    <?php
                    if($image->isUpscaled()) {
                        ?>
                        <span class="badge text-bg-success"><?php echo mb_strtoupper(t('Upscaled')) ?></span>
                        <?php
                    } else {
                        ?>
                        <span class="badge text-bg-secondary"><?php echo mb_strtoupper(t('Regular')) ?></span>
                        <?php
                    }
                    ?>
                </div>
                ID: <?php echo $image->getID() ?><br>
                Size: <?php echo $image->getImageSize()['width'] ?> x <?php echo $image->getImageSize()['height'] ?><br>
                Checkpoint: <?php echo $image->getCheckpoint() ?><br>
                Test: <?php echo $props->getTestName() ?> #<?php echo $props->getTestNumber() ?><br>
                Seed: <?php echo $props->getSeed() ?><br>
                Folder: <span class="folder-name"><?php echo $props->getFolderName() ?></span><br>
                <a href="<?php echo $image->getViewDetailsURL() ?>" target="_blank"><?php pt('More...') ?></a>
            </div>
        </div>
        <?php
    }

    private function renderImageToolbar(ImageInfo $image) : void
    {
        ?>
        <button
            onclick="<?php echo $this->objName ?>.DeleteImage('<?php echo $image->getID() ?>');return false;"
            class="btn btn-danger btn-sm"
        >
            <?php echo Icon::delete() ?>
            <?php pt('Delete') ?>
        </button>
        <?php
            $this->renderToggleButton(
                $image,
                $image->isFavorite(),
                'favorite',
                'ToggleFavorite',
                Icon::typeSolid('heart').' '.t('Favorite'),
                Icon::typeRegular('heart').' '.t('Unfavorite'),
            );
        ?>
        <div class="dropdown" style="display: inline-block">
            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php pt('More'); ?>
            </button>
            <ul class="dropdown-menu">
                <?php if(!$image->isUpscaled()) { ?>
                    <li>
                        <a href="#"
                           onclick="<?php echo $this->objName ?>.SetUpscaledID('<?php echo $image->getID() ?>');return false;"
                           class="dropdown-item"
                        >
                            <?php echo Icon::typeSolid('expand') ?>
                            <?php pt('Set upscaled ID...'); ?>
                        </a>
                    </li>
                <?php } ?>
                <li class="toggle-for-gallery">
                    <a href="#"
                       onclick="<?php echo $this->objName ?>.ToggleForGallery('<?php echo $image->getID() ?>');return false;"
                       class="dropdown-item"
                    >
                        <span class="toggle-enabled" <?php if(!$image->isForGallery()) { ?>hidden="hidden"<?php } ?>>
                            <?php echo Icon::typeSolid('images') ?>
                            <?php pt('Remove from gallery'); ?>
                        </span>
                        <span class="toggle-disabled" <?php if($image->isForGallery()) { ?>hidden="hidden"<?php } ?>>
                            <?php echo Icon::typeRegular('images') ?>
                            <?php pt('Set for gallery'); ?>
                        </span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item"
                       href="#"
                       onclick="<?php echo $this->objName ?>.MoveImage('<?php echo $image->getID() ?>');return false;"
                    >
                        <?php echo Icon::typeSolid('folder-open') ?>
                        <?php pt('Move to folder...'); ?>
                    </a
                </li>
            </ul>
        </div>
        <button
                onclick="<?php echo $this->objName ?>.ToggleSelection('<?php echo $image->getID() ?>');return false;"
                class="btn btn-info btn-sm toggle-selection"
        >
            <i class="fas fa-toggle-off"></i> <?php pt('Select') ?>
        </button>
        <?php
    }

    private function renderToggleButton(
            ImageInfo $image,
            bool $enabled,
            string $id,
            string $function,
            string $labelEnable,
            string $labelDisable
    ) : void
    {
        $statement = $this->objName.'.'.$function."('".$image->getID()."');return false";

        ?>
        <button
            class="toggle-<?php echo $id ?> btn btn-secondary btn-sm toggle-enabled"
            onclick="<?php echo $statement ?>"
            <?php if(!$enabled) { ?>hidden="hidden"<?php } ?>
        >
            <span><?php echo $labelDisable ?></span>
        </button>
        <button
            class="toggle-<?php echo $id ?> btn btn-primary btn-sm toggle-disabled"
            onclick="<?php echo $statement ?>"
            <?php if($enabled) { ?>hidden="hidden"<?php } ?>
        >
            <span><?php echo $labelEnable ?></span>
        </button>
        <?php
    }

    private function renderFilterToggle(string $label, bool $enabled, string $urlEnable, string $urlDisable): void
    {
        ?>
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-secondary label"><?php echo $label ?></button>
            <?php if($enabled) { ?>
                <a href="#" onclick="return false" class="btn btn-primary"><?php pt('ON' ) ?></a>
                <a href="<?php echo $urlDisable ?>" class="btn btn-secondary"><?php pt('OFF' ) ?></a>
            <?php } else { ?>
                <a href="<?php echo $urlEnable ?>" class="btn btn-secondary"><?php pt('ON' ) ?></a>
                <button onclick="return false" class="btn btn-primary"><?php pt('OFF' ) ?></button>
            <?php } ?>
        </div>
        <?php
    }

    protected function getURLParams(): array
    {
        return array(
            self::REQUEST_PARAM_UPSCALED => ConvertHelper::bool2string($this->request->getBool(self::REQUEST_PARAM_UPSCALED), true),
            self::REQUEST_PARAM_FAVORITES => ConvertHelper::bool2string($this->request->getBool(self::REQUEST_PARAM_FAVORITES), true),
            self::REQUEST_PARAM_FOLDER_NAME => $this->activeFolder,
            self::REQUEST_VAR_CARD_SIZE => $this->activeCardSize
        );
    }
}
