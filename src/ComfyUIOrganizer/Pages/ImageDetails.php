<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Pages;

use AppUtils\OutputBuffering;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRAsCollection;
use Mistralys\ComfyUIOrganizer\OrganizerApp;
use Mistralys\X4\UI\Page\BasePage;
use function AppUtils\t;
use const Mistralys\ComfyUIOrganizer\Config\APP_WEBROOT_URL;

class ImageDetails extends BasePage
{
    public const string URL_NAME = 'image-details';
    private ?ImageInfo $image;

    public function getID(): string
    {
        return self::URL_NAME;
    }

    public function getTitle(): string
    {
        return '🔎 '.$this->image->getLabel();
    }

    public function getSubtitle(): string
    {
        return '';
    }

    public function getAbstract(): string
    {
        return '';
    }

    public function getNavTitle(): string
    {
        return t('Details');
    }

    protected function preRender(): void
    {
        $image = OrganizerApp::create()->createImageCollection()->getFromRequest();

        if($image === null) {
            $this->redirect(APP_WEBROOT_URL);
        }

        $this->image = $image;
    }

    protected function _render(): void
    {
        OutputBuffering::start();

        ?>
        <div id="wrapper-<?php echo $this->image->getID() ?>">
            <p>
                <a href="<?php echo $this->image->getURL() ?>">
                    <img src="<?php echo $this->image->getURL() ?>" style="width: 100%" alt="<?php echo t('Full image preview') ?>">
                </a>
            </p>
            <p>

            </p>
        </div>
        <?php

        $grid = $this->ui->createDataGrid();
        $grid->addColumn('key', t('Property'));
        $grid->addColumn('value', t('Value'));

        $list = array(
            t('ID') => $this->image->getID(),
            t('Checkpoint') => $this->image->getCheckpoint(),
            t('Date') => $this->image->getDate()->format('Y-m-d H:i:s'),
            t('Image file') => $this->image->getImageFile()->getPath(),
            t('Sidecar file') => $this->image->getSidecarFile()->getPath(),
            t('Image size') => $this->image->getImageSize()['width'].' x '.$this->image->getImageSize()['height'],
        );

        $list = array_merge($list, $this->image->prop()->serialize());

        $loraIDs = LoRAsCollection::getInstance()->getIDs();

        $loras = array();
        foreach($list as $key => $value)
        {
            if(in_array($key, $loraIDs)) {
                $loras[$key] = $value;
                continue;
            }

            $grid->addRowFromArray(array(
                'key' => $key,
                'value' => $value
            ));
        }

        ksort($loras);

        foreach ($loras as $key => $value)
        {
            $grid->addRowFromArray(array(
                'key' => t('LoRA: ').$key,
                'value' => $value
            ));
        }

        $grid->addRowFromArray(array(
            'key' => t('LoRA summary'),
            'value' => $this->image->prop()->getLoRASummary()
        ));

        echo $grid;

        OutputBuffering::flush();
    }

    public function getNavItems(): array
    {
        return array();
    }

    protected function getURLParams(): array
    {
        return array();
    }
}
