<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


final class DashboardPresenter extends OnlinePresenter
{

	/** @var \App\Controls\Dashboard\Factory @inject */
	public $dashboardControlFactory;

	/**************************************************************************/

	public function createComponentFinanceOverview() {
		return $this->dashboardControlFactory->createFinance()->setArenaRow($this->arenaRow)->setTeamRow($this->teamRow);
	}

	/**************************************************************************/

	public function actionDefault() {
		/*
		homepage rozcestník po přihlášení

		- finance
		- fanclub
		- kalendář a eventy

		- team
		- aréna
		- liga
		- zápasy
		- tržiště
		- nastavení
		- pošta

		*/

		/*

		- can train junior
		- have new mail
		*/
	}

	/**************************************************************************/

}
