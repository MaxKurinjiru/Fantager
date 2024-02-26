<?php

declare(strict_types=1);

namespace App\Controls\Form;

abstract class Base extends \App\Controls\Base {

	/** @var \Nette\Application\UI\Form */
	public $form;

	/** @var array */
	public $onSuccess;

	/** @var array */
	public $onError;

	/**************************************************************************/

	public function render() {

		$this->template->_form = $this;
		$this->template->form  = $this->form;

		parent::render();

	}

	public function getForm() {
		return $this->form;
	}

	/**************************************************************************/

}
