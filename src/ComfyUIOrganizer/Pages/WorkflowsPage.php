<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Pages;

use Mistralys\ComfyUIOrganizer\BaseOrganizerPage;
use Mistralys\ComfyUIOrganizer\Workflows\PathFixer;
use Mistralys\ComfyUIOrganizer\Workflows\WorkflowFile;
use Mistralys\ComfyUIOrganizer\Workflows\WorkflowFileURL;
use Mistralys\ComfyUIOrganizer\Workflows\WorkflowsCollection;
use function AppLocalize\t;
use function AppUtils\sb;

class WorkflowsPage extends BaseOrganizerPage
{
    public const string URL_NAME = 'workflows';

    public const string REQUEST_PARAM_FIX_PATHS = 'fix-paths';

    public const string COL_LABEL = 'label';
    public const string COL_MODIFIED = 'modified';

    private WorkflowsCollection $workflowCollection;

    public function getID(): string
    {
        return self::URL_NAME;
    }

    public function getTitle(): string
    {
        return t('Workflows');
    }

    public function getSubtitle(): string
    {
        return t('Manage your ComfyUI workflows');
    }

    public function getAbstract(): string
    {
        return '';
    }

    public function getNavTitle(): string
    {
        return t('Workflows');
    }

    protected function preRender(): void
    {
        $this->workflowCollection = WorkflowsCollection::create();

        if($this->request->getBool(self::REQUEST_PARAM_FIX_PATHS))
        {
            $workflow = $this->workflowCollection->getByID($this->request->registerParam(WorkflowFileURL::REQUEST_PRIMARY)->setAlnum()->get());

            $messages = new PathFixer($workflow->getFile())->fixPaths()->getMessages();

            $this->redirectWithSuccessMessage(
                $this->getURL(),
                (string)sb()
                    ->para(sb()
                        ->t('The workflow paths have been checked and updated as necessary.')
                        ->t('See the messages below for details:')
                    )
                    ->ul($messages)
            );
        }
    }

    protected function _render(): void
    {
        $grid = $this->getUI()->createDataGrid();

        $grid->addColumn(self::COL_LABEL, t('Workflow'))
            ->useObjectValues()
            ->fetchByMethod(array(WorkflowFile::class, 'getLabelURLFixPaths'));

        $grid->addColumn(self::COL_MODIFIED, t('Last modified'))
            ->useObjectValues()
            ->fetchByMethod(array(WorkflowFile::class, 'getModifiedDate'))
            ->chooseFormat()
            ->dateAuto(true);

        $grid->addRowsFromObjects($this->workflowCollection->getAll());

        echo $grid;
    }

    protected function getURLParams(): array
    {
        return array();
    }
}
