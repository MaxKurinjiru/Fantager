<?php

namespace App\Controls\Player;

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

	/** @return  \App\Controls\Player\Overview */
	public function createOverview() {
		return new \App\Controls\Player\Overview($this->facades, $this);
	}

	/**************************************************************************/

}
