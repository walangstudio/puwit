<?php

declare(strict_types=1);

define('PUWIT_ROOT', dirname(__DIR__));

require PUWIT_ROOT . '/vendor/autoload.php';

use Puwit\Kernel;

try {
    $kernel = new Kernel();
    $kernel->handle();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error']);
}
