<?php

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\RouteList;


final class RouterFactory
{
	use Nette\StaticClass;

	/**************************************************************************/

	public static function createRouter(): RouteList
	{
		$router = new RouteList;

		$cfg = new \App\Helper\Config;
		$config = $cfg->getConfig();

		// todo
		// www | game | beta | admin
		//$router->addRoute('//[<module=www|game|admin>].%domain%/[[<locale=cs [a-z]{2}>/][<presenter=Homepage>][/<action=default>][/<id>]]');

		// localhost without kingdom
		//$router->addRoute('//fantager/[[<locale=cs [a-z]{2}>/][<presenter=Homepage>][/<action=default>][/<id>]]');
		//$router->addRoute('//fantager/[[<kingdom=www>/][<locale=cs [a-z]{2}>/][<presenter=Homepage>][/<action=default>][/<id>]]');

		// todo
		// public by domain and subdomain maybe?
		//$router->addRoute('//%domain%/[[<locale=cs [a-z]{2}>/][<presenter=Homepage>][/<action=default>][/<id>]]');

		// single domain variant - maybe deprecated
		$router->addRoute('[[<locale=cs [a-z]{2}>/]<presenter=Homepage>[/<action=default>][/<id>]]');

		return $router;
	}

	/**************************************************************************/
}
