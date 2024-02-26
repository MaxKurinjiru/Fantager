<?php

namespace App\Macros;
use Latte\MacroNode;
use Latte\PhpWriter;

class BootstrapIcon extends \Latte\Macros\MacroSet {

	public static function install(\Latte\Compiler $compiler) {
		$set = new static($compiler);
		$set->addMacro("bs_icon", [$set, 'macroBsIcon']);
	}

	public function macroBsIcon(MacroNode $node, PhpWriter $writer)
	{
		$root  = explode(';', $node->args);

		return $writer->write('echo \'<use xlink:href="/images/bootstrap-icons.svg#' . $root[0] . '"/>\'');
	}

}
