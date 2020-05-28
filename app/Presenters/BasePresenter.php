<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


class BasePresenter extends Nette\Application\UI\Presenter
{
	/** @var Nette\Security\User @inject **/
	public $user;


	// Presenter and action (for Permission primary)
	public $presenterName;
	public $presenterAction;

	protected function startup() {
		parent::startup();

		$this->presenterName = \Nette\Utils\Strings::after($this->getName(), ':', -1);
		$this->presenterAction = $this->presenter->getAction();

		$authenticator = new Nette\Security\SimpleAuthenticator([
			'admin' => 'root',
		]);

		$this->user->setAuthenticator($authenticator);

		if ($this->user->isLoggedIn()) {

		} else {

			/*try {
				// pokusíme se přihlásit uživatele...
				$this->user->login('admin', 'root');

				// ...a v případě úspěchu presměrujeme na další stránku
				$this->redirect('this');

			} catch (Nette\Security\AuthenticationException $e) {
				$this->flashMessage('Uživatelské jméno nebo heslo je nesprávné', 'warning');
			}
			*/

		}
	}
}
