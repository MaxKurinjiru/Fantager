<?php

declare(strict_types=1);
ob_start();

require __DIR__ . '/../vendor/autoload.php';

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
	if ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' && isset($_SERVER['SERVER_PORT']) && in_array($_SERVER['SERVER_PORT'], [80, 82])) { // https over proxy
		$_SERVER['HTTPS'] = 'On';
		$_SERVER['SERVER_PORT'] = 443;
	} elseif ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'http' && isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 80) { // http over proxy
		$_SERVER['HTTPS'] = 'Off';
		$_SERVER['SERVER_PORT'] = 80;
	}
}

define('__CONTROLS__', __DIR__ . '/../app/controls');
define('__TEMPLATES__', __DIR__ . '/../app/templates');

App\Bootstrap::boot()
	->createContainer()
	->getByType(Nette\Application\Application::class)
	->run();
