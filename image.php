<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

require_once __DIR__.'/prepend.php';

use AppUtils\Request;

$request = Request::getInstance();
$imageID = $request->registerParam('imageID')->setMD5()->get();

if(empty($imageID)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$collection = OrganizerApp::create()->createImageCollection();

if(!$collection->idExists($imageID)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$image = $collection->getByID($imageID);

if($request->getBool('thumbnail')) {
    $image->displayThumbnail();
} else {
    $image->displayFullSize();
}
