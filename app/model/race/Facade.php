<?php

declare(strict_types=1);

namespace App\Model\Facade;

use Nette;

final class Race extends DB
{
	/** @var string */
	public $table = 'race';

	/**************************************************************************/

	public function getToleranceTable() {
		$array = [];
		foreach ($this->table()->order('id ASC') as $row) {
			$array[$row->id] = [
				1 => $row->tolerance_1,
				2 => $row->tolerance_2,
				3 => $row->tolerance_3,
				4 => $row->tolerance_4,
				5 => $row->tolerance_5,
				6 => $row->tolerance_6,
				7 => $row->tolerance_7,
				8 => $row->tolerance_8,
			];
		}
		return $array;
	}

	public function getAgeTable() {
		$array = [];
		foreach ($this->table()->order('id ASC') as $row) {
			$array[$row->id] = [
				'min' => $row->age_min,
				'max' => $row->age_max,
				'old' => $row->age_old
			];
		}
		return $array;
	}

	/**************************************************************************/

	// discutable
	/**
	 * should here be race modificators?...
	 * or
	 * should it be in config?
	 * or 
	 * should it be in character something?
	*/

	/**************************************************************************/

}
