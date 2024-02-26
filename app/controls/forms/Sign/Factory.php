<?php

declare(strict_types=1);

namespace App\Controls\Form\Sign;

class Factory {

	/** @var \App\Model\Facades @inject */
	public $facades;

	/** @var \App\Model\Mail @inject */
	public $modelMail;

	/**************************************************************************/

	public function __construct(
		\App\Model\Facades $facades,
		\App\Model\Mail $modelMail
	) {
		$this->facades = $facades;
		$this->modelMail = $modelMail;
	}

	/**************************************************************************/

	public function createSignIn() {
		$control = new \App\Controls\Form\SignIn();
		return $control;
	}

	public function createSignUp() {
		$control = new \App\Controls\Form\SignUp($this->facades, $this->modelMail);
		return $control;
	}

	public function createSignRecover() {
		$control = new \App\Controls\Form\SignRecover($this->facades, $this->modelMail);
		return $control;
	}

	public function createSignPassword() {
		$control = new \App\Controls\Form\SignPassword($this->facades);
		return $control;
	}

	/**************************************************************************/
}
