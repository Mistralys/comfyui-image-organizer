<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Pages;

use AppUtils\OutputBuffering;
use Mistralys\ComfyUIOrganizer\BaseOrganizerPage;
use Mistralys\ComfyUIOrganizer\ImageIndexer;
use Mistralys\ComfyUIOrganizer\OrganizerApp;
use Mistralys\X4\UI\Page\BasePage;
use function AppLocalize\t;

class IndexManagerPage extends BaseOrganizerPage
{
    public const string URL_NAME = 'index-manager';
    private string $output;

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
        }
    }

    protected function _render(): void
    {
        if(!empty($this->output)) {
            ?>
            <div class="alert alert-success">
                <?php echo t('The index has been successfully rebuilt.'); ?>
            </div>
            <pre><?php echo $this->output; ?></pre>
            <?php
        } else {
            ?>
            <p>
                <?php echo t('This page allows you to rebuild the index of your images.'); ?>
            </p>
            <p>
                <a href="<?php echo OrganizerApp::create()->url()->indexManager(array('rebuild' => 'yes')) ?>" class="btn btn-primary">
                    <?php echo t('Rebuild Index'); ?>
                </a>
            </p>
            <?php
        }
    }

    protected function getURLParams(): array
    {
        return array();
    }
}
