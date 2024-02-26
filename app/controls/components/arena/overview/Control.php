<?php

namespace App\Controls\Arena;

class Overview extends \App\Controls\Base {

    /** @var \App\Model\Facades @inject */
	public $facades;

	/** @var string **/
	public $tpl = __DIR__ . '/template.latte';

	public $arenaRow;

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
	public function setTeamRow($arenaRow) {
		$this->teamRow = $teamRow;
		return $this;
	}
	public function setUserRow($arenaRow) {
		$this->userRow = $userRow;
		return $this;
	}

	/**************************************************************************/

	public function handleUpgradeInfo() {
		$this->template->upgrade_option = true;
	}

	public function handleChangeRace() {
		$race = (int) $this->getParameter('race');

		if ( $this->facades->getFacade('arena')->changeArenaRace($this->arenaRow, $race) ) {
			//$this->flashMessage('Změna uzpůsobení arény provedena');
		}
		$this->presenter->redirect('Arena:default');
	}

	public function handleUpgradeStart() {
		if ( $this->facades->getFacade('arena')->startArenaUpgrade($this->arenaRow) ) {
			//$this->flashMessage('Aréna se vylepšuje');
		}

		$this->presenter->redirect('Arena:default');
	}

	/**************************************************************************/

	public function beforeRender()
	{
		$this->template->arenaRow = $this->arenaRow;

		// seznam ras pro změnu uzpůsobení
		$this->template->raceList = $this->facades->getFacade('race')->table()->order('id ASC');
		// sedadla
		$this->template->arenaThisSeats = $this->facades->getFacade('arena')->getArenaSeatsByLevel($this->arenaRow->level);

		// platy a výdaje
		$this->template->arenaMaintenance = $this->facades->getFacade('arena')->getPayMaintenanceLvl($this->arenaRow->level, $randomized = false);

		// další level / maxnuto
		$arenaNextLevel = $this->arenaRow->level + 1;
		$this->template->arenaNextLevel = $arenaNextLevel;
		if ( !$this->facades->getFacade('arena')->existArenaLevel($arenaNextLevel) ) {
			$this->template->arenaMaxed = true;
		} else {
			$this->template->arenaMaxed = false;

			$this->template->arenaNextLevelSeats = $this->facades->getFacade('arena')->getArenaSeatsByLevel($arenaNextLevel);
			$this->template->arenaNextLevelPrice = $this->facades->getFacade('arena')->getArenaPriceByLevel($arenaNextLevel);
			$this->template->arenaNextLevelMaintenance = $this->facades->getFacade('arena')->getPayMaintenanceLvl($arenaNextLevel, false);
			$this->template->arenaNextLevelDays = $this->facades->getFacade('arena')->getArenaDaysByLevel($arenaNextLevel);
		}

		// arena se vylepšuje
		if ( !empty($this->arenaRow->level_progress) ) {
			$this->template->arenaInProgress = $this->arenaRow->level_progress;
			$this->template->arenaInProgressLeft = $this->facades->getFacade('arena')->getArenaBuildLeft($arenaNextLevel, $this->arenaRow->level_progress);
		}
	}

	/**************************************************************************/

}
