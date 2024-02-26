<?php

declare(strict_types=1);

namespace App\Controls\Form;

use Nette,
	Nette\Application\UI,
	Nette\Application\UI\Form;

class SignRecover extends \App\Controls\Form\Base {

	/** @var \App\Model\Facades */
	public $facades;

	/** @var \App\Model\Mail @inject */
	public $modelMail;

	/** @var string **/
	public $tpl = __DIR__ . '/signRecover.latte';

	/**************************************************************************/

	public function __construct(
		\App\Model\Facades $facades,
		\App\Model\Mail $modelMail
	) {
		$this->facades = $facades;
		$this->modelMail = $modelMail;
	}

	/**************************************************************************/

	protected function createComponentForm() {

		$form = new Form;

		$form->addEmail('email', 'E-mail')
			->setRequired('Zadejte prosím e-mail');

		$form->addSubmit('send', 'Odeslat');

		$form->addProtection();

		$form->onSuccess[] = [$this, 'processForm'];

		return $form;
	}

	/**************************************************************************/

	public function processForm(Form $form, array $values) {

		$facadeUser = $this->facades->getFacade('user');

		if ( count( $user = $facadeUser->findByEmail($values['email']) ) <= 0 ) {
			$form['email']->addError('Uživatel s tímto emailem neexistuje');
			return;
		}

		$userRow = $user->fetch();

		if ( $recoverToken = $facadeUser->createRecoverToken($userRow) ) {

			$urlRoot = \App\Helper\Config::getFullURL();
			$link = $urlRoot . '/sign/password/' . $recoverToken;

			$body = '
				<h3 class="font-uniq">Fantager</h3>
				<h5>Obnovení hesla</h5>
				<p></p>
				<p>Požádali jste o obnovení hesla,<br>pokračujte kliknutím na odkaz níže pro nastavení nového.</p>
				<p>
					<a href="' . $link . ' "> '. $link .' </a>
				</p>
				<p>Pokud jste tuto akci neprovedli, klidně tento email ignorujte.</p>
			';

			$mail = $this->modelMail->createEmail($body, 'Obnovení hesla');
			$mail->addTo($userRow->email);
			$this->modelMail->send($mail);

			$this->onSuccess($this, $form, $values);
		}
	}

	/**************************************************************************/

}
