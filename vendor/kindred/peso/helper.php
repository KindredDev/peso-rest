<?php

class Helper {

  static function getDateRangeArray($start, $end) {
    $range = array();

    $start = new DateTime($start);
    $end = new DateTime($end);
    $end = $end->modify( '+1 day' );

    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($start, $interval, $end);

    foreach ( $period as $dt )
      $range[] = $dt->format( "Y-m-d" );

    return $range;
  }

  static function isJson($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
  }

  static function dateInRange($start_date, $end_date, $date_from_user) {
    $start_ts = strtotime($start_date);
    $user_ts = strtotime($date_from_user);

    if (!$end_date)
      return ($user_ts >= $start_ts);

    $end_ts = strtotime($end_date);
    return (($user_ts >= $start_ts) && ($user_ts <= $end_ts));
  }

  static function getDaysOfWeekBetweenDates($startDate, $endDate, $weekdayNumber) {
    $startDate = strtotime($startDate);
    $endDate = strtotime($endDate);

    $dateArr = array();

    do {
        if(date("w", $startDate) != $weekdayNumber)
        {
            $startDate += (24 * 3600); // add 1 day
        }
    } while(date("w", $startDate) != $weekdayNumber);


    while($startDate <= $endDate) {
        $dateArr[] = date('Y-m-d', $startDate);
        $startDate += (7 * 24 * 3600); // add 7 days
    }

    return($dateArr);
  }

  static function getUID() {
      return md5(time());
  }

  static function sendMessage($template, $recipient, $data) {
      // send email using twig template
      return true;
  }

  // Authentication

  static function getClientIP() {
      if ( isset($_SERVER['HTTP_CLIENT_IP']) && ! empty($_SERVER['HTTP_CLIENT_IP'])) {
          $ip = $_SERVER['HTTP_CLIENT_IP'];
      } elseif ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) && ! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
          $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
      } else {
          $ip = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
      }

      $ip = filter_var($ip, FILTER_VALIDATE_IP);
      $ip = ($ip === false) ? '0.0.0.0' : $ip;

      return $ip;
  }

  static function validateSession($request, $mysql) {
      $body = $request->getParsedBody();

      if (!array_key_exists('session', $body) || !array_key_exists('member', $body))
        die(json_encode(array( 'status' => 'failure', 'description' => "Session parameters missing")));

      $stm = $mysql->prepare('SELECT * FROM members WHERE session_key = :session_key');
      $stm->execute(array(
          'session_key' => (string) $body['session']
      ));
      $user = $stm->fetch(PDO::FETCH_ASSOC);

      if (!$user || $user['id'] != $body['member'] || $user['session_ip'] != Helper::getClientIP())
        die(json_encode(array( 'status' => 'failure', 'description' => "Invalid session")));
  }

  static function checkAuthenticated($app, $member) {
      global $mysql;
      $session = $app->request->headers->get('Session-Key');

      if (!$session)
          finalize( array( "status" => "error", "description" => "Session key missing." ) );
      else {
          $stm = $mysql->prepare('SELECT * FROM members WHERE session_key = :session_key');
          $stm->execute(array(
              'session_key' => (string) $session
          ));
          $user = $stm->fetch(PDO::FETCH_ASSOC);

          if (!$user || $user['id'] != $member || $user['session_ip'] != getClientIP())
              finalize( array( "status" => "error", "description" => "Session key invalid." ) );
      }
  }

}
