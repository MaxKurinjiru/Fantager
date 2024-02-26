<?php

declare(strict_types=1);

namespace App\Controls\Form\Team;

use Nette,
	Nette\Application\UI,
	Nette\Application\UI\Form;

class Rename extends \App\Controls\Form\Base {

	/** @var \App\Model\Facades */
	public $facades;

	public $facadeTeam;
	public $teamRow;

	/** @var string **/
	public $tpl = __DIR__ . '/template.latte';

	/**************************************************************************/

	public function __construct(
		\App\Model\Facades $facades
	) {
		$this->facades = $facades;
		$this->facadeTeam = $this->facades->getFacade('team');
	}

	/**************************************************************************/

	public function setTeamRow($teamRow) {
		$this->teamRow = $teamRow;
		return $this;
	}

	/**************************************************************************/

	protected function createComponentForm() {

		$form = new Form;

		$form->addText('name', '')
			->addRule($form::MIN_LENGTH, $this->presenter->translator->translate('system.message.required.team_name_min_length'), 3)
			->addRule($form::MAX_LENGTH, $this->presenter->translator->translate('system.message.required.team_name_max_length'), 64)
			->setRequired($this->presenter->translator->translate('system.message.required.team_name'))
			->setDefaultValue($this->teamRow->name);

		$form->addSubmit('send', $this->presenter->translator->translate('system.team.rename'));

		$form->addProtection();

		$form->onSuccess[] = [$this, 'processForm'];

		return $form;
	}

	/**************************************************************************/

	public function processForm(Form $form, array $values) {

		$newName = $values['name'];

		if ( $this->facadeTeam->checkNameExist($newName) ) {
			$form->addError($this->presenter->translator->translate('system.message.team_change_name_taken'));
			return;
		}

		$webalized = \Nette\Utils\Strings::webalize($newName);

		$this->teamRow->update([
			'name' => $newName,
			'webalized' => $webalized,
			'renamed' => \App\Helper\Format::getTimestamp()
		]);

		$this->onSuccess($this, $form, $values);
	}

	/**************************************************************************/

}
