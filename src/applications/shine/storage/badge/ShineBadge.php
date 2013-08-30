<?php

class ShineBadge extends ShineDAO {

  protected $title;
  protected $userPHID;
  protected $dateEarned;
  protected $tally;

  public function getConfiguration()
  {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }
}
