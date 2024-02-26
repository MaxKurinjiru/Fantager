<?php

namespace App\Controls\Dashboard;

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

	/** @return  \App\Controls\Dashboard\Finance */
	public function createFinance() {
		return new \App\Controls\Dashboard\Finance($this->facades);
	}

	/**************************************************************************/

}
