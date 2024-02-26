<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


final class PlayerPresenter extends OnlinePresenter
{
	
	/** @var \App\Controls\Player\Factory @inject */
	public $userControlFactory;
	
	/**************************************************************************/

	public function createComponentPlayerOverview() {
		$userRow = $this->userRow;
		$isMine = true;

		if ( $this->presenterAction == 'detail' && $id = (int) $this->getParameter('id') ) {
			$userRow = $this->facades->getFacade('user')->findById($id);
			$isMine = false;
		}
		return $this->userControlFactory->createOverview()->setUserRow($userRow)->setMine($isMine);
	}

	/**************************************************************************/

	public function actionDefault() {

	}

	/**************************************************************************/

}
