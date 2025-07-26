<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

require_once __DIR__.'/prepend.php';

use Mistralys\X4\UI\UserInterface;
use const Mistralys\ComfyUIOrganizer\Config\APP_WEBROOT_URL;

$ui = new UserInterface(
    OrganizerApp::create(),
    APP_WEBROOT_URL,
    APP_WEBROOT_URL.'/vendor'
);

$ui->display();
