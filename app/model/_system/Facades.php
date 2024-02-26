<?php

declare(strict_types=1);

namespace App\Model;

class Facades implements \App\Model\Interfaces\Facade {

	/** @var \Nette\Localization\ITranslator @inject */
	public $translator;

	/** @var \App\Helper\Config @inject */
	public $cfg;

	/** @var array **/
	public $config;

    /** @var array */
	protected $facades = [];

	/**************************************************************************/

    public function __construct(
		\App\Helper\Config $cfg,
		\Nette\Localization\ITranslator $translator
	) {
		$this->translator = $translator;
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
		return $this;
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

}
