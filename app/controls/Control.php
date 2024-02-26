<?php

declare(strict_types=1);

namespace App\Controls;

abstract class Base extends \Nette\Application\UI\Control {

	/** @var string **/
	public $tpl = __DIR__ . '/template.latte';

	/** @var \App\Helper\Config @inject */
	public $cfg;

	/** @var array **/
	public $config;

	/**************************************************************************/

	public function getConfig() {
		$cfg = new \App\Helper\Config;
		$this->config = $cfg->getConfig();
		return $this->config;
	}

	public function beforeRender() {

	}

	public function render() {
		$this->beforeRender();
		$this->template->render($this->tpl);
	}

	/**************************************************************************/
}
