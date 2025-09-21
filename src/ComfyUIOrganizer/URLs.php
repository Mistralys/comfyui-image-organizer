<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use Mistralys\ComfyUIOrganizer\Pages\ImageBrowser;
use Mistralys\ComfyUIOrganizer\Pages\IndexManagerPage;
use Mistralys\ComfyUIOrganizer\Pages\LoRAOverviewPage;
use Mistralys\ComfyUIOrganizer\Pages\WorkflowsPage;
use Mistralys\X4\UI\Page\BasePage;
use Mistralys\X4\UI\UserInterface;
use const Mistralys\ComfyUIOrganizer\Config\APP_WEBROOT_URL;

class URLs
{
    private UserInterface $ui;

    public function __construct(UserInterface $ui)
    {
        $this->ui = $ui;
    }

    public function browser() : string
    {
        return $this->build(ImageBrowser::URL_NAME);
    }

    public function indexManager(array $params=array()) : string
    {
        return $this->build(IndexManagerPage::URL_NAME, $params);
    }

    public function workflows(array $params=array()) : string
    {
        return $this->build(WorkflowsPage::URL_NAME, $params);
    }

    public function loras(array $params=array()) : string
    {
        return $this->build(LoRAOverviewPage::URL_NAME, $params);
    }

    private function build(string $pageName, array $params=array()) : string
    {
        $params[BasePage::REQUEST_PARAM_PAGE] = $pageName;

        return $this->ui->getRequest()->setBaseURL(APP_WEBROOT_URL)->buildURL($params);
    }
}
