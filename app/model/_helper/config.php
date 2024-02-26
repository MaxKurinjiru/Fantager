<?php

declare(strict_types=1);

namespace App\Helper;

use Nette;

final class Config
{
	use Nette\SmartObject;

	public $kingdom = 1;
	public $kingdom_locale;

	public $rows = [];

	/** @var array */
	private static $config = [
		'server' => [
			'name' => 'Fantager',
			'http' => 'https',
			'url' => 'fantager.cz',
			'localhost' => 'fantager',
		],
		'mailer' => [
			'email' => 'noreply@fantager.cz',
			'name' => 'Fantager'
		],
		'lang' => [
			'cs' => 'cs_CZ',
			'en' => 'en_EN',
		],
		'ticketPrice' => 2,
		'insolventDaysLimit' => 21,
		'league' => [
			'teams' => 10,			// teams in group
			'list' => [
				// release => 180 players
				/*
				1 => [				// # of league
					'groups' => 1,	// groups in league
					'top' => 0,		// # of teams promoted - league up
					'bottom' => 6	// # of teams degraded - league down
				],
				2 => [
					'groups' => 3,
					'top' => 2,
					'bottom' => 6
				],
				3 => [
					'groups' => 6,
					'top' => 3,
					'bottom' => 4
				],
				4 => [
					'groups' => 8,
					'top' => 3,
					'bottom' => 0
				]
				*/
				// beta settings => 60 players
				1 => [				// # of league
					'groups' => 1,	// groups in league
					'top' => 0,		// # of teams promoted - league up
					'bottom' => 4	// # of teams degraded - league down
				],
				2 => [
					'groups' => 2,
					'top' => 2,
					'bottom' => 3
				],
				3 => [
					'groups' => 3,
					'top' => 2,
					'bottom' => 0
				]
			]
		],
		'character' => [
			'team_limit' => 20,
			'limit_new' => 12,
			'stats' => [
				'STR', 'DEX', 'CON', 'SPD', 'INT', 'WIL', 'CHA', 'LUC'
			],
			'weapon' => [
				'cut', 'thrust', 'blunt', 'pole', 'bow', 'crossbow'
			],
			'magic' => [
				'life', 'death', 'chaos', 'order', 'nature'
			],
		],
		'staff' => [
			'salary' => 60,
			'list' => [
				'res', 'heal', 'smith', 'prop', 'train'
			]
		],
		'arena' => [
			1 => [
				'lvl' => 1,
				'price' => 2500,
				'seats' => 500,
				'days' => 0
			],
			2 => [
				'lvl' => 2,
				'price' => 8000,
				'seats' => 1000,
				'days' => 5
			],
			3 => [
				'lvl' => 3,
				'price' => 20000,
				'seats' => 2000,
				'days' => 10
			],
			4 => [
				'lvl' => 4,
				'price' => 50000,
				'seats' => 4000,
				'days' => 14
			],
			5 => [
				'lvl' => 5,
				'price' => 80000,
				'seats' => 6000,
				'days' => 20
			],
			6 => [
				'lvl' => 6,
				'price' => 150000,
				'seats' => 10000,
				'days' => 28
			],
			7 => [
				'lvl' => 7,
				'price' => 250000,
				'seats' => 12000,
				'days' => 35
			],
			8 => [
				'lvl' => 8,
				'price' => 500000,
				'seats' => 20000,
				'days' => 50
			]
		]
	];


	/**************************************************************************/

	public function getConfig() {
		return self::$config;
	}

	/**************************************************************************/

	public function setRows($rows) {
		foreach ($rows as $name => $row) {
			$this->setRow($name, $row);
		}
	}

	public function setRow($name, $row) {
		$this->rows[$name] = $row;
	}

	public function getRow($name) {
		if ( empty($this->rows[$name]) ) {
			return null;
		}
		return $this->rows[$name];
	}

	/**************************************************************************/

	public function setKingdom($kingdom) {
		$this->kingdom = $kingdom->id;
		$this->kingdom_locale = $kingdom->lang;
	}

	/**************************************************************************/

	public static function kingdomSlots() {
		$i = 0;
		foreach (self::$config['league']['list'] as $league) {
			$i += $league['groups'];
		}

		$teams = self::$config['league']['teams'];

		return $teams * $i;
	}

	/**************************************************************************/

	public static function getStaffSalary() {
		return self::$config['staff']['salary'];
	}

	public static function staffList() {
		return self::$config['staff']['list'];
	}

	/**************************************************************************/

	public static function getFullURL() {
		// is this necessary or .. ?
		$subdomain = 'www.';
		$url = self::$config['server']['http'] . '://' . $subdomain . self::$config['server']['url'];
		return $url;
	}

	public static function getNiceFullURL() {
		$url = self::$config['server']['url'];
		return $url;
	}

	/**************************************************************************/
}
