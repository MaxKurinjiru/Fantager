<?php

namespace App\Controls\Team;

class Characters extends \App\Controls\Base {

    /** @var \App\Model\Facades @inject */
	public $facades;

	/** @var \App\Controls\Form\Team\Factory @inject */
	public $factory;

	/** @var string **/
	public $tpl = __DIR__ . '/template.latte';

	public $heroes;

	/**************************************************************************/

	public function __construct(
        \App\Model\Facades $facades
	) {
        $this->facades = $facades;

		$this->getConfig();
	}

	/**************************************************************************/

	public function setTeamRow($teamRow) {
		$this->heroes = $this->facades->getFacade('character')->findAllByTeam($teamRow->id)->fetchAll();
		return $this;
	}

	/**************************************************************************/

	public function beforeRender()
	{
		$this->template->heroes = $this->heroes;
		$this->template->config = $this->config;
	}

	/**************************************************************************/

}
