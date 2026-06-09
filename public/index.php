<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$appEnv = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';
$appDebug = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? '1';
$debug = filter_var($appDebug, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
$debug = $debug ?? $appDebug !== '0';

if ($debug) {
	umask(0000);
	Debug::enable();
}

$kernel = new Kernel($appEnv, $debug);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
