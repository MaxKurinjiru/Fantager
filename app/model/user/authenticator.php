<?php

namespace App\Model;

use Nette;
use Nette\Security\IIdentity;
use Nette\Utils\DateTime;

class Authenticator implements \Nette\Security\Authenticator, \Nette\Security\IdentityHandler {

	use \Nette\SmartObject;

	/** @var \App\Model\Facades @inject */
	public $facades;

	/**************************************************************************/

	public function __construct(
		\App\Model\Facades $facades
	) {
		$this->facades = $facades;
	}

	/**************************************************************************/

	public function sleepIdentity(IIdentity $identity): IIdentity
	{
		// return sleep identity with authtoken in ID
		return new Nette\Security\Identity($identity->activation_token);
	}

	public function wakeupIdentity(IIdentity $identity): ?IIdentity
	{
		// replace sleep identity with full identity like in authenticate()
		$row = $this->facades->getFacade('user')->findByActivation($identity->getId())->fetch();

		if (!$row) {
			return null;
		}

		// last login update
		$lastLogin = \App\Helper\Format::getTimestamp();
		$row->update([
			'last_login' => $lastLogin,
			'last_action' => $lastLogin
		]);

		return $this->getIdentityFromDB($row);
	}

	/**************************************************************************/

	public function getRoles($row) {
		// todo
		return 'default';
		//return $roles;
	}

	public function getIdentityFromDB($row) {
		// user row to array with no password
		$arr = $row->toArray();
		unset($arr['password']);

		// user roles
		$role = $this->getRoles($row);

		// create user identity
		return new Nette\Security\Identity($arr['id'], $role, $arr);
	}

	/**************************************************************************/

	public function authenticate(string $email, string $password): IIdentity {
		$row = $this->facades->getFacade('user')->findByEmail($email)->fetch();

		// exceptions

		// user not exist
		if (!$row) {
			throw new Nette\Security\AuthenticationException(
				$this->facades->getFacade('user')->translator->translate('system.message.wrong_credentials')
			);
		}

		// wrong credentials
		if (!$this->facades->getFacade('user')->verifyPassword($password, $row->password)) {
			throw new Nette\Security\AuthenticationException(
				$this->facades->getFacade('user')->translator->translate('system.message.wrong_credentials')
			);
		}

		// not activated
		if (!$row->activated) {
			throw new Nette\Security\AuthenticationException(
				$this->facades->getFacade('user')->translator->translate('system.message.not_activated')
			);
		}

		// banned
		if ($row->ban) {
			throw new Nette\Security\AuthenticationException(
				$this->facades->getFacade('user')->translator->translate('system.message.has_ban')
			);
		}

		// no exceptions

		// rehash password if needed
		if ($this->facades->getFacade('user')->needsPasswordRehash($row->password)) {
			$row->update([
				'password' => $this->facades->getFacade('user')->hashPassword($password)
			]);
		}

		// last login update
		$lastLogin = \App\Helper\Format::getTimestamp();
		$row->update([
			'last_login' => $lastLogin,
			'last_action' => $lastLogin
		]);

		// login activity
		$this->facades->getFacade('activity')->saveLogin($row);

		return $this->getIdentityFromDB($row);
	}

	/**************************************************************************/

}
