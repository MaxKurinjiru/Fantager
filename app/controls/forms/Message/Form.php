<?php

declare(strict_types=1);

namespace App\Controls\Form;

use Nette,
	Nette\Application\UI,
	Nette\Application\UI\Form;

class Message extends \App\Controls\Form\Base {

	/** @var \App\Model\Facade\User */
	public $facadeUser;

	/** @var string **/
	public $tpl = __DIR__ . '/message.latte';

	public $kingdom;
	public $userlist;

	/**************************************************************************/

	public function __construct(
		\App\Model\Facade\User $facadeUser,
		int $kingdom = 1
	) {
		$this->facadeUser = $facadeUser;

		$this->userlist = $this->facadeUser->fetchUsersOnly($kingdom);
	}

	/**************************************************************************/

	protected function createComponentForm() {

		$form = new Form;

		$form->addText('nickname', 'Adresát')
			->setRequired('Zadejte prosím herní jméno')
			->addRule($form::IS_IN, 'Uživatel s tímto jménem neexistuje', $this->userlist);

		if ( $default = $this->presenter->getParameter('write') ) {
			$form['nickname']->setDefaultValue($default);
		}

		$form->addText('title', 'Předmět zprávy')
			->addRule($form::MAX_LENGTH, 'Předmět musí mít maximálně %d znaků', 32);

		$form->addTextArea('text', 'Text zprávy')
			->addRule($form::MIN_LENGTH, 'Zpráva musí mít alespoň %d znaků', 8)
			->setHtmlAttribute('rows', 8);

		$form->addSubmit('send', 'Odeslat');

		$form->addProtection();

		$form->onSuccess[] = [$this, 'processForm'];

		return $form;
	}

	/**************************************************************************/

	public function processForm(Form $form, array $values) {
		$userRow = $this->facadeUser->findByNickname($values['nickname']);

		$this->facadeUser->saveMail([
			'from' => $this->presenter->userRow->id,
			'user' => $userRow->fetch()->id,
			'title' => $values['title'],
			'text' => $values['text']
		]);

		//$this->onSuccess($this, $form, $values);
		$this->presenter->redirect('Mail:default');

	}

	/**************************************************************************/

	public function beforeRender() {
		$this->template->userlist = $this->userlist;
	}

	/**************************************************************************/

}
