<?php

declare(strict_types=1);

namespace App\Helper;

use Nette;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

final class Format
{
	use Nette\SmartObject;

	/**************************************************************************/

	public static function setJson($j) {
		try {
			$json = Json::encode($j);
		} catch (Nette\Utils\JsonException $e) {
			// Exception handling
			\Tracy\Debugger::barDump($e, 'setJson error');
		}

		return $json;
	}

	public static function getJson($j) {
		try {
			$json = Json::decode($j);
		} catch (Nette\Utils\JsonException $e) {
			// Exception handling
			\Tracy\Debugger::barDump($e, 'getJson error');
		}

		return $json;
	}

	/**************************************************************************/

	public static function priceRandomizer($price, $randomized = true) {
		if (!$randomized) {
			return (int) floor($price);
		}

		$price = (int) floor($price);
		$r = (int) floor($price * 0.1);

		return (int) floor(rand($price - $r, $price + $r));
	}

	/**************************************************************************/

	public static function formatDateDB($date, $format = 'd.m.Y') {
		if (is_object($date)) {
			return $date->format($format);
		}

		$date = strtotime($date);

		$date = date($format, $date);
		return $date;
	}

	/**************************************************************************/
	public static function formatAge($age) {
		return floor($age / 10);
	}
	/**************************************************************************/

	public static function getTimestamp() {
		$nowDate = new DateTime();
		return $nowDate->format('Y-m-d H:i:s');
	}

	public static function getTime($timestamp) {
		$time = DateTime::from($timestamp);
		return $time->format('d.m.Y H:i');
	}

	/**************************************************************************/

	public static function formatMoney(int $money) {
		$money = number_format($money, 0, '', '.');
		return $money;
	}

	/**************************************************************************/
}
