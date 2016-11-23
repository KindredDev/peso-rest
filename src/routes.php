<?php
// error_reporting(E_ALL); ini_set('display_errors', 1);

include_once('../vendor/kindred/peso/peso.php');

// Routes

$app->post('/', function ($request, $response, $args) {
  $this->logger->info("POST request to '/'");

  $json = $request->getBody();
  $peso = new Peso($json);

  $data = false;
  if ($peso->data && is_array($peso->data))
    $data = $peso->build();

  return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($data));
});
