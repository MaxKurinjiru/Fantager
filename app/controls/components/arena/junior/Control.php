<?php

namespace App\Controls\Arena;

class Junior extends \App\Controls\Base {

    /** @var \App\Model\Facades @inject */
	public $facades;

	/** @var string **/
	public $tpl = __DIR__ . '/template.latte';

	public $arenaRow;
	public $teamRow;
	public $userRow;

	/**************************************************************************/

	public function __construct(
        \App\Model\Facades $facades
	) {
        $this->facades = $facades;
	}

	/**************************************************************************/

	public function setArenaRow($arenaRow) {
		$this->arenaRow = $arenaRow;
		return $this;
	}
	public function setTeamRow($teamRow) {
		$this->teamRow = $teamRow;
		return $this;
	}
	public function setUserRow($userRow) {
		$this->userRow = $userRow;
		return $this;
	}

	/**************************************************************************/

	public function handleJunior() {
		// check if really can train new one
		if (!$this->facades->getFacade('arena')->canTrainJunior($this->arenaRow)) {
			$this->presenter->redirect('Arena:junior');
		}

		// todo: 
		// check team limit character if can have one more
		$juniorRow = $this->facades->getFacade('character')->newJunior(
			$this->arenaRow,
			$this->teamRow,
			$this->userRow
		);

		$this->presenter->redirect('Arena:junior');
	}

	/**************************************************************************/

	public function beforeRender()
	{
		$this->template->arenaRow = $this->arenaRow;
		$this->template->arenaJuniorAvail = $this->facades->getFacade('arena')->canTrainJunior($this->arenaRow);
	}

	/**************************************************************************/

}
