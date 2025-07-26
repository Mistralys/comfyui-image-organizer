<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Pages;

use AppUtils\ConvertHelper;
use AppUtils\JSHelper;
use AppUtils\OutputBuffering;
use Mistralys\ComfyUIOrganizer\Ajax\DeleteImageMethod;
use Mistralys\ComfyUIOrganizer\Ajax\FavoriteImageMethod;
use Mistralys\ComfyUIOrganizer\ImageCollection;
use Mistralys\ComfyUIOrganizer\OrganizerApp;
use Mistralys\X4\UI\Page\BasePage;
use function AppLocalize\pt;
use function AppLocalize\t;

class ImageBrowser extends BasePage
{
    public const string URL_NAME = 'image-browser';
    const string REQUEST_PARAM_UPSCALED = 'upscaled';
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

    protected function _render(): void
    {
        $this->ui->addInternalJS('ImageBrowser.js');
        $this->ui->addInternalJS('ImageHandler.js');
        $this->ui->addInternalStylesheet('app.css');

        $objName = 'IB'.JSHelper::nextElementID();

        $this->ui->addJSHead(sprintf(
            "const %s = new ImageBrowser('%s', %s);",
            $objName,
            $this->getURL(),
            ConvertHelper\JSONConverter::var2json(array(
                'deleteImage' => DeleteImageMethod::METHOD_NAME,
                'favoriteImage' => FavoriteImageMethod::METHOD_NAME,
            ))
        ));

        $upscaledOnly = $this->request->getBool(self::REQUEST_PARAM_UPSCALED);

        OutputBuffering::start();

        ?>
        <h3><?php pt('Filtering') ?></h3>
        <div style="margin-bottom: 30px;">
            <?php pt('Upscaling:') ?>
            <?php if($upscaledOnly) { ?>
                <a href="<?php echo $this->getURL(array(self::REQUEST_PARAM_UPSCALED => '')) ?>"><?php pt('Regular and upscaled') ?></a>
            <?php } else { ?>
                <a href="<?php echo $this->getURL(array(self::REQUEST_PARAM_UPSCALED => 'yes')) ?>"><?php pt('Upscaled only') ?></a>
            <?php } ?>
        </div>
        <?php

        foreach($this->collection->getAll() as $image)
        {
            if($upscaledOnly && !$image->isUpscaled()) {
                continue;
            }

            $this->ui->addJSHead(sprintf(
                "%s.RegisterImage('%s');",
                $objName,
                $image->getID()
            ));

            $props = $image->getProperties();

            ?>
            <div id="wrapper-<?php echo $image->getID() ?>"
                 class="image-wrapper <?php if($image->isFavorite()) { echo 'favorite'; } ?>"
            >
                <a href="<?php echo $image->getURL()  ?>" style="display: block">
                    <img src="<?php echo $image->getThumbnailURL() ?>" alt="<?php echo $image->getLabel() ?>" loading="lazy" class="image-thumbnail"/>
                </a>
                <div style="padding:8px;">
                <?php
                if($image->isUpscaled()) {
                    ?>
                    <span style="float:right">
                        <strong style="color:#22ac00"><?php echo mb_strtoupper(t('Upscaled')) ?></strong>
                    </span>
                    <?php
                }
                ?>
                <a href="#" onclick="<?php echo $objName ?>.DeleteImage('<?php echo $image->getID() ?>');return false;"><?php pt('Delete') ?></a>
                    |
                <a href="#" class="toggle-favorite" onclick="<?php echo $objName ?>.ToggleFavorite('<?php echo $image->getID() ?>');return false;">
                    <?php
                        if($image->isFavorite()) {
                            pt('Unfavorite');
                        } else {
                            pt('Favorite');
                        }
                    ?>
                </a>

                <br>
                ID: <?php echo $image->getID() ?><br>
                Size: <?php echo $image->getImageSize()['width'] ?> x <?php echo $image->getImageSize()['height'] ?><br>
                Checkpoint: <?php echo $image->getCheckpoint() ?><br>
                Test: <?php echo $props->getTestName() ?> #<?php echo $props->getTestNumber() ?><br>
                Seed: <?php echo $props->getSeed() ?><br>
                Folder: <?php echo $props->getFolderName() ?><br>
                </div>
            </div>
            <?php
        }

        OutputBuffering::flush();
    }

    public function getNavItems(): array
    {
        return array();
    }

    protected function getURLParams(): array
    {
        return array(
            self::REQUEST_PARAM_UPSCALED => ConvertHelper::bool2string($this->request->getBool(self::REQUEST_PARAM_UPSCALED), true)
        );
    }
}
