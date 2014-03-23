<?php
require_once 'classes/Database.php';

class System extends Database {
  public function __construct() {
    parent::__construct();
  }

  public function time2str($ts) {
    if(!ctype_digit($ts))
        $ts = strtotime($ts);

    // chnage to EST from UTC
    $ts -= (4 * 3600);

    $diff = time() - $ts;
    if($diff == 0)
        return 'now';
    elseif($diff > 0)
    {
        $day_diff = floor($diff / 86400);
        if($day_diff == 0)
        {
            if($diff < 30) return $diff .  ' seconds ago';
            if($diff >= 30 && $diff < 60) return 'about 30 seconds ago';
            if($diff < 120) return 'about a minute ago';
            if($diff < 3600) return floor($diff / 60) . ' minutes ago';
            if($diff < 7200) return 'about an hour ago';
            if($diff < 86400) return 'about ' . floor($diff / 3600) . ' hours ago';
        }
        if($day_diff == 1) return 'Yesterday';
        if($day_diff < 7) return $day_diff . ' days ago';
        if($day_diff < 31) return ceil($day_diff / 7) . ' weeks ago';
        if($day_diff < 60) return 'last month';
        return date('F Y', $ts);
    }
  }

  public function date2words ($ts) {
    if(!ctype_digit($ts))
        $ts = strtotime($ts);

    return date('F j, Y', $ts);
  }

  public function get_userid($email){
   global $DB;

    $query = $DB->prepare ("
      SELECT user_id
      FROM user
      WHERE
        (
          email = ?
          OR u_name = ?
        )
    ");
   
    $query->bind_param('ss', $email, $email);
    $query->execute();
    $query->bind_result($id);
    $query->fetch();

    return $id;
  }

  public function get_uname($id) {
   global $DB;

    $query = $DB->prepare ("
      SELECT u_name
      FROM user
      WHERE user_id = ?
    ");
   
    $query->bind_param('d', $id);
    $query->execute();
    $query->bind_result($u_name);
    $query->fetch();
    $query->free_result();

    if ( $u_name == '' ) {
      $u_name = $this->get_email($id);
    }

    return $u_name;
  }

  public function get_email($id) {
   global $DB;

    $query = $DB->prepare ("
      SELECT email
      FROM user
      WHERE user_id = ?
    ");
   
    $query->bind_param('s', $id);
    $query->execute();
    $query->bind_result($email);
    $query->fetch();

    return $email;
  }

  public function set_event_outcome(){
   global $DB;

  	foreach ($this->events as $event) {
  		if($event->home_score > $event_away_score){
  			$query = $DB->prepare ("
      UPDATE event
      SET outcome = 1
      WHERE id = ?
    ");
   
    $query->bind_param('d',$event->id);
    $query->execute();
    
  	}
  	else{
      $query = $DB->prepare ("
      UPDATE event
      SET outcome = 0
      WHERE id = ?
    ");

    $query->bind_param('d',$event->id); 
    $query->execute();
    
   	}
   }
 }

  public function set_wager_outcome(){
   global $DB;

    foreach ($this->wagers as $wager) {
      if ($wager->proposal == $wager->event->home_id && $wager->event->outcome == 1) {

      $query = $DB->prepare ("
      UPDATE wager
      SET wager_outcome = 1
      WHERE id = ?
    ");
   
    $query->bind_param('d',$wager->id);
    $query->execute();
    }

    elseif ($wager->proposal == $wager->event->away_id && $wager->event->outcome == 0) {

      $query = $DB->prepare ("
      UPDATE wager
      SET wager_outcome = 1
      WHERE id = ?
    ");
   
    $query->bind_param('d',$wager->id);
    $query->execute();
  }
  elseif($wager->proposal == $wager->event->home_id && $wager->event->outcome == 0){

    $query = $DB->prepare ("
      UPDATE wager
      SET wager_outcome = 0
      WHERE id = ?
    ");
   
    $query->bind_param('d',$wager->id);
    $query->execute();
  }
  elseif($wager->proposal == $wager->event->away_id && $wager->event->outcome == 1){

    $query = $DB->prepare ("
      UPDATE wager
      SET wager_outcome = 0
      WHERE id = ?
    ");
   
    $query->bind_param('d',$wager->id);
    $query->execute();
  }
  }
}

public function check_time($event_time){
  //On front end, check time, return true/false
  //If time off throw error

  //We should pass the time in as a string if possible. 

  //If the time being passed in, which should be the current time, is greater 
  //(meaning time to bet has passed) greater than the current time then the 
  //wager cannot be placed

  if( strtotime('now') < strtotime($event_time) ){
    return true;
  }
  else
    return false;
}

public function check_and_update_user_balances(){
   global $DB;

  //use global user variable, loop through all users and update 
  //balances based on wagers that have status == 1 and have an outcome

  foreach ($this->user_id as $user) {
    
    if($user->user_id == $yacs->$user_id && $wagers->status == 1
        && $wagers->outcome == 1 && $user->user_id == $wagers->user_id){

      $query = $DB->prepare ("
      UPDATE yac
      SET balance = ?, at_risk = ?
      WHERE id = ?
    ");

    $query->bind_param('ddd', $yacs->balance + $wagers->amount, $yacs->at_risk - $wagers->amount, $user->user_id);
    $query->execute();
   }
   
   elseif($user->user_id == $yacs->$user_id && $wagers->status == 1
          && $wagers->outcome == 0 && $user->user_id == $wagers->user_id){

      $query = $DB->prepare ("
      UPDATE yac
      SET balance = ?, at_risk = ?
      WHERE id = ?
    ");

    $query->bind_param('ddd', $yacs->balance - $wagers->amount, $yacs->at_risk - $wagers->amount, $user->user_id);
    $query->execute();
   }

   elseif($user->user_id == $yacs->$user_id && $wagers->status == 1
          && $wagers->outcome == 1 && $user->user_id == $wagers->opponent_id){

      $query = $DB->prepare ("
      UPDATE yac
      SET balance = ?, at_risk = ?
      WHERE id = ?
    ");

    $query->bind_param('ddd', $yacs->balance - $wagers->amount, $yacs->at_risk - $wagers->amount, $user->user_id);
    $query->execute();
   }

   elseif($user->user_id == $yacs->$user_id && $wagers->status == 1
          && $wagers->outcome == 0 && $user->user_id == $wagers->opponent_id){

      $query = $DB->prepare ("
      UPDATE yac
      SET balance = ?, at_risk = ?
      WHERE id = ?
    ");

    $query->bind_param('ddd', $yacs->balance + $wagers->amount, $yacs->at_risk - $wagers->amount, $user->user_id);
    $query->execute();
   }

 }
}

};
?>
