<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use \Tracy\Debugger;


class OnlinePresenter extends BasePresenter
{

	/** @var \App\Model\Facades @inject */
	public $facades;

	/** @var \App\Controls\OnlineMenu\Factory @inject */
	public $onlineMenuFactory;

	/** @var \Nette\Database\Table\ActiveRow **/
	public $userRow;
	/** @var \Nette\Database\Table\ActiveRow **/
	public $teamRow;
	/** @var \Nette\Database\Table\ActiveRow **/
	public $arenaRow;

	/**************************************************************************/

	protected function startup() {
		parent::startup();

		// if user not exist or not loged
		if ( empty($this->user) || !$this->user->isLoggedIn() ) {
			$this->redirect('Homepage:default');
		}

		// get user identity
		$identity = $this->user->getIdentity();

		// user relevant data rows
		// used by most actions and templates
		$this->userRow = $this->facades->getFacade('user')->findById($identity->id);
		$this->teamRow = $this->facades->getFacade('team')->findByUser($identity->id)->fetch();
		$this->arenaRow = $this->facades->getFacade('arena')->findByTeam($this->teamRow->id);

		// ban -> logout
		if ($this->userRow->ban) {
			$this->redirect('Sign:out');
		}

		// update user last action
		$this->userRow->update([
			'last_action' => \App\Helper\Format::getTimestamp()
		]);
	}

	/**************************************************************************/

	public function createComponentOnlineMenu() {
		return $this->onlineMenuFactory->create();
	}

	/**************************************************************************/

	public function beforeRender() {
		$this->template->presenterName = $this->presenterName;
		$this->template->presenterAction = $this->presenterAction;

		$this->template->userRow = $this->userRow;
		$this->template->teamRow = $this->teamRow;
		$this->template->arenaRow = $this->arenaRow;
	}

	/**************************************************************************/

}
