<?php

declare(strict_types=1);

namespace App\Model;

use Nette;


final class Character
{
  private $id;
  private $race;
  private $sex;
  private $old;
  private $lvl;
  private $exp;
  private $name;
  
  private $hp_max;
  private $hp;
  private $mp_max;
  private $mp;
  
  private $stats = [];
  private $masteries = [];
  private $combat = [
    "p_dmg" => 0,
    "m_dmg" => 0,
    "p_def" => 0,
    "m_def" => 0,
    "speed" => 0
  ];
  
  
}
