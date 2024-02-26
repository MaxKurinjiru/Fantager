<?php

declare(strict_types=1);

namespace App\Controls\Form\Message;

class Factory {

	/** @var \App\Model\Facade\User @inject */
	public $facadeUser;

	/**************************************************************************/

	public function __construct(
		\App\Model\Facade\User $facadeUser
	) {
		$this->facadeUser = $facadeUser;
	}

	/**************************************************************************/

	public function create($kingdom) {
		$control = new \App\Controls\Form\Message($this->facadeUser, $kingdom);
		return $control;
	}

	/**************************************************************************/

}
