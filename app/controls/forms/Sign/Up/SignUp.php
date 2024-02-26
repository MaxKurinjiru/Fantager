<?php

declare(strict_types=1);

namespace App\Controls\Form;

use Nette,
	Nette\Application\UI,
	Nette\Application\UI\Form;

class SignUp extends \App\Controls\Form\Base {

	/** @var \App\Model\Facades */
	public $facades;

	/** @var \App\Model\Mail @inject */
	public $modelMail;

	public $facadeUser;
	public $facadeKingdom;

	/** @var string **/
	public $tpl = __DIR__ . '/signUp.latte';

	/**************************************************************************/

	public function __construct(
		\App\Model\Facades $facades,
		\App\Model\Mail $modelMail
	) {
		$this->facades = $facades;
		$this->facadeUser = $facades->getFacade('user');
		$this->facadeKingdom = $facades->getFacade('kingdom');
		$this->modelMail = $modelMail;
	}

	/**************************************************************************/

	protected function createComponentForm() {

		$form = new Form;

		$kingdoms = $this->facadeKingdom->getKingdomList($withPlayers = true);

		$form->addEmail('email', 'E-mail')
			->setRequired('Zadejte prosím e-mail');

		$form->addText('nickname', 'Herní jméno')
			->setRequired('Zadejte prosím herní jméno');

		$form->addSelect('kingdom', 'Království', $kingdoms);

		$fullKingdoms = array();
		foreach ($kingdoms as $key => $value) {
			if ( $this->facadeKingdom->isKingdomFull($key) ) {
				$fullKingdoms[] = $key;
			}
		}
		if ( !empty($fullKingdoms) ) {
			$form['kingdom']->setDisabled($fullKingdoms);
		}

		$form->addPassword('password', 'Heslo')
			->addRule($form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaků', 6)
			->setRequired('Zadejte prosím heslo');

		$form->addPassword('password_check', 'Heslo znovu (kontrolní)')
			->setRequired('Zadejte prosím kontrolní heslo')
			->addRule($form::EQUAL, 'Hesla se musejí shodovat', $form['password']);

		$form->addSubmit('send', 'Zaregistrovat');

		$form->addProtection();

		$form->onSuccess[] = [$this, 'processForm'];

		return $form;
	}

	/**************************************************************************/

	public function processForm(Form $form, array $values) {

		if ( count( $this->facadeUser->findByEmail($values['email']) ) > 0 ) {
			$form['email']->addError('Uživatel s tímto emailem již existuje');
			return;
		}

		if ( count( $this->facadeUser->findByNickname($values['nickname']) ) > 0 ) {
			$form['nickname']->addError('Uživatel s tímto jménem již existuje');
			return;
		}

		if ( $this->facadeKingdom->isKingdomFull($values['kingdom']) ) {
			$form['kingdom']->addError('Království je již plné');
			return;
		}

		unset($values['password_check']);

		if ( $newUser = $this->facadeUser->create($values) ) {

			$urlRoot = \App\Helper\Config::getFullURL();
			$link = $urlRoot . '/sign/activate/' . $newUser->activation_token;

			$body = '
				<h3 class="font-uniq">Fantager</h3>
				<h5>Potvrzení registrace</h5>
				<p></p>
				<p>Vaše registrace proběhla v pořádku,<br>pokračujte kliknutím na odkaz níže pro aktivaci účtu.</p>
				<p>
					<a href="' . $link . ' "> '. $link .' </a>
				</p>
			';

			$mail = $this->modelMail->createEmail($body, 'Potvrzení registrace');
			$mail->addTo($newUser->email);
			$this->modelMail->send($mail);

			$this->onSuccess($this, $form, $values);
		}
	}

	/**************************************************************************/

}
