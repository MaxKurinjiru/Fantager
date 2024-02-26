<?php

declare(strict_types=1);

namespace App\Model\Facade;

abstract class DB implements \App\Model\Interfaces\Facade {
	
	/** @var \Nette\Database\Context @inject */
	protected $db;

	/** @var \Nette\Localization\ITranslator @inject */
	public $translator;

	/** @var \App\Model\Mail @inject */
	public $modelMail;

	/** @var \App\Helper\Config @inject */
	public $cfg;

	/** @var array **/
	public $config;

	/** @var string */
	public $table;

	/** @var array */
	protected $facades = [];

	/**************************************************************************/

	public function __construct(
		\Nette\Database\Context $db,
		\App\Model\Mail $modelMail,
		\Nette\Localization\ITranslator $translator,
		\App\Helper\Config $cfg
	) {
		$this->translator = $translator;
		$this->modelMail = $modelMail;
		$this->db = $db;
		$this->cfg = $cfg;
		$this->config = $this->cfg->getConfig();
	}

	/**************************************************************************/

	/**
	* @var string $name
	* @var \App\Model\Interfaces\Facade $facade
	*/
	public function addFacade(string $name, \App\Model\Interfaces\Facade $facade) {
		$this->facades[$name] = $facade;
	}

	/**
    * @var string $name
    * @return \App\Model\Interface\Facade
    */
	public function getFacade(string $name) {
		if (!isset($this->facades[$name])) {
			return null;
		}

		return $this->facades[$name];
	}

	/**************************************************************************/

	/* DB defaults */

	public function table(string $table = null) {
		if ( empty($table) ) {
			$table = $this->table;
		}
		return $this->db->table($table);
	}

	public function findById(int $id, string $table = null) {
		return $this->table($table)->get($id);
	}

	public function delete(int $id, string $table = null) {

		if (!$row = $this->findById($id, $table)) {
			return false;
		}

		$row->delete();

		return true;
	}

	public function save(array $data, string $table = null) {

		$table = $this->table($table);

		// new row
		if ( empty($data['id']) ) {
			unset($data['id']);
			return $table->insert($data);
		}

		// update, but not exist
		if (!$row = $table->get($data['id'])) {
			throw new \Nette\Database\ConstraintViolationException('Data not exist');
		}

		try {
			$row->update($data);
		}
		catch(Exception $E) {
			\Tracy\Debugger::barDump('error db save');
		}

		return $row;
	}

	/**************************************************************************/

}
