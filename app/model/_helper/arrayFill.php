<?php

declare(strict_types=1);

namespace App\Helper;

use Nette;

final class ArrayFill
{
	use Nette\SmartObject;

	/**************************************************************************/

	public static function randomScale($count, $min = 1, $max = 20) {
		$numbers = [];
		for ($i = 0; $i < $count; $i++) {
			$numbers[] = mt_rand($min, $max);
		}
		shuffle($numbers);
		return $numbers;
	}

	/**************************************************************************/

	// from $total (sum) make array sizeof($count) increments [x1+x2+...] = sum)
    public static function randomAverage($total, $count = 6, $max_limit = null) {
		$numbers = [];

		if (empty($max_limit)) {
			$max_limit = $total;
		}

		for ($i = 1; $i < $count; $i++) {
			$_max = (int) floor($total / ($count - $i));
			if ($_max > $max_limit) {
				$_max = $max_limit;
			}
			$random = mt_rand(1, $_max);
			$numbers[] = $random;
			$total -= $random;
		}

        // $total now as last number
        // but can be 0 or over $max_limit

		$r_min = min($numbers);
		$r_max = max($numbers);

		while($total > $max_limit) {
			$r = array_rand($numbers, 1);
			if ($numbers[$r] < $r_max) {
				$total -= 1;
				$numbers[$r] += 1;
			}
		}

		while($total < ceil($r_min / 2)) {
			$r = array_rand($numbers, 1);
			if ($numbers[$r] > $r_min) {
				$total += 1;
				$numbers[$r] -= 1;
			}
		}
		$numbers[] = $total;

		shuffle($numbers);

		return $numbers;
	}

	/**************************************************************************/
}
