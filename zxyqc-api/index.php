<?php
ini_set('display_error',E_ALL);
require_once __DIR__ . '/vendor/autoload.php';

define('WEB_DIR', __DIR__);

use Symfony\Component\HttpFoundation\Request;

$kernel = new AppKernel(Request::createFromGlobals(), true);
$kernel->handle();