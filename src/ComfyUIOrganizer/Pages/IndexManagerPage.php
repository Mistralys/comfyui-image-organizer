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
    public const string REQUEST_PARAM_CLEAN_FOLDERS = 'cleanFolders';
    public const string REQUEST_PARAM_DETECT_UPSCALED = 'detectUpscaled';
    public const string REQUEST_PARAM_REFRESH = 'refresh';
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
        if($this->getRequest()->getBool(self::REQUEST_PARAM_REFRESH)) {
            OutputBuffering::start();
            new ImageIndexer(OrganizerApp::create())->indexImages();
            $this->output = OutputBuffering::get();
            $this->message = t('The index has been successfully refreshed.');
        }

        if($this->getRequest()->getBool(self::REQUEST_PARAM_DETECT_UPSCALED)) {
            OutputBuffering::start();
            new ImageIndexer(OrganizerApp::create())->detectUpscaledImages();
            $this->output = OutputBuffering::get();
            $this->message = t('Upscaled images have been successfully detected.');
        }

        if($this->getRequest()->getBool(self::REQUEST_PARAM_CLEAN_FOLDERS)) {
            OutputBuffering::start();
            new ImageIndexer(OrganizerApp::create())->cleanUpFolders();
            $this->output = OutputBuffering::get();
            $this->message = t('Empty folders have been successfully cleaned up.');
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
                <a href="<?php echo OrganizerApp::create()->url()->indexManager(array(self::REQUEST_PARAM_REFRESH => 'yes')) ?>" class="btn btn-primary">
                    <?php echo t('Refresh image index'); ?>
                </a>
                <br>
                <?php pt('Rebuilds the image index to detect changes on disk.'); ?>
            </p>
            <p>

                <a href="<?php echo OrganizerApp::create()->url()->indexManager(array(self::REQUEST_PARAM_DETECT_UPSCALED => 'yes')) ?>" class="btn btn-secondary">
                    <?php echo t('Auto-detect upscaled'); ?>
                </a>
                <br>
                <?php pt('Auto-detects upscaled versions of images when there is a single regular and upscaled image with the same generation settings.'); ?>
            </p>
            <p>

                <a href="<?php echo OrganizerApp::create()->url()->indexManager(array(self::REQUEST_PARAM_CLEAN_FOLDERS => 'yes')) ?>" class="btn btn-secondary">
                    <?php echo t('Clean up folders'); ?>
                </a>
                <br>
                <?php pt('Checks for empty image folders and deletes them.'); ?>
            </p>
            <?php
        }
    }

    protected function getURLParams(): array
    {
        return array();
    }
}
