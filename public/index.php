<?php

declare(strict_types=1);

define('PUWIT_ROOT', dirname(__DIR__));

require PUWIT_ROOT . '/vendor/autoload.php';

use Puwit\Kernel;

$kernel = new Kernel();
$kernel->handle();
