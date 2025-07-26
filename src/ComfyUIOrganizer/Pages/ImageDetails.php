<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Pages;

use AppUtils\OutputBuffering;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\OrganizerApp;
use Mistralys\X4\UI\Page\BasePage;
use function AppUtils\t;
use const Mistralys\ComfyUIOrganizer\Config\APP_WEBROOT_URL;

class ImageDetails extends BasePage
{
    public const string URL_NAME = 'image-details';
    private ?ImageInfo $image;

    public function getTitle(): string
    {
        return t('Image details');
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
        <p>
            <img src="<?php echo $this->image->getURL() ?>" style="width: 100%">
        </p>
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

        foreach($list as $key => $value) {
            $grid->addRowFromArray(array(
                'key' => $key,
                'value' => $value
            ));
        }

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
