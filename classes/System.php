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

  public function search($q) {
    global $DB;
    
    $q = "%$q%";

    $query = $DB->prepare ("
      SELECT event.*
      FROM event
      INNER JOIN category AS c ON (event.cat_id = c.id)
      INNER JOIN team AS t1 ON (event.home_id = t1.id)
      INNER JOIN team AS t2 ON (event.away_id = t2.id)
      WHERE t1.name LIKE ?
      OR t2.name LIKE ?
      OR t1.short_name LIKE ?
      OR t2.short_name LIKE ?
      OR c.name LIKE ?
    ");
   
    $query->bind_param('sssss', $q, $q, $q, $q, $q);
    $query->execute();
    $results = $this->resolve_data($query);

    foreach ($results as $event) {
      $events[] = new Event (
        $event->id,
        $event->cat_id, 
        $event->event_time,
        $event->outcome,
        $event->home_id,
        $event->home_score,
        $event->away_id,
        $event->away_score,
        $event->location,
        $event->description
      );
    }
    return ( isset($events) ) ? $events : $events = array();
  }

  function get_wager () {
    global $DB;

    $query = $DB->prepare ("
      SELECT *
      FROM wager
      ORDER BY timestamp DESC
      LIMIT 1 
    ");
    $query->execute();

    $results = $this->resolve_data($query);

    foreach ($results as $wager) {
      $wagers[] = new Wager (
        $wager->id,
        $wager->timestamp,
        $wager->user_id,
        $wager->amount,
        $wager->opponent_id,
        $wager->event_id,
        $wager->wager_outcome,
        $wager->status,
        $wager->proposal,
        $wager->seen,
        $wager->counter_offer_bool
      );
    }
    return ( isset($wagers) ) ? $wagers : $wagers = array();
  }

  public function set_event_outcome () {
    global $DB;
    global $EVENTS;

  	foreach ($EVENTS as $event) {
  		if ($event->home_score > $event->away_score) {
  			$query = $DB->prepare ("
      UPDATE event
      SET outcome = 1
      WHERE id = ?
    ");
   
    $query->bind_param('d',$event->id);
    $query->execute();
    
  	}
  	elseif ($event->home_score < $event->away_score) {
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
  global $WAGERS;

  foreach ($WAGERS as $wager) {
    if ($wager->status !== 0 && $wager->status !== NULL && $wager->paid_out === 0) {

      if ($wager->proposal == $wager->event->home_team->id && $wager->event->outcome == 1) {

      $query = $DB->prepare ("
        UPDATE wager
        SET wager_outcome = 1
        WHERE id = ?
      ");
     
      $query->bind_param('d',$wager->id);
      $query->execute();
      }

      elseif ($wager->proposal == $wager->event->away_team->id && $wager->event->outcome == 0) {

        $query = $DB->prepare ("
        UPDATE wager
        SET wager_outcome = 1
        WHERE id = ?
      ");
     
      $query->bind_param('d',$wager->id);
      $query->execute();
      }
      elseif($wager->proposal == $wager->event->home_team->id && $wager->event->outcome == 0){

        $query = $DB->prepare ("
          UPDATE wager
          SET wager_outcome = 0
          WHERE id = ?
        ");
       
        $query->bind_param('d',$wager->id);
        $query->execute();
      }
      elseif($wager->proposal == $wager->event->away_team->id && $wager->event->outcome == 1){

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
}

public function check_time($event_time){
  //On front end, check time, return true/false
  //If time off throw error

  //We should pass the time in as a string if possible. 

  if( strtotime('now') < strtotime($event_time) ){
    return true;
  }
  else
    return false;
}

public function check_yacs(){
   //Add credits to the user's account if 3 weeks have passed 

   global $DB;
   global $USERS; 
     

  foreach ($USERS as $user) {

        $uid = $user->get_uid();
        $uyb = $user->yac->balance;
        $schfifty = 50;
    
        $merica = $uyb + $schfifty;

    if( strtotime($user->yac->updated) >= strtotime('-3 week') ) {
    
        $query = $DB->prepare ("
        UPDATE yac
        SET balance = ?
        WHERE id = ?
      ");

      $query->bind_param('dd', 
        $merica, $uid
      );
    }
    else 
      ;
  }
  
}

public function check_and_update_user_balances(){
   global $DB;
   global $USERS;

  //use global user variable, loop through all users and update 
  //balances based on wagers that have status == 1 and have an outcome

  foreach ($USERS as $user) {
    $user->update_user();

    foreach ($user->get_accepted_wagers() as $wagers) {

    $user->update_user();
         
    $uid = $user->get_uid();
    $uyb = $user->yac->balance;
    $uyar = $user->yac->at_risk;
    $uyw = $user->yac->winnings;
    $uyl = $user->yac->losings;
    $wamt = $wagers->amount; 

    $add_win = $uyb + (2 * $wamt);
    $add_at_risk = $uyar - $wamt;
    $add_yac_winnings = $uyw + $wamt;

    $add_loss = $uyb - $wamt;
    $add_yac_losses = $uyl + $wamt;

    $paid_out = $wagers->paid_out + 1;
    
    

      if($wagers->outcome === 1 && $uid == $wagers->user_id && $wagers->paid_out === 0){
 
        echo 'user: ' . $user->get_uid() . ' won wager #' . $wagers->id .
              ' and won: ' . $wagers->amount . '<br>'
              . '     current balance: ' . $uyb . ' new balance: ' . $add_win . '<br><br>';
      

      $query = $DB->prepare ("
        UPDATE yac, wager
        SET yac.balance = ?, yac.at_risk = ?, yac.winnings = ?, wager.paid_out = ?
        WHERE yac.user_id = ?
        AND wager.id = ?
      ");

      $query->bind_param('dddddd',
        $add_win,
        $add_at_risk ,
        $add_yac_winnings,
        $paid_out,
        $uid,
        $wagers->id
      );
      $query->execute();
     }
   
     elseif($wagers->outcome === 0 && $uid == $wagers->user_id && $wagers->paid_out === 0){

      //The current user lost!
        echo 'user: ' . $user->get_uid() . ' lost wager #' . $wagers->id .
              ' and lost: ' . $wagers->amount . '<br>'
              . '     current balance: ' . $uyb . ' new balance: ' . $uyb . '<br><br>';

      $query = $DB->prepare ("
        UPDATE yac, wager
        SET yac.balance = ?, yac.at_risk = ?, yac.losings = ?, wager.paid_out = ?
        WHERE yac.user_id = ?
        AND wager.id = ?
      ");

      $query->bind_param('dddddd',
        $uyb,
        $add_at_risk,
        $add_yac_losses,
        $paid_out,
        $uid,
        $wagers->id
      );
      $query->execute();
     }
     
     
     elseif($wagers->outcome === 1 && $uid == $wagers->opponent_id && $wagers->paid_out === 0){

        //The current user lost (because the opponent won)!
        echo 'user: ' . $user->get_uid() . ' lost wager #' . $wagers->id .
              ' and lost: ' . $wagers->amount . '<br>'
              . '     current balance: ' . $uyb . ' new balance: ' . $uyb . '<br><br>';

      $query = $DB->prepare ("
        UPDATE yac, wager
        SET yac.balance = ?, yac.at_risk = ?, yac.losings = ?, wager.paid_out = ?
        WHERE yac.user_id = ?
        AND wager.id = ?
      ");

      $query->bind_param('dddddd',
        $uyb,
        $add_at_risk,
        $add_yac_losses,
        $paid_out,
        $uid,
        $wagers->id
      );
      $query->execute();
     }

     elseif($wagers->outcome === 0 && $uid == $wagers->opponent_id && $wagers->paid_out === 0){

        //The current user won (because he/she is the opponent)!
        echo 'user: ' . $user->get_uid() . ' won wager #' . $wagers->id .
              ' and won: ' . $wagers->amount . '<br>'
              . '     current balance: ' . $uyb . ' new balance: ' . $add_win . '<br><br>';

      $query = $DB->prepare ("
        UPDATE yac, wager
        SET yac.balance = ?, yac.at_risk = ?, yac.winnings = ?, wager.paid_out = ?
        WHERE yac.user_id = ?
        AND wager.id = ?
      ");

      $query->bind_param('dddddd',
        $add_win,
        $add_at_risk,
        $add_yac_winnings,
        $paid_out,
        $uid,
        $wagers->id
      );
      $query->execute();
     }
    else echo 'no new updates<br>';
   }
  }
 }
};
?>
