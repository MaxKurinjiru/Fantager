<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


final class TeamPresenter extends OnlinePresenter
{
	
	/** @var \App\Controls\Team\Factory @inject */
	public $teamControlFactory;

	/**************************************************************************/

	public function createComponentTeamOverview() {
		$teamRow = $this->teamRow;
		$isMine = true;

		if ( $this->presenterAction == 'detail' && $id = (int) $this->getParameter('id') ) {
			$teamRow = $this->facades->getFacade('team')->findById($id);
			$isMine = false;
		}
		return $this->teamControlFactory->createOverview()->setTeamRow($teamRow)->setMine($isMine);
	}

	public function createComponentTeamCharacters() {
		$teamRow = $this->teamRow;

		return $this->teamControlFactory->createCharacters()->setTeamRow($teamRow);
	}

	/**************************************************************************/

	public function actionDefault() {

	}

	/**************************************************************************/

}
