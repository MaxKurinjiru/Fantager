<?php

declare(strict_types=1);

namespace App\Controls;

class OnlineMenu extends Base {

	/** @var \App\Model\Facades @inject */
	public $facades;

	/** @var string **/
	public $tpl = __DIR__ . '/template.latte';

	/**************************************************************************/

	public function __construct(
		\App\Model\Facades $facades
	) {
		$this->facades = $facades;
	}

	/**************************************************************************/

	public function beforeRender() {
		$userFacade = $this->facades->getFacade('user');

		$userRow = $userFacade->findById($this->presenter->getUser()->getIdentity()->id);

		$this->template->userRow = $userRow;
	}

	/**************************************************************************/

}
