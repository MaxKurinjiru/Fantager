<?php

declare(strict_types=1);

namespace App\Helper;

use Nette;
use Nette\Utils\Strings;

final class Token
{
	use Nette\SmartObject;

	/**************************************************************************/

	public static function createToken() {
		return Nette\Utils\Random::generate(15, '0-9AZ');
	}

	public static function formatToken(string $token) {
		$s1 = Strings::substring($token, 0, 4);
		$s2 = Strings::substring($token, 5, 9);
		$s3 = Strings::substring($token, 10, 14);

		return $s1 .'-'. $s2 .'-'. $s3;
	}

	/**************************************************************************/

}
