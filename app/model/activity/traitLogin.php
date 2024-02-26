<?php

declare(strict_types=1);

namespace App\Model\Facade;

trait ActivityLogin
{
    /** @var string */
    public $tableLogin = 'activity_login';

	/**************************************************************************/

    public function tableLogin() {
		return $this->table($this->tableLogin);
	}

    /**************************************************************************/

    public function saveLogin($userRow) {
		$array = [];
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			$array['ip'] = $_SERVER['REMOTE_ADDR'];
		}
		if (!empty($_SERVER['HTTP_USER_AGENT'])) {
			$array['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
		}

		if (!empty($array)) {
			$array['user'] = $userRow->id;
			$this->save($array, $this->tableLogin);
		}
	}

	/**************************************************************************/

}
