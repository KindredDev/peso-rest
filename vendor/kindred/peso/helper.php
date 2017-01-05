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

  static function buildAccounts($accounts, $mysql) {
      $result = array();
      foreach($accounts as $account) {
          if ($curr = Helper::buildAccount($account, $mysql))
              $result[] = $curr;
      }
      return $result;
  }

  static function buildAccount($account, $mysql) {
      $stm = $mysql->prepare('SELECT * FROM accounts WHERE id = :id');
      $stm->execute(array(
          ':id' => $account
      ));
      $row = $stm->fetch();
      if ($row) {
          $current_account = array(
              'id' => $row['id'],
              'name' => $row['name'],
              'transactions' => array(
                  'reconciled' => array(),
                  'scheduled' => array()
              )
          );

          $stm2 = $mysql->prepare('SELECT * FROM schedules WHERE account = :id');
          $stm2->execute(array(
              ':id' => $account
          ));
          $schedules = $stm2->fetchAll();
          if ($schedules && is_array($schedules) && count($schedules) > 0) {
              foreach($schedules as $schedule) {
                  $current_schedule = array(
                      'id' => $row['id'],
                      'title' => $schedule['name'],
                      'amount' => $schedule['amount'],
                      'range' => array(
                          'start' => $schedule['range_start']
                      )
                  );

                  if ($schedule['range_end'])
                      $current_schedule['range']['end'] = $schedule['range_end'];

                  if ($schedule['expressions'])
                      $current_schedule['expressions'] = json_decode($schedule['expressions'], true);

                  if ($schedule['dates'])
                      $current_schedule['dates'] = json_decode($schedule['dates'], true);


                  $current_account['transactions']['scheduled'][] = $current_schedule;
              }
          }

          $stm3 = $mysql->prepare('SELECT * FROM transactions WHERE account = :id');
          $stm3->execute(array(
              ':id' => $account
          ));
          $transactions = $stm3->fetchAll();
          if ($transactions && is_array($transactions) && count($transactions) > 0) {
              foreach($transactions as $transaction) {
                  $current_account['transactions']['reconciled'][] = array(
                      'id' => $row['id'],
                      'title' => $transaction['name'],
                      'amount' => $transaction['amount'],
                      'date' => $transaction['dt']
                  );
              }
          }

          return $current_account;
      }
      else {
          return false;
      }
  }

}
