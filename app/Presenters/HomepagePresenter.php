<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


final class HomepagePresenter extends BasePresenter
{

	/** @var \App\Controls\Form\Sign\Factory @inject */
	public $signFactory;

	/** @var \App\Controls\OnlinePlayersStats\Factory @inject */
	public $onlinePlayersStatsFactory;

	/**************************************************************************/

	public function createComponentOnlinePlayersStats() {
		return $this->onlinePlayersStatsFactory->create();
	}

	public function createComponentSignInForm() {

		$facadeUser = $this->facades->getFacade('user');

		$signForm = $this->signFactory->createSignIn();

		$self = $this;

		$signForm->onSuccess[] = function($cmp, $form, $values) use ($self, $facadeUser) {

			$user = $facadeUser->findByEmail($values['email']);

			try {
				$user = $this->getUser();
				$user->login($values['email'], $values['password']);
			} catch (\Nette\Security\AuthenticationException $e) {
				$form->addError($e->getMessage());
				return;
			}

			$self->redirect('Dashboard:');

		};

		return $signForm;
	}

	/**************************************************************************/

	public function beforeRender() {
		$facadeUser = $this->facades->getFacade('user');

		$user = null;
		if ($this->user->isLoggedIn()) {
			$identity = $this->user->getIdentity();
			$user = $facadeUser->findById($identity->id);
		}

		$this->template->user = $user;
	}

	/**************************************************************************/

}
