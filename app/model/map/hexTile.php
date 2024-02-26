<?php

declare(strict_types=1);

namespace App\Model\Map;

use Nette;

class HexTile
{
	/** @var int */
	public $x;
	public $y;
	public $z;

	public $map;

	public $isFree = true;
	public $isEven;

	/**************************************************************************/
	public function __construct($x, $y, $map) {
		$this->x = $x;
		$this->y = $y;
		$this->map = $map;

		$this->setZ();

		$this->even = $this->y % 2 == 0;
	}

	public function notFree() {
		$this->isFree = false;
	}

	public function setZ() {
		/*
		x + y + z = 0
		-- but there is no negative coords...
		*/
		$this->z = $this->x + $this->y;
		//$this->z = -1 * ($x + $y);
	}

	public function isRowEven() {
		return $this->even;
	}

	public function getSurroundingTiles() {
		$array = [];
		$returnArray = [];

		$x = $this->x;
		$y = $this->y;

		//surrounded by z
		if ( !$this->isRowEven() ) {
			// diag for even rows
			$array[] = ['x' => $x+1, 'y' => $y-1];
			$array[] = ['x' => $x+1, 'y' => $y+1];
		} else {
			// diag for odd rows
			$array[] = ['x' => $x-1, 'y' => $y-1];
			$array[] = ['x' => $x-1, 'y' => $y+1];
		}

		// surrounded by y
		$array[] = ['x' => $x-1, 'y' => $y];
		$array[] = ['x' => $x+1, 'y' => $y];

		// surrounded by x
		$array[] = ['x' => $x, 'y' => $y-1];
		$array[] = ['x' => $x, 'y' => $y+1];

		// if position valid and exist
		foreach ($array as $tile) {
			// not exist
			if ( empty($this->map->mapArray[$tile['x']][$tile['y']]) ) {
				continue;
			}

			$returnArray[] = $this->map->mapArray[$tile['x']][$tile['y']];
		}

		return $returnArray;
	}


	/**************************************************************************/



	/**************************************************************************/



	/**************************************************************************/

}
