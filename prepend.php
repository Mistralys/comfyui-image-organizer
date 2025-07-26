<?php

declare(strict_types=1);

use AppUtils\Request;
use const Mistralys\ComfyUIOrganizer\Config\APP_WEBROOT_URL;

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/config.php';

Request::getInstance()->setBaseURL(APP_WEBROOT_URL);