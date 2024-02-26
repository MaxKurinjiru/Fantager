<?php

declare(strict_types=1);

namespace App\Controls\Form;

use Nette,
	Nette\Application\UI,
	Nette\Application\UI\Form;

class SignIn extends \App\Controls\Form\Base {

	/** @var string **/
	public $tpl = __DIR__ . '/signIn.latte';

	/**************************************************************************/

	protected function createComponentForm() {

		$form = new Form;

		$form->addEmail('email', 'e-mail')
			->setRequired($this->presenter->translator->translate('system.message.required.login_email'));

		$form->addPassword('password', 'heslo')
			->setRequired($this->presenter->translator->translate('system.message.required.login_password'));

		$form->addSubmit('send', $this->presenter->translator->translate('system.confirm.login'));

		$form->addProtection();

		$form->onSuccess[] = [$this, 'processForm'];

		return $form;
	}

	/**************************************************************************/

	public function processForm(Form $form, array $values) {
		$this->onSuccess($this, $form, $values);
	}

	/**************************************************************************/

}
