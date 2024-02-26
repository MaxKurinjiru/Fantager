<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


final class ArenaPresenter extends OnlinePresenter
{

	/** @var \App\Controls\Arena\Factory @inject */
	public $arenaControlFactory;

	/**************************************************************************/

	public function createComponentArenaOverview() {
		return $this->arenaControlFactory->createOverview()->setArenaRow($this->arenaRow);
	}

	public function createComponentArenaStaff() {
		return $this->arenaControlFactory->createStaff()->setArenaRow($this->arenaRow);
	}

	public function createComponentArenaJunior() {
		return $this->arenaControlFactory->createJunior()->setArenaRow($this->arenaRow)
			->setTeamRow($this->teamRow)->setUserRow($this->userRow);
	}

	/**************************************************************************/

	public function actionDefault() {

	}

	/**************************************************************************/

}
