<?php

namespace App\Controls\Team;

class Overview extends \App\Controls\Base {

    /** @var \App\Model\Facades @inject */
	public $facades;

	/** @var \App\Controls\Form\Team\Factory @inject */
	public $factory;

	/** @var string **/
	public $tpl = __DIR__ . '/template.latte';

	public $teamRow;
	public $isMine;

	/**************************************************************************/

	public function __construct(
        \App\Model\Facades $facades,
		\App\Controls\Form\Team\Factory $factory
	) {
        $this->facades = $facades;
		$this->factory = $factory;

		$this->getConfig();
	}

	/**************************************************************************/

	public function setTeamRow($teamRow) {
		$this->teamRow = $teamRow;
		return $this;
	}

	public function setMine($isMine) {
		$this->isMine = $isMine;
		return $this;
	}

	/**************************************************************************/

	public function handleRename() {
		if (!empty($this->teamRow->renamed)) {
			return;
		}

		$this->template->rename = true;
	}

	/**************************************************************************/

	public function createComponentRenameForm() {
		$control = $this->factory->createRename()->setTeamRow($this->teamRow);

		$self = $this;

		$control->onSuccess[] = function($cmp, $form, $values) use ($self) {
			$self->presenter->redirect('Team:default');
		};

		return $control;
	}

	/**************************************************************************/

	public function beforeRender()
	{
		$this->template->teamRow = $this->teamRow;
		$this->template->isMine = $this->isMine;
		$this->template->league = $this->facades->getFacade('league')->findLeagueByTeam($this->teamRow->id)->fetch();
		$this->template->insolventDaysLimit = $this->config['insolventDaysLimit'];
	}

	/**************************************************************************/

}
