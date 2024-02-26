<?php

namespace App\Controls\Team;

class Factory {

	/** @var \App\Model\Facades @inject */
	public $facades;

	/** @var \App\Controls\Form\Team\Factory @inject */
	public $factoryTeam;

	/**************************************************************************/

	public function __construct(
        \App\Model\Facades $facades,
		\App\Controls\Form\Team\Factory $factoryTeam
	) {
        $this->facades = $facades;
		$this->factoryTeam = $factoryTeam;
	}

	/**************************************************************************/

	/** @return  \App\Controls\Team\Overview */
	public function createOverview() {
		return new \App\Controls\Team\Overview($this->facades, $this->factoryTeam);
	}

	/** @return  \App\Controls\Team\Characters */
	public function createCharacters() {
		return new \App\Controls\Team\Characters($this->facades);
	}

	/**************************************************************************/

}
