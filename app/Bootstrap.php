<?php

declare(strict_types=1);

namespace App;

use Nette\Configurator;

class Bootstrap
{
	public static function boot(): Configurator
	{
		$configurator = new Configurator;

		ini_set('memory_limit', '256M');

		\Tracy\Debugger::enable();
		\Tracy\Debugger::$email = 'kurinjiru@gmail.com';

		$configurator->setDebugMode(false);
		/*
		if ($_SERVER['REMOTE_ADDR'] == '89.103.96.84') {
			echo "<pre>";
			print_r($_SERVER);
			echo "</pre>";
			die();
		}
		//*/

		// mode
		$mode = 'local';

		if ( !empty($_SERVER['NETTE_APP_MODE']) ) {
			$mode = $_SERVER['NETTE_APP_MODE'];
		}

		if ( !empty($_SERVER['NETTE_PROJECT_CONFIG']) ) {
			$mode = $_SERVER['NETTE_PROJECT_CONFIG'];
		}

		if ($mode != 'local') {
			$configurator->setDebugMode('89.103.96.84'); // enable for your remote IP
		} else {
			//$configurator->setDebugMode(true); // enable
		}

		$configurator->enableTracy(__DIR__ . '/../log');

		$configurator->setTimeZone('Europe/Prague');
		$configurator->setTempDirectory(__DIR__ . '/../temp');

		$configurator->createRobotLoader()
			->addDirectory(__DIR__)
			->register();

		$configurator->addConfig(__DIR__ . '/config/common.neon');
		$configurator->addConfig(__DIR__ . '/config/' . $mode . '.neon');

		return $configurator;
	}
}
