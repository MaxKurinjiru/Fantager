<?php

declare(strict_types=1);

namespace App\Model\Facade;

use Nette;

final class Session extends DB
{
	/** @var string */
	public $table = 'session';

	/**************************************************************************/

    public function save(array $data) {
		return $this->save($data);
	}

	/**************************************************************************/

	public function findSessionById(int $id) {
		return $this->table()->where(['id' => $id]);
	}

	public function findSessionInKingdom(int $id) {
		return $this->table()->where(['kingdom' => $id])->order('created DESC');
	}

	public function getCurrentSessionIn(int $kindom) {
		return $this->findSessionInKingdom($kingdom)->fetch();
	}

	/**************************************************************************/

	// discutable
	// # new session
	// # end session
	/**
	 * shoult this be here, or in cron job for that?
	*/

	/**************************************************************************/

}
