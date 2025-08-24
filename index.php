<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

require_once __DIR__.'/prepend.php';

use const Mistralys\ComfyUIOrganizer\Config\APP_WEBROOT_URL;

OrganizerApp::create()
    ->createUI(
        APP_WEBROOT_URL,
        APP_WEBROOT_URL.'/vendor'
    )
    ->display();
