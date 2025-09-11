<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Workflows;

use Mistralys\ComfyUIOrganizer\OrganizerApp;
use Mistralys\ComfyUIOrganizer\Pages\WorkflowsPage;
use Mistralys\ComfyUIOrganizer\URLs;

class WorkflowFileURL
{
    public const string REQUEST_PRIMARY = 'workflow';

    private URLs $baseURL;
    private WorkflowFile $file;

    public function __construct(WorkflowFile $file)
    {
        $this->file = $file;
        $this->baseURL = OrganizerApp::create()->url();
    }

    public function fixPaths() : string
    {
        return $this->base(array(
            WorkflowsPage::REQUEST_PARAM_FIX_PATHS => 'yes'
        ));
    }

    public function base(array $params=array()) : string
    {
        $params[self::REQUEST_PRIMARY] = $this->file->getID();

        return $this->baseURL->workflows($params);
    }
}
