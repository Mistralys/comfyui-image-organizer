<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Config;

/**
 * URL to the web root of this application in the local webserver.
 */
const APP_WEBROOT_URL = 'http://127.0.0.1/comfyui-image-organizer';

/**
 * Absolute path to the ComfyUI installation folder.
 */
const APP_COMFYUI_FOLDER = 'C:\Users\newsl\Documents\ComfyUI';

/**
 * Absolute path to the folder in which ComfyUI saves generated images.
 */
const APP_IMAGE_FOLDER = APP_COMFYUI_FOLDER.'\output';

/**
 * **OPTIONAL**
 *
 * URL to the API endpoint of the website to which images can be sent.
 *
 * (This is used by the "Send to website" feature, but is intended only
 * for my personal use.)
 */
const APP_WEBSITE_API_URL = 'http://127.0.0.1/htdocs/websites/website-aeonoftime/htdocs/api';
