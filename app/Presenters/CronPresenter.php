<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use \Tracy\Debugger;


class CronPresenter extends BasePresenter
{

	/** @var \App\Model\Cron\Cron @inject */
	public $cron;

	/*******************************************************/
	/*******************************************************/

	public function actionTemp() {
		/*
		$this->getKingdom();
		// set webalize names
		$this->cron->facades->getFacade('user')->webalizeNames();
		$this->cron->facades->getFacade('team')->webalizeNames();
		$this->afterDone();
		*/
	}


	/*******************************************************/
	/*******************************************************/

	public function getKingdom() {
		return $this->getParameter('kid');
	}

	public function checkKingdom() {
		if ($kid = $this->getKingdom()) {
			$this->cron->setKingdom($kid);
		}
	}

	public function afterDone() {
		$this->redirect('Homepage:');
	}

	/*******************************************************/
	/*******************************************************/

	// daily
	public function actionArenaUpgrades() {
		$this->checkKingdom();
		$this->cron->arenaUpgrade();
		$this->afterDone();
	}

	/*******************************************************/

	public function actionHeroesRecovery() {
		$this->checkKingdom();
		$this->cron->heroesRecovery();
		$this->afterDone();
	}

	/*******************************************************/

	public function actionDailyPlayers() {
		$this->checkKingdom();
		$this->cron->checkInsolventPlayers();
		$this->cron->checkInactivePlayers();
		$this->afterDone();
	}

	/*******************************************************/

	// tuesday
	public function actionArenaRaceChange() {
		$this->checkKingdom();
		$this->cron->arenaRaceChange();
		$this->afterDone();
	}

	/*******************************************************/

	// tuesday
	public function actionArenaJuniorBonus() {
		$this->checkKingdom();
		$this->cron->arenaJuniorBonus();
		$this->afterDone();
	}

	/*******************************************************/

	// tuesday
	public function actionJuniorReset() {
		$this->checkKingdom();
		$this->cron->arenaResetJunior();
		$this->afterDone();
	}

	/*******************************************************/

	public function actionHeroesTraining() {
		$this->checkKingdom();
		$this->cron->heroesTraining();
		$this->afterDone();
	}

	/*******************************************************/

	// tuesday
	public function actionFinanceWeek() {
		$this->checkKingdom();
		$this->cron->financeWeek();
		$this->afterDone();
	}

	/*******************************************************/

	/*******************************************************/
}
