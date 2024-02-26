<?php

declare(strict_types=1);

namespace App\Helper;

use Nette;

final class Dice
{
	use Nette\SmartObject;

	/**************************************************************************/

	public static function rollDice($numDice, $numSides, $add = 0) {
		$sum = $add;
		for($i = 0; $i < $numDice; $i++) {
			$sum += mt_rand(1, $numSides);
		}
		return $sum;
	}

	/**************************************************************************/
}
