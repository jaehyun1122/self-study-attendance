<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/App/Controller.php';

$app = new Controller();

echo $app->renderTemplate('public/index.php');
