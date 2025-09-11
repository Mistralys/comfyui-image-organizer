<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\FileHelper\FolderInfo;

class ComfyUI
{
    private OrganizerApp $app;

    public function __construct()
    {
        $this->app = OrganizerApp::create();
    }

    private static ?self $instance = null;

    public static function create() : self
    {
        if(self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getWorkflowsFolder() : FolderInfo
    {
        return FolderInfo::factory($this->app->getComfyFolder().'/user/default/workflows')->requireExists();
    }
}
