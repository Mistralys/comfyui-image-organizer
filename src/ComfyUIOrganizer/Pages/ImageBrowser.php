<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Pages;

use AppUtils\ConvertHelper;
use AppUtils\OutputBuffering;
use Mistralys\ComfyUIOrganizer\ImageCollection;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\OrganizerApp;
use Mistralys\X4\UI\Icon;
use Mistralys\X4\UI\Page\BasePage;
use function AppLocalize\pt;
use function AppLocalize\pts;
use function AppLocalize\t;

class ImageBrowser extends BasePage
{
    public const string URL_NAME = 'image-browser';
    public const string REQUEST_PARAM_UPSCALED = 'upscaled';
    public const string REQUEST_PARAM_FAVORITES = 'favorites';
    private ImageCollection $collection;

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
    }

    private string $objName;
    private bool $upscaledOnly = false;
    private bool $favoritesOnly = false;

    protected function _render(): void
    {
        $this->objName = $this->collection->injectJS($this->ui, $this->getURL());

        $this->upscaledOnly = $this->request->getBool(self::REQUEST_PARAM_UPSCALED);
        $this->favoritesOnly = $this->request->getBool(self::REQUEST_PARAM_FAVORITES);

        OutputBuffering::start();

        $missing = $this->collection->getMissingImages();

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

        ?>
        <h3><?php pt('Filtering') ?></h3>
        <div class="filter-toolbar">
            <?php
                $this->renderFilterToggle(
                    t('Upscaling'),
                    $this->upscaledOnly,
                    $this->getURL(array(self::REQUEST_PARAM_UPSCALED => 'yes')),
                    $this->getURL(array(self::REQUEST_PARAM_UPSCALED => ''))
                );

                $this->renderFilterToggle(
                    t('Favorites'),
                    $this->favoritesOnly,
                    $this->getURL(array(self::REQUEST_PARAM_FAVORITES => 'yes')),
                    $this->getURL(array(self::REQUEST_PARAM_FAVORITES => ''))
                );
            ?>

            <div style="display: inline-block; position:relative;" id="image-search">
                <input type="text" placeholder="<?php pt('Filter by search...') ?>" onkeyup="<?php echo $this->objName ?>.FilterImages(this.value);return false;" class="form-control" style="width: 300px; display: inline-block; margin-left: 8px;">
                <span class="reset-filters" onclick="<?php echo $this->objName ?>.ResetFilter()" title="<?php pt('Reset the filter terms') ?>"><?php echo Icon::delete() ?></span>
            </div>
        </div>
        <?php

        foreach($this->collection->getAll() as $image)
        {
            $this->renderImage($image);
        }

        $this->ui
            ->makeFooterFixed()
            ->setFooterContent($this->renderFooter());

        OutputBuffering::flush();
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
            &#160;
            <button class="btn btn-primary btn-sm" onclick="<?php echo $this->objName ?>.FavoriteSelected()">
                <i class="fas fa-heart"></i>
                <span class="favorite-label"><?php pt('Favorite') ?></span>
            </button>
            &#160;
            <button class="btn btn-secondary btn-sm" onclick="<?php echo $this->objName ?>.UnfavoriteSelected()">
                <i class="far fa-heart"></i>
                <span class="favorite-label"><?php pt('Unfavorite') ?></span>
            </button>
            &#160;
            <button class="btn btn-info btn-sm" onclick="<?php echo $this->objName ?>.DeselectAll()">
                <i class="fas fa-toggle-on"></i>
                <span class="favorite-label"><?php pt('Deselect all') ?></span>
            </button>
        </div>
        <?php

        return OutputBuffering::get();
    }

    private function renderImage(ImageInfo $image) : void
    {
        if($this->upscaledOnly && !$image->isUpscaled()) {
            return;
        }

        if($this->favoritesOnly && !$image->isFavorite()) {
            return;
        }

        // Skip images that have an upscaled version, we only want to display the upscaled images.
        if($image->prop()->getUpscaledImage() !== null) {
            return;
        }

        $image->injectJS($this->ui, $this->objName);

        $props = $image->getProperties();

        $classes = array();
        if($image->isFavorite()) { $classes[] = 'favorite'; }
        if($image->isUpscaled()) { $classes[] = 'upscaled'; }

        ?>
        <div id="wrapper-<?php echo $image->getID() ?>"
             class="image-wrapper <?php echo implode(' ', $classes) ?>"
        >
            <a href="<?php echo $image->getURL()  ?>" class="image-link" target="_blank">
                <img src="<?php echo $image->getThumbnailURL() ?>" alt="<?php echo $image->getLabel() ?>" loading="lazy" class="image-thumbnail"/>
            </a>
            <div style="padding:8px;">
                <div class="image-toolbar">
                    <?php $this->renderImageToolbar($image) ?>
                </div>
                ID: <?php echo $image->getID() ?><br>
                Size: <?php echo $image->getImageSize()['width'] ?> x <?php echo $image->getImageSize()['height'] ?><br>
                Checkpoint: <?php echo $image->getCheckpoint() ?><br>
                Test: <?php echo $props->getTestName() ?> #<?php echo $props->getTestNumber() ?><br>
                Seed: <?php echo $props->getSeed() ?><br>
                Folder: <span class="folder-name"><?php echo $props->getFolderName() ?></span>
                    (<a href="#" onclick="<?php echo $this->objName ?>.MoveImage('<?php echo $image->getID() ?>');return false;">
                        <?php pt('Move...'); ?>
                    </a>)
                    <br>
                <a href="<?php echo $image->getViewDetailsURL() ?>" target="_blank"><?php pt('More...') ?></a>
            </div>
        </div>
        <?php
    }

    private function renderImageToolbar(ImageInfo $image) : void
    {
        if($image->isUpscaled()) {
            ?>
            <span class="badge text-bg-success" style="float:right"><?php echo mb_strtoupper(t('Upscaled')) ?></span>
            <?php
        }
        ?>
        <button
            onclick="<?php echo $this->objName ?>.DeleteImage('<?php echo $image->getID() ?>');return false;"
            class="btn btn-danger btn-sm"
        >
            <?php echo Icon::delete() ?>
            <?php pt('Delete') ?>
        </button>
        &#160;
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
        <?php if(!$image->isUpscaled()) { ?>
        &#160;
        <button
                onclick="<?php echo $this->objName ?>.SetUpscaledID('<?php echo $image->getID() ?>');return false;"
                class="btn btn-secondary btn-sm"
        >
            <?php pt('Upscaled ID...'); ?>
        </button>
        <?php } ?>
        &#160;
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

    public function getNavItems(): array
    {
        return array();
    }

    protected function getURLParams(): array
    {
        return array(
            self::REQUEST_PARAM_UPSCALED => ConvertHelper::bool2string($this->request->getBool(self::REQUEST_PARAM_UPSCALED), true),
            self::REQUEST_PARAM_FAVORITES => ConvertHelper::bool2string($this->request->getBool(self::REQUEST_PARAM_FAVORITES), true),
        );
    }
}
