<?php

declare(strict_types=1);

namespace App\Controls\Form;

use Nette,
	Nette\Application\UI,
	Nette\Application\UI\Form;

class SignPassword extends \App\Controls\Form\Base {

	/** @var \App\Model\Facades */
	public $facades;

	public $facadeUser;

	/** @var string **/
	public $tpl = __DIR__ . '/signPassword.latte';

	/**************************************************************************/

	public function __construct(
		\App\Model\Facades $facades
	) {
		$this->facades = $facades;
		$this->facadeUser = $this->facades->getFacade('user');
	}

	/**************************************************************************/

	protected function createComponentForm() {

		$token = $this->presenter->getParameter('id');

		$form = new Form;

		$form->setAction($this->presenter->link('Sign:password', ['id' => $token]));

		$form->addPassword('password', 'Heslo')
			->addRule($form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaků', 6)
			->setRequired('Zadejte prosím heslo');

		$form->addPassword('password_check', 'Heslo znovu (kontrolní)')
			->setRequired('Zadejte prosím kontrolní heslo')
			->addRule($form::EQUAL, 'Hesla se musejí shodovat', $form['password']);

		$form->addSubmit('send', 'Odeslat');

		$form->addProtection();

		if ( count( $this->facadeUser->findByRecovery($token) ) <= 0 ) {
			$form->addError('Neplatný obnovovací kód');
		}

		$form->onSuccess[] = [$this, 'processForm'];

		return $form;
	}

	/**************************************************************************/

	public function processForm(Form $form, array $values) {

		$token = $this->presenter->getParameter('id');

		if ( count( $user = $this->facadeUser->findByRecovery($token) ) <= 0 ) {
			$form->addError('Uživatel s tímto emailem neexistuje');
			return;
		}

		$password = $this->facadeUser->hashPassword($values['password']);

		$row = $user->fetch();

		$row->update([
			'password' => $password,
			'password_token' => null
		]);

		$this->onSuccess($this, $form, $values);
	}

	/**************************************************************************/

}
