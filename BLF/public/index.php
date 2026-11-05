<?php

declare(strict_types=1);

use Lightpull\App;

$root = dirname(__DIR__, 2);
$appDir = dirname(__DIR__);

require $root . '/vendor/autoload.php';

App::loadEnv($root . '/.env');

require $appDir . '/pages.php';
require $appDir . '/admin_pages.php';

$app = new App(require $appDir . '/config.php');

require $appDir . '/routes.php';

$app->run();
