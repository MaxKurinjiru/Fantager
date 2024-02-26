<?php

declare(strict_types=1);

namespace App\Model\Map;

use Nette;

class HexMap
{
	/** @var int */
	public $gridX;
	public $gridY;

	/** @var array */
	public $mapArray = [];

	/**************************************************************************/

	public function __construct($x, $y) {
		$this->gridX = $x;
		$this->gridY = $y;

		$this->generate();
	}

	/**************************************************************************/

	public function generate() {
		$gx = $this->gridX;
		$gy = $this->gridY;

		if (empty($gx) || empty($gy)) {
			return;
		}

		for ($x=0; $x < $gx; $x++) {
			for ($y=0; $y < $gy; $y++) {
				$this->mapArray[$x][$y] = new HexTile($x,$y,$this);
			}
		}
	}

	/**************************************************************************/

	public function distanceBetween($a, $b){
		// same x
		if ($a->x == $b->x) {
			return abs($b->y - $a->y);
		}
		// same y
		if ($a->y == $b->y) {
			return abs($b->x - $a->x);
		}

		// not same axis
		// and some corection, google find this..
		// dont know why, only correct way to get right values
		// something against square to hex transform
		$dx = abs($b->x - $a->x);
		$dy = abs($b->y - $a->y);

		if ($a->x < $b->x) {
			$com = (int) ceil($dy / 2);
		} else {
			$com = (int) floor($dy / 2);
		}

		return $dx + $dy - $com;
	}

	/**************************************************************************/

	public function pathBetween($a, $b) {
		$checked = [];
		$checked[] = $a->x . '|' . $a->y;
		$pathT = [];
		$cTile = $a;
		// default max distance
		$distance = $this->gridX;
		if ($this->gridY > $this->gridX) {
			$distance = $this->gridY;
		}

		while($distance > 0) {

			foreach($cTile->getSurroundingTiles() as $step) {

				$checkedString = $step->x . '|' . $step->y;

				if (!$step->isFree) {
					continue;
					//\Tracy\Debugger::barDump($step->x . '|' . $step->y . ' - step skip (not free)');
				}

				if (in_array($checkedString, $checked)) {
					continue;
				}

				$checked[] = $checkedString;

				$d = $this->distanceBetween($step, $b);

				\Tracy\Debugger::barDump($checkedString . ' - step check ('. $d .')');

				if ($d > $distance) {
					//\Tracy\Debugger::barDump($step->x . '|' . $step->y . ' - step skip (' . $d . '>=' . $distance . ')');
					continue;
				}

				//\Tracy\Debugger::barDump($distance, 'distance min');
				//\Tracy\Debugger::barDump([$step, $b, $d], 'distance between');

				\Tracy\Debugger::barDump('-> step usable');

				// if not known distance or lower is found
				//\Tracy\Debugger::barDump($distance, 'nova nejblizsi');
				$distance = $d;
				$closest = $step;

				if ($d == 0) {
					\Tracy\Debugger::barDump('-> destination found');
					break;
				}
			}
			\Tracy\Debugger::barDump($closest->x . '|' . $closest->y, '-> step used');

			$pathT[] = $cTile = $closest;

			if ($d == 0) {
				\Tracy\Debugger::barDump('-> destination found');
				break;
			}

		}

		\Tracy\Debugger::barDump($pathT, 'path');

	}

	/**************************************************************************/



	/**************************************************************************/



	/**************************************************************************/

}
