<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Pages;

use AppUtils\ConvertHelper;
use AppUtils\OutputBuffering;
use Mistralys\ComfyUIOrganizer\BaseOrganizerPage;
use Mistralys\ComfyUIOrganizer\ImageCollection;
use Mistralys\ComfyUIOrganizer\ImageIndexer;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\OrganizerApp;
use Mistralys\X4\UI\Icon;
use function AppLocalize\pt;
use function AppLocalize\pts;
use function AppLocalize\t;
use function AppUtils\sb;

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
    public const string REQUEST_PARAM_REMOVE_MISSING = 'removeMissing';
    public const int RESULT_REFRESHED = 181001;

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
        $this->getUI()->addInternalStylesheet('app.css');

        $this->collection = OrganizerApp::create()->createImageCollection();
        $this->defaultSize = self::CARD_SIZE_L;
        $this->cardSizes = self::getCardSizes();
        $this->activeCardSize = $this->resolveActiveCardSize();
        $this->objName = $this->collection->injectJS($this->ui, $this->getURL());

        $this->upscaledOnly = $this->resolveBooleanToggle(self::REQUEST_PARAM_UPSCALED);
        $this->favoritesOnly = $this->resolveBooleanToggle(self::REQUEST_PARAM_FAVORITES);
        $this->galleryOnly = $this->resolveBooleanToggle(self::REQUEST_PARAM_GALLERY);

        $this->folders = $this->collection->getFolderNames();
        $this->activeFolder = $this->resolveActiveFolder();

        if($this->request->getBool('refresh') && !empty($this->activeFolder)) {
            $this->handleRefreshFolder();
        }

        if($this->request->getBool(self::REQUEST_PARAM_REMOVE_MISSING)) {
            $this->collection->removeMissingImages();
            $this->redirect($this->getURL());
        }
    }

    private function handleRefreshFolder() : never
    {
        $results = new ImageIndexer(OrganizerApp::create())
            ->indexFolder($this->activeFolder)
            ->detectUpscaledInFolder($this->activeFolder)
            ->getAnalysisResults();

        $this->redirectWithSuccessMessage(
            $this->getURL(),
            sb()
                ->t('Index successfully refreshed.')
                ->t(
                    'Added %1$s new images, updated %2$s existing ones.',
                    $results->countSuccesses(), $results->countNotices()
                ),
            self::RESULT_REFRESHED
        );
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

        $folders = $this->folders;
        $folders[] = 'all';

        $folder = $this->request->registerParam(self::REQUEST_PARAM_FOLDER_NAME)->setEnum($folders)->getString();
        if(!empty($folder)) {
            if($folder === 'all') {
                $folder = '';
            }

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
        OutputBuffering::start();

        $missing = $this->collection->getMissingImages();
        $images = $this->resolveFilteredImages();

        ?>
        <div id="status-bar" hidden="hidden"></div>
        <?php

        if(!empty($missing)) {
            ?>
            <div class="alert alert-warning">
                <p>
                <b><?php pt('Some images are missing on disk:'); ?></b>
                <?php
                    pts('This can happen if the image files were deleted or moved outside of the organizer.');
                    pts('They are still present in the index, but cannot be found on disk.');
                    pts('It is recommended to refresh the index in case they were not deleted, but only moved.');
                    pts('If they are indeed missing, you can remove them from the index with the button below.');
                ?>
                </p>
                <ul>
                    <?php
                    foreach($missing as $image) {
                        ?>
                        <li><?php echo $image->getImageFile()->getName() ?></li>
                        <?php
                    }
                    ?>
                </ul>
                <p>
                    <button class="btn btn-primary" onclick="document.location.href='<?php echo $this->getURL(array(self::REQUEST_PARAM_REMOVE_MISSING => 'yes')) ?>'">
                        <?php pt('Remove from the index'); ?>
                    </button>
                </p>
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
            ->setFooterContent($this->renderSelectionStatusBar());

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
                <label for="image-search-field"></label>
                <input type="search"
                       id="image-search-field"
                       placeholder="<?php pt('Filter by search...') ?>"
                       onkeyup="<?php echo $this->objName ?>.FilterImages(this.value);return false;"
                       onfocus="this.classList.add('focused')"
                       onblur="this.classList.remove('focused')"
                       class="form-control">
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
            <div class="btn-group">
                <button class="btn btn-info" onclick="<?php echo $this->objName ?>.SelectAll()" style="margin-left: var(--element-spacing-l);">
                    <?php echo Icon::typeSolid('toggle-on') ?>
                    <?php pt('Select all') ?>
                </button>
                <button class="btn btn-info" onclick="<?php echo $this->objName ?>.DeselectAll()">
                    <?php echo Icon::typeSolid('toggle-off') ?>
                    <?php pt('Select none') ?>
                </button>
            </div>
            <?php
            if(!empty($this->activeFolder)) {
                ?>
                <div class="btn-group">
                    <a class="btn btn-secondary" href="<?php echo $this->getURL(array('refresh' => 'yes')) ?>">
                        <?php echo Icon::typeSolid('recycle') ?>
                        <?php pt('Refresh') ?>
                    </a>
                </div>
                <?php
            }
            ?>
        </div>
        <hr>
        <p>
            <i>
            <?php
                pts('Note:');
                pts('Use CTRL + click to toggle the selection of images.');
            ?>
            </i>
        </p>
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
        <label for="folder-selector" hidden="hidden"><?php pt('Folder') ?></label>
        <select id="folder-selector"
                name="folder-selector"
                onchange="document.location.href='<?php echo $baseURL ?>'.replace('__FOLDERNAME__', this.value);"
                class="form-select">
            <option value="all"><?php pt('Select a folder...') ?></option>
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

    private function renderSelectionStatusBar() : string
    {
        OutputBuffering::start();

        ?>
        <div id="footer-empty-selection">
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
            <button class="btn btn-secondary btn-sm" onclick="<?php echo $this->objName ?>.LabelSelected()">
                <?php echo Icon::typeSolid('font') ?>
                <?php pt('Set label...') ?>
            </button>
            <button class="btn btn-secondary btn-sm" onclick="<?php echo $this->objName ?>.CopySelectedToOutput()">
                <?php echo Icon::typeSolid('copy') ?>
                <?php pt('Copy to output') ?>
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
             onclick="return <?php echo $this->objName ?>.HandleImageClicked('<?php echo $image->getID() ?>', event);"
        >
            <a href="<?php echo $image->getViewDetailsURL()  ?>" class="image-link" target="_blank">
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
                Label: <span class="image-label">
                        <?php
                            $label = $image->getLabel();
                            if(empty($label)) {
                                ?><a href="#" onclick="<?php echo $this->objName ?>.SetLabel('<?php echo $image->getID() ?>');return false;"><?php pt('Set...') ?></a><?php
                            } else {
                                echo htmlspecialchars($label);
                            }
                        ?>
                    </span><br>
                Size: <?php echo $image->getImageSize()['width'] ?> x <?php echo $image->getImageSize()['height'] ?><br>
                Checkpoint: <?php echo $image->getCheckpoint() ?><br>
                Test: <?php echo $props->getTestName() ?> #<?php echo $props->getTestNumber() ?>-<?php echo $props->getBatchNumber() ?><br>
                Seed: <?php echo $props->getSeed() ?><br>

                <?php if(empty($this->activeFolder) || $this->activeFolder === 'all') { ?>
                    Folder: <span class="folder-name"><?php echo $props->getFolderName() ?></span><br>
                <?php } ?>
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
            <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php pt('More'); ?>
            </button>
            <?php $this->renderImageMenu($image) ?>
        </div>
        <button
                onclick="<?php echo $this->objName ?>.ToggleSelection('<?php echo $image->getID() ?>');return false;"
                class="btn btn-info btn-sm toggle-selection"
        >
            <i class="fas fa-toggle-off"></i> <?php pt('Select') ?>
        </button>
        <?php
    }

    private function renderImageMenu(ImageInfo $image) : void
    {
        ?>
        <ul class="dropdown-menu">
            <li>
                <a href="#"
                   onclick="<?php echo $this->objName ?>.SetLabel('<?php echo $image->getID() ?>');return false;"
                   class="dropdown-item"
                >
                    <?php echo Icon::typeSolid('font') ?>
                    <?php pt('Set label...'); ?>
                </a>
            </li>
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

            <li>
                <a class="dropdown-item"
                   href="#"
                   onclick="<?php echo $this->objName ?>.CopyToOutput('<?php echo $image->getID() ?>');return false;"
                >
                    <?php echo Icon::typeSolid('copy') ?>
                    <?php pt('Copy to output folder'); ?>
                </a
            </li>
        </ul>
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
