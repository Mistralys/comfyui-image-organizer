<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

require_once __DIR__.'/prepend.php';

use Mistralys\X4\UI\UserInterface;
use const Mistralys\ComfyUIOrganizer\Config\APP_WEBROOT_URL;

session_start();

$app = OrganizerApp::create();

$ui = new UserInterface(
    $app,
    APP_WEBROOT_URL,
    APP_WEBROOT_URL.'/vendor'
);

$app->setUI($ui);

$ui->display();
