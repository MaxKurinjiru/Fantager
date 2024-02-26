<?php

namespace App\Controls\Player;

class Overview extends \App\Controls\Base {

    /** @var \App\Model\Facades @inject */
	public $facades;

	/** @var \App\Controls\Player\Factory @inject */
	public $factory;

	/** @var string **/
	public $tpl = __DIR__ . '/template.latte';

	public $userRow;
	public $teamRow;
	public $isMine;

	/**************************************************************************/

	public function __construct(
        \App\Model\Facades $facades,
		\App\Controls\Player\Factory $factory
	) {
        $this->facades = $facades;
		$this->factory = $factory;

		$this->getConfig();
	}

	/**************************************************************************/

	public function setUserRow($userRow) {
		$this->userRow = $userRow;
		$this->teamRow = $this->facades->getFacade('team')->findByUser($userRow->id)->fetch();
		return $this;
	}

	public function setMine($isMine) {
		$this->isMine = $isMine;
		return $this;
	}

	/**************************************************************************/

	/**************************************************************************/

	public function beforeRender()
	{
		$this->template->userRow = $this->userRow;
		$this->template->teamRow = $this->teamRow;
		$this->template->isMine = $this->isMine;
	}

	/**************************************************************************/

}
