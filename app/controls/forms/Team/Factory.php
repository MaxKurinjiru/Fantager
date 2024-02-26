<?php

declare(strict_types=1);

namespace App\Controls\Form\Team;

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

	public function createRename() {
		$control = new \App\Controls\Form\Team\Rename($this->facades);
		return $control;
	}

	/**************************************************************************/
}
