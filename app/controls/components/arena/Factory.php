<?php

namespace App\Controls\Arena;

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

	/** @return  \App\Controls\Arena\Overview */
	public function createOverview() {
		return new \App\Controls\Arena\Overview($this->facades);
	}

	/** @return  \App\Controls\Arena\Staff */
	public function createStaff() {
		return new \App\Controls\Arena\Staff($this->facades);
	}

	/** @return  \App\Controls\Arena\Junior */
	public function createJunior() {
		return new \App\Controls\Arena\Junior($this->facades);
	}

	/**************************************************************************/

}
