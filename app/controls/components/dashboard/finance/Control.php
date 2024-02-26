<?php

namespace App\Controls\Dashboard;

class Finance extends \App\Controls\Base {

    /** @var \App\Model\Facades @inject */
	public $facades;

	/** @var string **/
	public $tpl = __DIR__ . '/template.latte';

	public $arenaRow;
	public $teamRow;

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

	/**************************************************************************/

	public function beforeRender()
	{
		$this->template->arenaRow = $this->arenaRow;
		$this->template->teamRow = $this->teamRow;
		$this->template->translator = $this->facades->translator;
		$this->template->arenaStaffSalary = $this->facades->getFacade('arena')->getStaffSallary($this->arenaRow);
		$this->template->arenaMaintenance = $this->facades->getFacade('arena')->getPayMaintenanceLvl($this->arenaRow->level, $randomized = false);
		$this->template->teamFinanceHistory = $this->facades->getFacade('activity')->getFinanceHistoryLastByTeam($this->teamRow->id);
	}

	/**************************************************************************/

}
