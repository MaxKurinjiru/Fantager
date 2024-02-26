<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use \Tracy\Debugger;


class BasePresenter extends Nette\Application\UI\Presenter
{
	/** @persistent */
	public $locale;

	/** @persistent */
	public $kingdom;

	/** @var \Nette\Localization\ITranslator @inject */
	public $translator;

	/** @var \Contributte\Translation\LocalesResolvers\Session @inject */
	public $translatorSessionResolver;

	/** @var \Nette\Security\User @inject **/
	public $user;

	/** @var \App\Model\Facades @inject **/
	public $facades;

	/** @var \App\Helper\Config @inject */
	public $cfg;

	/** @var array **/
	public $config;

	/** @var string **/
	public $defaultAction;

	/** @var string **/
	public $presenterName;

	/** @var string **/
	public $presenterAction;

	/**************************************************************************/

	protected function startup() {
		parent::startup();
		// config
		$this->config = $this->cfg->getConfig();

		// moduled presenter
		$this->presenterName = \Nette\Utils\Strings::after($this->getName(), ':', -1);
		// based presenter
		if ( !$this->presenterName ) {
			$this->presenterName = $this->getName();
		}
		// presenter action
		$this->presenterAction = $this->presenter->getAction();

		// redirecting default actions if set
		$this->defaultRedirect();

		// localization
		//$this->translator->setLocale($this->locale);

		$this->template->locale = $this->locale;
	}

	/**************************************************************************/

	// Redirect from :default -> defaultAction ( if set )
	protected function defaultRedirect() {
		/*
		if ( !empty($this->defaultAction) && $this->presenterAction == 'default' ) {
			$this->redirect( $this->presenterName . ':' . $this->defaultAction );
		}
		*/
	}

	/**************************************************************************/
}
