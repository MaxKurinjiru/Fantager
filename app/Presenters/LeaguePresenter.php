<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


final class LeaguePresenter extends OnlinePresenter
{

	/** @var \App\Controls\League\Factory @inject */
	public $leagueFactory;



	/**************************************************************************/

	public function createComponentLeagueTable() {
		if (!empty($this->getParameter('id'))) {
			$param = $this->getParameter('id');
			//\Tracy\Debugger::barDump($param, 'param');
			$league = $this->facades->getFacade('league')->findLeagueByTag($param)->fetch();
		} else {
			$league = $this->facades->getFacade('league')->findLeagueByTeam($this->teamRow->id)->fetch();
		}

		//\Tracy\Debugger::barDump($league, 'league');

		$control = $this->leagueFactory->createTable($league->id);
		return $control;
	}

	/**************************************************************************/

	public function actionDefault() {

	}

	/**************************************************************************/
	
}
