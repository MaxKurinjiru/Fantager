<?php

declare(strict_types=1);

namespace App\Controls\League;

class Factory {

	/** @var \App\Model\Facades @inject */
	public $facades;

	/**************************************************************************/

	public function __construct(
		\App\Model\Facades $facades
	) {
		$this->facades = $facades;
	}

	/**************************************************************************/

	public function createTable(int $id = 0) {
		$control = new \App\Controls\LeagueTable(
			$this->facades,
			$id
		);
		return $control;
	}

	/**************************************************************************/

}
