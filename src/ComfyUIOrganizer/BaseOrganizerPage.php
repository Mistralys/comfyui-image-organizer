<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use Mistralys\X4\UI\Icon;
use Mistralys\X4\UI\Page\BasePage;
use Mistralys\X4\UI\Page\NavItem;
use function AppLocalize\t;

abstract class BaseOrganizerPage extends BasePage
{
    public function getNavItems(): array
    {
        return array(
            new NavItem(Icon::typeSolid('bars').' '.t('Browser'), OrganizerApp::create()->url()->browser()),
            new NavItem(Icon::typeSolid('database').' '.t('Index manager'), OrganizerApp::create()->url()->indexManager()),
            new NavItem(Icon::typeSolid('code-branch').' '.t('Workflows'), OrganizerApp::create()->url()->workflows()),
            new NavItem(Icon::typeSolid('brain').' '.t('LoRAs'), OrganizerApp::create()->url()->loras()),
        );
    }
}
