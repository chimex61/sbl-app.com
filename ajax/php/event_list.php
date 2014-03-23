<?php
/* Move back to the server root dir so that
 * so that includes and such work correctly
 */
$root_dir = $_SERVER['DOCUMENT_ROOT'];
chdir($root_dir);

require_once 'core/init.php';



function compare_event_date($a, $b) {
  $a_date = strtotime($a);
  $b_date = strtotime($b);

  if ($a_date == $b_date)
    return 0;

  return ($a_date < $b_date) ? -1 : 1;
}

$cat_id = $_GET['cat'];
$dates = array();

// Store dates for categorization
foreach ($EVENTS as $event) {
  if ( !in_array($event->date, $dates)
      &&
      (
        $event->category->id == $cat_id
        || $cat_id == 1
      )
     )
    $dates[] = $event->date;
}

usort($dates, 'compare_event_date');

foreach ($dates as $date) {
?>
<div class="date_title">
  <span>
  <?php echo
    $SYSTEM->date2words($date)
    ;
  ?>
  </span>
</div>

<?php
  foreach ($EVENTS as $event) {
    $cat_name = $event->category->name;

    if ($event->category->id == $cat_id
        || $cat_id == 1)
    {
      if ($event->date == $date) {
      ?>

      <div
        id="<?php echo $event->id;?>"
        class="list_item"
        onclick="load_event(this.id, <?php echo $cat_id;?>)"
      >
        <span id="cat_name">
          <?php echo
            $cat_name[0] . '<br>' .
            $cat_name[1] . '<br>' .
            $cat_name[2]
            ;
          ?>
        </span>
        <span>
          <?php echo
            $event->away_team->name
            . ' vs ' .
            $event->home_team->name
            ;
          ?>
        </span>
        <span>
          <?php echo
            $event->time
            ;
          ?>
        </span>
        <span>
          <?php echo
            $event->away_score
            . ' - ' .
            $event->home_score
            ;
          ?>
        </span>
        <span>
          <?php echo
            $event->description
            ;
          ?>
        </span>
      </div>

      <?php
      }
    }
  }
}
?>