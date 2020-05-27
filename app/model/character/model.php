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
  
  private $hp_max = 0;
  private $hp = 0;
  private $mp_max = 0;
  private $mp = 0;
  
  private $stats = [];
  private $masteries = [];
  private $combat = [
    "p_dmg" => 0,
    "m_dmg" => 0,
    "p_def" => 0,
    "m_def" => 0,
    "speed" => 0
  ];
  
  public function __construct(array $data) {
    
    
    return $this;
  }
  
  /* 
  alive or dead 
  */
  
  public function isAlive() {
    if ($this->hp > 0 ) {
      return true;
    }  
    return false;
  }
  public function isDead() {
    if ($this->hp > 0 ) {
      return false;
    }
    return true;
  }
  
  /* 
  getters 
  */
  
  public function id() {
    return $this->id;
  }
  public function race() {
    return $this->race;
  }
  public function sex() {
    return $this->sex;
  }
  public function old() {
    return $this->old;
  }
  public function lvl() {
    return $this->lvl;
  }
  public function exp() {
    return $this->exp;
  }
  public function name() {
    return $this->name;
  }
  
  public function hp() {
    return $this->hp;
  }
  public function hp_max() {
    return $this->hp_max;
  }
  public function mp() {
    return $this->mp;
  }
  public function mp_max() {
    return $this->mp_max;
  }
  
  public function stats() {
    return $this->stats;
  }
  public function masteries() {
    return $this->masteries;
  }
  public function combat() {
    return $this->combat;
  }
  
  /*
  hp process
  */
  public function add_hp(int $hp ) {
    $this->hp += $hp;
    
    if ( $this->hp > $this->hp_max) {
      // max hp limit
      $this->hp = $this->hp_max;
    }
    if ( $this->hp < 0) {
      // min hp limit
      $this->hp = 0;
    }
  }
}
