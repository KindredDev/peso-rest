<?php
// error_reporting(E_ALL); ini_set('display_errors', 1);

include_once('../vendor/kindred/peso/peso.php');
include_once('../vendor/kindred/peso/connections.php');

// Connections

$mysql = ConnectionFactory::getFactory()->getMySqlConnection();

// Routes

$app->post('/calendar', function ($request, $response, $args) use ($mysql) {
    $this->logger->info("POST request to '/calendar'");

    Helper::validateSession($request, $mysql);

    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
      'member' => $body['member'],
      'accounts' => $body['accounts'] ?: null,
      'range_start' => $body['range_start'] ?: null,
      'range_end' => $body['range_end'] ?: null
    );

    $accounts = array();
    foreach($body['accounts'] as $account) {
        $stm = $mysql->prepare('SELECT * FROM accounts WHERE id = :id');
        $stm->execute(array(
            ':id' => $account
        ));
        $row = $stm->fetch();
        if ($row) {
            $current_account = array(
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
                        'title' => $transaction['name'],
                        'amount' => $transaction['amount'],
                        'date' => $transaction['dt']
                    );
                }
            }

            $accounts[] = $current_account;
        }
    }

    $json = json_encode(array(
        'accounts' => $accounts
    ));
    $peso = new Peso($json);

    $data = false;
    if ($peso->data && is_array($peso->data))
        $data = $peso->build();

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($data));
});

// create schedule
$app->post('/schedule/create', function ($request, $response, $args) use ($mysql) {
    Helper::validateSession($request, $mysql);

    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
        'id' => Helper::getUID(),
        'member' => $body['member'],
        'name' => $body['name'],
        'account' => $body['account'],
        'amount' => $body['amount'],
        'range_start' => $body['range_start'],
        'range_end' => $body['range_end'] ?: null,
        'expressions' => $body['expressions'] ?: null,
        'dates' => $body['dates'] ?: null
    );

    $stm = $mysql->prepare('INSERT INTO schedules (id, name, account, amount, range_start, range_end, expressions, dates) VALUES (:id,:name,:account,:amount,:range_start,:range_end,:expressions,:dates)');
    $stm->execute(array(
        ':id' => $data['id'],
        ':name' => $data['name'],
        ':account' => $data['account'],
        ':amount' => $data['amount'],
        ':range_start' => $data['range_start'],
        ':range_end' => $data['range_end'],
        ':expressions' => json_encode($data['expressions']),
        ':dates' => json_encode($data['dates'])
    ));

    if ($stm->rowCount()) {
        $payload = array( 'status' => 'success', 'description' => "Schedule created successfully!", 'payload' => array('schedule' => $data));
    }
    else {
        $payload = array( 'status' => 'failure', 'description' => "Error creating schedule. Please try again, or contact <a href='mailto:#'>support</a>.");
    }

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($payload));
});

// update schedule
$app->post('/schedule/update', function ($request, $response, $args) use ($mysql) {
    Helper::validateSession($request, $mysql);

    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
        'id' => $body['schedule'],
        'member' => $body['member'],
        'name' => $body['name'],
        'account' => $body['account'],
        'amount' => $body['amount'],
        'range_start' => $body['range_start'],
        'range_end' => $body['range_end'] ?: null,
        'expressions' => $body['expressions'] ?: null,
        'dates' => $body['dates'] ?: null
    );

    $stm = $mysql->prepare('UPDATE schedules SET name = :name, account = :account, amount = :amount, range_start = :range_start, range_end = :range_end, expressions = :expressions, dates = :dates WHERE id = :id');
    $stm->execute(array(
        ':name' => $data['name'],
        ':account' => $data['account'],
        ':amount' => $data['amount'],
        ':range_start' => $data['range_start'],
        ':range_end' => $data['range_end'],
        ':expressions' => json_encode($data['expressions']),
        ':dates' => json_encode($data['dates']),
        ':id' => $data['id']
    ));

    if ($stm->rowCount()) {
        $payload = array( 'status' => 'success', 'description' => "Schedule updated successfully!", 'payload' => array('schedule' => $data));
    }
    else {
        $payload = array( 'status' => 'failure', 'description' => "Error updating schedule. Please try again, or contact <a href='mailto:#'>support</a>.");
    }

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($payload));
});

