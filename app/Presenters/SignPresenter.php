<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;

final class SignPresenter extends BasePresenter
{

	/** @var \App\Controls\Form\Sign\Factory @inject */
	public $signFactory;

	/** @var \App\Model\Mail @inject */
	public $modelMail;

	/** @var \App\Model\Facades @inject */
	public $facades;

	/**************************************************************************/

	public function createComponentSignInForm() {

		$signForm = $this->signFactory->createSignIn();

		$self     = $this;

		$signForm->onSuccess[] = function($cmp, $form, $values) use ($self) {

			$user = $this->facades->getFacade('user')->findByEmail($values['email']);

			try {
				$user = $this->getUser();
				$user->login($values['email'], $values['password']);
			} catch (\Nette\Security\AuthenticationException $e) {
				$form->addError($e->getMessage());
				return;
			}

			$self->redirect('Homepage:');

		};

		return $signForm;
	}

	public function createComponentSignRecoverForm() {

		$signForm = $this->signFactory->createSignRecover();

		$self     = $this;

		$signForm->onSuccess[] = function($cmp, $form, $values) use ($self) {
			$self->redirect('Sign:recover', ['id' => 'sent']);
		};

		return $signForm;
	}

	public function createComponentSignUpForm() {

		$signForm = $this->signFactory->createSignUp();

		$self     = $this;

		$signForm->onSuccess[] = function($cmp, $form, $values) use ($self) {
			$self->redirect('Sign:up', ['id' => 'sent']);
		};

		return $signForm;
	}

	public function createComponentSignPasswordForm() {

		$signForm = $this->signFactory->createSignPassword();

		$self     = $this;

		$signForm->onSuccess[] = function($cmp, $form, $values) use ($self) {
			$self->redirect('Sign:password', ['id' => 'sent']);
		};

		return $signForm;
	}

	/**************************************************************************/

	public function actionOut() {
		$this->user->logout(true);
		$this->redirect('Homepage:default');
	}

	/**************************************************************************/

	public function actionActivate() {
		$token = $this->getParameter('id');

		$this->template->activation_success = false;

		// no token
		if ( empty($token) ) {
			return;
		}

		// no user
		if (!$row = $this->facades->getFacade('user')->findByActivation($token)->fetch()) {
			return;
		}

		$this->template->activation_success = true;

		// already activated
		if ( !empty($row->activated) ) {
			return;
		}

		$nowDate = new \Nette\Utils\DateTime();
		$activated = $nowDate->format('Y-m-d H:i:s');
		$row->update([
			'activated' => $activated,
		]);
		
		// discutable
		// should be here, or right after register?
		$this->facades->getFacade('team')->assignTeam($row->id, $row->kingdom);
	}

	/**************************************************************************/

}
