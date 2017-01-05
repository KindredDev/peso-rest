<?php

class Cron {

  public $schedule;
  public $dates;

  function Cron($schedule='* * * * *', $start=false, $end=false) {
    if (strpos($schedule, ':') !== false) {
      $parts = explode(':',$schedule);
      $this->schedule = $parts[0];
      $this->dates = $this->fillStaticDates($start, $end, $parts[1]);
    } else {
      $this->schedule = $schedule;
    }
  }

  private function fillStaticDates($start, $end, $recurrence) {
    $dates = array();
    $start = strtotime($start);
    do {
      $crontab = explode(' ', $this->schedule);
      if ($recurrence[1] == 'M') {
        $month_crontab = $this->parseNonStandardCharacters($crontab, $start);
        $this->dates[] = date("Y-m-{$month_crontab[2]}", $start);
      } else {
        $dates[] = date("Y-m-d", strtotime( date("Y",$start)."W".date("W",$start).$crontab[4] ));
      }
      $start = strtotime("+{$recurrence[0]} ".($recurrence[1] == 'M' ? 'months' : 'weeks'), $start);
    } while( date('Ym',$start) < date('Ym',strtotime($end)) );
    return $dates;
  }

  private function convertNumeralToOrdinal($numeral) {
    $array = array("first", "second", "third", "fourth", "fifth");
    return $array[$numeral-1];
  }

  private function convertOrdinalToDayname($ordinal) {
    $array = array("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday");
    return $array[$ordinal-1];
  }

  private function parseNonStandardCharacters($crontab, $time) {
    // day of month
    switch (true) {
      case $crontab[2] == 'L':
        $crontab[2] = date("t", $time);
        break;
      case stristr($crontab[2],'W'):
        $int = explode('W', $crontab[2]);
        $test = strtotime( date("Y-m-{$int[0]}", $time) );
        $day = date('N', $test);
        if ($day == 6) {
          $crontab[2] = date("j", strtotime( "-1 day", date("Y-m-{$int[0]}", $time) ));
        } else if ($day == 7) {
          $crontab[2] = date("j", strtotime( "+1 day", date("Y-m-{$int[0]}", $time) ));
        } else {
          $crontab[2] = $int[0];
        }
        break;
    }
    // day of week
    switch (true) {
      case stristr($crontab[4],'L'):
        $int = explode('L', $crontab[4]);
        $crontab[2] = date("j", strtotime( date("\L\a\s\t\ l \o\f F Y", $time) ));
        $crontab[4] = "*";
        break;
      case stristr($crontab[4],'#'):
        $int = explode('#', $crontab[4]);
        $crontab[2] = date("j", strtotime( $this->convertNumeralToOrdinal($int[0]).date(" l \o\f F Y", $time) ));
        $crontab[4] = "*";
        break;
    }
    return $crontab;
  }

  function isDue($time=false) {
      $time = is_string($time) ? strtotime($time) : time();

      if (count($this->dates) > 0) {
        return in_array(date("Y-m-d",$time), $this->dates);
      }

      $crontab = $this->parseNonStandardCharacters(explode(' ', $this->schedule), $time);

      $time = explode(' ', date('i G j n w Y', $time));
      foreach ($crontab as $k => &$v) {
          $v = explode(',', $v);
          $regexps = array(
              '/^\*$/', # every
              '/^\d+$/', # digit
              '/^(\d+)\-(\d+)$/', # range
              '/^\*\/(\d+)$/' # every digit
          );
          $content = array(
              "true", # every
              "{$time[$k]} === $0", # digit
              "($1 <= {$time[$k]} && {$time[$k]} <= $2)", # range
              "{$time[$k]} % $1 === 0" # every digit
          );
          foreach ($v as &$v1)
              $v1 = preg_replace($regexps, $content, $v1);
          $v = '('.implode(' || ', $v).')';
      }
      $crontab = implode(' && ', $crontab);
      return eval("return {$crontab};");
  }

}

?>