// delete schedule
$app->post('/schedule/delete', function ($request, $response, $args) use ($mysql) {
    Helper::validateSession($request, $mysql);

    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
        'id' => $body['schedule']
    );

    $stm = $mysql->prepare('DELETE FROM schedules WHERE id = :id');
    $stm->execute(array(
        ':id' => $data['id']
    ));

    if ($stm->rowCount()) {
        $payload = array( 'status' => 'success', 'description' => "Schedule deleted successfully!");
    }
    else {
        $payload = array( 'status' => 'failure', 'description' => "Error deleting schedule. Please try again, or contact <a href='mailto:#'>support</a>.");
    }

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($payload));
});

// create transaction
$app->post('/transaction/create', function ($request, $response, $args) use ($mysql) {
    Helper::validateSession($request, $mysql);

    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
        'id' => Helper::getUID(),
        'member' => $body['member'],
        'account' => $body['account'],
        'name' => $body['name'],
        'amount' => $body['amount'],
        'date' => $body['date']
    );

    $stm = $mysql->prepare('INSERT INTO transactions (id, account, name, amount, dt) VALUES (:id,:account,:name,:amount,:dt)');
    $stm->execute(array(
        ':id' => $data['id'],
        ':account' => $data['account'],
        ':name' => $data['name'],
        ':amount' => $data['amount'],
        ':dt' => $data['date']
    ));

    if ($stm->rowCount()) {
        $payload = array( 'status' => 'success', 'description' => "Transaction created successfully!", 'payload' => array('transaction' => $data));
    }
    else {
        $payload = array( 'status' => 'failure', 'description' => "Error creating transaction. Please try again, or contact <a href='mailto:#'>support</a>.");
    }

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($payload));
});

// update transaction
$app->post('/transaction/update', function ($request, $response, $args) use ($mysql) {
    Helper::validateSession($request, $mysql);

    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
        'id' => $body['transaction'],
        'member' => $body['member'],
        'account' => $body['account'],
        'name' => $body['name'],
        'amount' => $body['amount'],
        'date' => $body['date']
    );

    $stm = $mysql->prepare('UPDATE transactions SET account = :account, name = :name, amount = :amount, dt = :dt WHERE id = :id');
    $stm->execute(array(
        ':account' => $data['account'],
        ':name' => $data['name'],
        ':amount' => $data['amount'],
        ':dt' => $data['date'],
        ':id' => $data['id']
    ));

    if ($stm->rowCount()) {
        $payload = array( 'status' => 'success', 'description' => "Transaction updated successfully!", 'payload' => array('transaction' => $data));
    }
    else {
        $payload = array( 'status' => 'failure', 'description' => "Error updating transaction. Please try again, or contact <a href='mailto:#'>support</a>.");
    }

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($payload));
});

// delete transaction
$app->post('/transaction/delete', function ($request, $response, $args) use ($mysql) {
    Helper::validateSession($request, $mysql);

    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
        'id' => $body['transaction']
    );

    $stm = $mysql->prepare('DELETE FROM transactions WHERE id = :id');
    $stm->execute(array(
        ':id' => $data['id']
    ));

    if ($stm->rowCount()) {
        $payload = array( 'status' => 'success', 'description' => "Transaction deleted successfully!");
    }
    else {
        $payload = array( 'status' => 'failure', 'description' => "Error deleting transaction. Please try again, or contact <a href='mailto:#'>support</a>.");
    }

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($payload));
});

// create account
$app->post('/account/create', function ($request, $response, $args) use ($mysql) {
    Helper::validateSession($request, $mysql);

    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
        'id' => Helper::getUID(),
        'member' => $body['member'],
        'name' => $body['name'],
        'type' => $body['type'],
        'api_key' => $body['api_key'] ?: null,
        'created' => date('Y-m-d H:i:s')
    );

    if($data['member'] == NULL || $data['name'] == NULL){
        $missing = array();

        if($data['member'] == NULL)
            $missing[] = "Member";
        if($data['name'] == NULL)
            $missing[] = "Account Name";
        if($data['type'] == NULL)
            $missing[] = "Account Type";

        $payload = array( 'status' => 'failure', 'description' => "Please complete the missing fields:\n" . implode(", ", $missing));
    }
    else {
        $stm = $mysql->prepare('INSERT INTO accounts (id, member, name, type, created) VALUES (:id,:member,:name,:type,:created)');
        $stm->execute(array(
            ':id' => $data['id'],
            ':member' => $data['member'],
            ':name' => $data['name'],
            ':type' => $data['type'],
            ':created' => $data['created']
        ));

        $stm2 = $mysql->prepare('SELECT id FROM accounts WHERE id = :id');
        $stm2->execute(array(
            ':id' => $data['id']
        ));
        $lid = $stm2->fetchColumn();
        if ($lid == $data['id']) {
            $payload = array( 'status' => 'success', 'description' => "Account created successfully!", 'payload' => array('account' => $data));
        }
        else {
            $payload = array( 'status' => 'failure', 'description' => "Error creating account. Please try again, or contact <a href='mailto:#'>support</a>.");
        }
    }

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($payload));
});

