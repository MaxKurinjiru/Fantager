<?php

declare(strict_types=1);

namespace App\Model;

use Nette;

class Name
{

	/**************************************************************************/

	public static function generate($type) {

		$type = 'dwarf';

		$pathA = __DIR__ . '/names/' . $type . '.name';
		$pathB = __DIR__ . '/names/' . $type . '.surname';
		$a = preg_split('/\r\n|\r|\n/', file_get_contents($pathA));
		$b = preg_split('/\r\n|\r|\n/', file_get_contents($pathB));

		return trim($a[array_rand($a, 1)] . ' ' . $b[array_rand($b, 1)]);
	}

	/**************************************************************************/

}
