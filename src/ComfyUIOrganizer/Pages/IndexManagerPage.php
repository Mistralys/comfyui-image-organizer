<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Pages;

use AppUtils\OutputBuffering;
use Mistralys\ComfyUIOrganizer\BaseOrganizerPage;
use Mistralys\ComfyUIOrganizer\ImageIndexer;
use Mistralys\ComfyUIOrganizer\OrganizerApp;
use function AppLocalize\pt;
use function AppLocalize\t;

class IndexManagerPage extends BaseOrganizerPage
{
    public const string URL_NAME = 'index-manager';
    private string $output;
    private string $message = '';

    public function getID(): string
    {
        return self::URL_NAME;
    }

    public function getTitle(): string
    {
        return t('Index Manager');
    }

    public function getSubtitle(): string
    {
        return t('Manage the database index of your images.');
    }

    public function getAbstract(): string
    {
        return '';
    }

    public function getNavTitle(): string
    {
        return t('Index Manager');
    }

    protected function preRender(): void
    {
        if($this->getRequest()->getBool('rebuild')) {
            OutputBuffering::start();
            new ImageIndexer(OrganizerApp::create())->indexImages();
            $this->output = OutputBuffering::get();
            $this->message = t('The index has been successfully refreshed.');
        }

        if($this->getRequest()->getBool('detectUpscaled')) {
            OutputBuffering::start();
            new ImageIndexer(OrganizerApp::create())->detectUpscaledImages();
            $this->output = OutputBuffering::get();
            $this->message = t('Upscaled images have been successfully detected.');
        }
    }

    protected function _render(): void
    {
        if(!empty($this->message)) {
            ?>
            <div class="alert alert-success">
                <?php echo $this->message ?>
            </div>
            <pre><?php echo $this->output; ?></pre>
            <?php
        } else {
            ?>
            <p>
                <a href="<?php echo OrganizerApp::create()->url()->indexManager(array('rebuild' => 'yes')) ?>" class="btn btn-primary">
                    <?php echo t('Refresh image index'); ?>
                </a>
                <br>
                <?php pt('Rebuilds the image index to detect changes on disk.'); ?>
            </p>
            <p>

                <a href="<?php echo OrganizerApp::create()->url()->indexManager(array('detectUpscaled' => 'yes')) ?>" class="btn btn-secondary">
                    <?php echo t('Auto-detect upscaled'); ?>
                </a>
                <br>
                <?php pt('Auto-detects upscaled versions of images when there is a single regular and upscaled image with the same generation settings.'); ?>
            </p>
            <?php
        }
    }

    protected function getURLParams(): array
    {
        return array();
    }
}