// update account
$app->post('/account/update', function ($request, $response, $args) use ($mysql) {
    Helper::validateSession($request, $mysql);

    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
        'id' => $body['account'],
        'member' => $body['member'],
        'name' => $body['name'],
        'api_key' => $body['api_key'] ?: null,
        'type' => $body['type']
    );

    $stm = $mysql->prepare('UPDATE accounts SET name = :name, type = :type WHERE id = :id');
    $stm->execute(array(
        ':name' => $data['name'],
        ':type' => $data['type'],
        ':id' => $data['id']
    ));

    if ($stm->rowCount()) {
        $payload = array( 'status' => 'success', 'description' => "Account updated successfully!", 'payload' => array('account' => $data));
    }
    else {
        $payload = array( 'status' => 'failure', 'description' => "Error updating account. Please try again, or contact <a href='mailto:#'>support</a>.");
    }

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($payload));
});

// delete account
$app->post('/account/delete', function ($request, $response, $args) use ($mysql) {
    Helper::validateSession($request, $mysql);

    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
        'id' => $body['account']
    );

    $stm = $mysql->prepare('DELETE FROM accounts WHERE id = :id');
    $stm->execute(array(
        ':id' => $data['id']
    ));

    if ($stm->rowCount()) {
        $payload = array( 'status' => 'success', 'description' => "Account deleted successfully!");
    }
    else {
        $payload = array( 'status' => 'failure', 'description' => "Error deleting account. Please try again, or contact <a href='mailto:#'>support</a>.");
    }

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($payload));
});

// login
$app->post('/members/login', function ($request, $response, $args) use ($mysql) {
    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
        'email' => $body['email'],
        'password' => $body['password']
    );

    $stm = $mysql->prepare('SELECT * FROM members WHERE email = :email');
    $stm->execute(array(
        ':email' => $data['email']
    ));
    $user = $stm->fetch(PDO::FETCH_ASSOC);

    if($user && password_verify($data['password'], $user['password'])) {
        $session_key = Helper::getUID();

        $stm = $mysql->prepare('UPDATE members SET session_key = :session_key, session_ip = :session_ip, session_start = :session_start WHERE email = :email');
        $stm->execute(array(
            ':session_key' => $session_key,
            ':session_ip' => Helper::getClientIP(),
            ':session_start' => date('Y-m-d H:i:s'),
            ':email' => $data['email']
        ));

        $payload = array( 'status' => 'success', 'description' => "Logged in successfully!", 'payload' => array(
            'session' => array(
                'user'  => array(
                    'id'    => $user['id'],
                    'email' => $user['email'],
                    'name' => array(
                        'first' => $user['name_first'],
                        'last' => $user['name_last'],
                        'full' => $user['name_first']." ".$user['name_last']
                    )
                ),
                'key'   => $session_key
            )
        ));
    }
    else
        $payload = array( 'status' => 'failure', 'description' => "Email/pass is incorrect.");

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($payload));
});

