<?php

declare(strict_types=1);

namespace App\Model\Facade;

use Nette;

final class User extends DB
{
	/** @var \Nette\Security\Passwords */
	public $passwords;

	/** @var string */
	public $table = 'user';

	/**************************************************************************/

	public function addPasswords(
		\Nette\Security\Passwords $passwords
	) {
		$this->passwords = $passwords;
	}

	/**************************************************************************/

	public function hashPassword(string $password) {
		return $this->passwords->hash($password);
	}

	public function verifyPassword(string $password, string $hash) {
		return $this->passwords->verify($password, $hash);
	}

	public function needsPasswordRehash(string $hash) {
		return $this->passwords->needsRehash($hash);
	}

	/**************************************************************************/

	// deprecated
	public function tableUsersOnly() {
		return $this->table()->where('id > 1');
	}

	// deprecation
	// should not be used, use playersInKingdom instead!
	public function tableActiveUsers() {
		return $this->table()->where('id > 1');
	}

	// default "all players"
	public function playersInKingdom($kingdom = null) {
		$selection = $this->table()->where('id > 1');
		if (!empty($kingdom)) {
			$selection->where(['kingdom' => $kingdom]);
		}
		return $selection;
		//return $this->table()->where(['kingdom' => $id]);
	}

	/**************************************************************************/

	// discutable
	public function fetchUsersOnly($kingdom) {
		$array = [];
		foreach ($this->playersInKingdom($kingdom) as $row) {
			$array[] = $row->nickname;
		}
		return $array;
	}

	/**************************************************************************/

	public function findByEmail(string $email) {
		return $this->table()->where(array('email' => $email));
	}

	public function findByActivation(string $token) {
		return $this->table()->where(array('activation_token' => $token));
	}

	public function findByRecovery(string $token) {
		return $this->table()->where(array('password_token' => $token));
	}

	// deprecated
	// todo: change to webalized search
	public function findByNickname(string $nick) {
		return $this->table()->where(array('nickname' => $nick));
	}

	/**************************************************************************/

	// discutable
	public function getWeekGrow() {
		return count($this->table()->where('DATE(created) >= DATE( NOW() - INTERVAL 7 DAY ) AND activated IS NOT NULL'));
	}

	// discutable
	public function getActive() {
		return count($this->table()->where('DATE(last_action) >= DATE( NOW() - INTERVAL 7 DAY ) AND activated IS NOT NULL'));
	}

	// discutable
	public function getOnlineSelection() {
		return $this->table()->where('last_action >= NOW() - INTERVAL 10 MINUTE AND activated IS NOT NULL');
	}

	// discutable
	public function getOnline() {
		return count($this->getOnlineSelection());
	}

	// discutable
	public function getOnlineNames() {
		$array = [];
		foreach ($this->getOnlineSelection() as $row) {
			$array[] = $row->nickname;
		}

		if ( empty($array) ) {
			return $this->translator->translate('system.user.noone_online');
		}

		return implode('<br>', $array);
	}

	/**************************************************************************/

	public function createRecoverToken(\Nette\Database\Table\ActiveRow $user) {
		$token = \App\Helper\Token::createToken();

		while ( count( $this->findByRecovery($token) ) > 0 ) {
			$token = \App\Helper\Token::createToken();
		}

		$user->update(['password_token' => $token]);

		return $token;
	}

	public function createActivationToken(\Nette\Database\Table\ActiveRow $user) {
		$token = \App\Helper\Token::createToken();

		while ( count( $this->findByActivation($token) ) > 0 ) {
			$token = \App\Helper\Token::createToken();
		}

		$user->update(['activation_token' => $token]);

		return $token;
	}

	/**************************************************************************/

	// temporarily?
	public function webalizeNames() {
		foreach ($this->playersInKingdom() as $row) {
			$this->webalizeName($row);
		}
	}
	
	// temporarily?
	public function webalizeName(\Nette\Database\Table\ActiveRow $user) {
		$webalized = \Nette\Utils\Strings::webalize($user->nickname);
		$user->update([
			'webalized' => $webalized
		]);
	}

	/**************************************************************************/

	public function create(array $data) {

		if ( !empty($data['password']) ) {
			$password = $this->hashPassword($data['password']);
			$data['password'] = $password;
		}

		$token = \App\Helper\Token::createToken();

		while ( count( $this->findByActivation($token) ) > 0 ) {
			$token = \App\Helper\Token::createToken();
		}
		$data['activation_token'] = $token;

		$webalized = \Nette\Utils\Strings::webalize($data['nickname']);
		$data['webalized'] = $webalized;

		return $this->save($data);
	}

	/**************************************************************************/

}