// register
$app->post('/members/register', function ($request, $response, $args) use ($mysql) {
    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
        'id' => Helper::getUID(),
        'registered' => date('Y-m-d H:i:s'),
        'email' => $body['email'],
        'password' => $body['password'],
        'name_first' => $body['name_first'],
        'name_last' => $body['name_last']
    );

    if($data['email'] == NULL || $data['password'] == NULL || $data['name_first'] == NULL || $data['name_last'] == NULL){
        $missing = array();

        if($data['email'] == NULL)
            $missing[] = "Email";
        if($data['password'] == NULL)
            $missing[] = "Password";
        if($data['name_first'] == NULL)
            $missing[] = "First Name";
        if($data['name_last'] == NULL)
            $missing[] = "Last Name";

        $payload = array( 'status' => 'failure', 'description' => "Please complete the missing fields:\n" . implode(", ", $missing));
    }
    else {
        if(strlen($data['email']) <= 3 || strlen($data['email']) >= 30){
            $payload = array( 'status' => 'failure', 'description' => "Your email must be between 3 and 30 characters.");
        }
        else {
            $stm = $mysql->prepare('SELECT * FROM members WHERE email = :email');
            $stm->execute(array(
                ':email' => $data['email'],
            ));
            $check_members = $stm->fetchAll();

            if(count($check_members) != 0){
                $payload = array( 'status' => 'failure', 'description' => "The email is already in use!");
            }
            else {
                if(strlen($data['password']) <= 5 || strlen($data['password']) >= 12){
                    $payload = array( 'status' => 'failure', 'description' => "Your password must be between 6 and 12 characters.");
                }
                else {
                    if(!filter_var($data['email'], FILTER_VALIDATE_EMAIL)){
                        $payload = array( 'status' => 'failure', 'description' => "Your email address was not valid.");
                    }
                    else {
                        $stm = $mysql->prepare('INSERT INTO members (id, email, password, name_first, name_last, registered, status) VALUES(:mid,:email,:password,:name_first,:name_last,:registered,:status)');
                        $stm->execute(array(
                            ':mid' => $data['id'],
                            ':email' => $data['email'],
                            ':password' => password_hash((string)$data['password'], PASSWORD_DEFAULT),
                            ':name_first' => $data['name_first'],
                            ':name_last' => $data['name_last'],
                            ':registered' => $data['registered'],
                            ':status' => 1
                        ));

                        $stm2 = $mysql->prepare('SELECT id FROM members WHERE id = :mid');
                        $stm2->execute(array(
                            ':mid' => $data['id']
                        ));
                        $lid = $stm2->fetchColumn();
                        if ($lid == $data['id']) {
                            if( Helper::sendMessage('confirmation', $data['email'], $user) )
                                $payload = array( 'status' => 'success', 'description' => 'Thank you for registering. A confirmation email has been sent to '.$data['email'].'.');
                            else
                                $payload = array( 'status' => 'success', 'description' => "Your account was created successfully however, we were not able to send a confirmation email. Please contact <a href='mailto:#'>support</a>.");
                        }
                        else {
                            $payload = array( 'status' => 'failure', 'description' => "Error processing registration. Please try again, or contact <a href='mailto:#'>support</a>.");
                        }
                    }
                }
            }
        }
    }

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($payload));
});

// delete member
$app->post('/members/delete', function ($request, $response, $args) use ($mysql) {
    Helper::validateSession($request, $mysql);

    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
        'id' => $body['member']
    );

    $stm = $mysql->prepare('DELETE FROM members WHERE id = :id');
    $stm->execute(array(
        ':id' => $data['id']
    ));

    if ($stm->rowCount()) {
        $payload = array( 'status' => 'success', 'description' => "Member deleted successfully!");
    }
    else {
        $payload = array( 'status' => 'failure', 'description' => "Error deleting member. Please try again, or contact <a href='mailto:#'>support</a>.");
    }

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($payload));
});

// change password
$app->post('/members/password/change', function ($request, $response, $args) use ($mysql) {
    Helper::validateSession($request, $mysql);

    $payload = array();
    $body = $request->getParsedBody();

    $data = array(
        'id' => $body['member'],
        'password_current' => $body['password_current'],
        'password_new' => $body['password_new']
    );

    $stm = $mysql->prepare('SELECT * FROM members WHERE id = :id');
    $stm->execute(array(
        ':id' => $data['id']
    ));
    $user = $stm->fetch(PDO::FETCH_ASSOC);

    if($user && password_verify($data['password_current'], $user['password'])) {
        $stm = $mysql->prepare('UPDATE members SET password = :password WHERE id = :id');
        $stm->execute(array(
            ':password' => password_hash((string)$data['password_new'], PASSWORD_DEFAULT),
            ':id' => $data['id']
        ));

        if ($stm->rowCount()) {
            $payload = array( 'status' => 'success', 'description' => "Password changed successfully!");
        }
        else {
            $payload = array( 'status' => 'failure', 'description' => "Error changing password. Please try again, or contact <a href='mailto:#'>support</a>.");
        }
    }
    else {
        $payload = array( 'status' => 'failure', 'description' => "Current password incorrect.");
    }

    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($payload));
});
