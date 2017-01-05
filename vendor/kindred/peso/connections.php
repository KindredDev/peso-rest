<?php

    class ConnectionFactory
    {
        private static $factory;
        public static function getFactory() {
            if (!self::$factory)
                self::$factory = new ConnectionFactory(…);
            return self::$factory;
        }

        private $mysql;
        public function getMySqlConnection() {
            if (!$mysql)
                // $mysql = new PDO('mysql:dbname='.$_SERVER["DB1_NAME"].';host='.$_SERVER["DB1_HOST"].';port='.$_SERVER["DB1_PORT"], $_SERVER["DB1_USER"], $_SERVER["DB1_PASS"]);
                $mysql = new PDO('mysql:dbname=peso;host=127.0.0.1;port=3306', 'peso', 'pesopeso');
            return $mysql;
        }

        /*
        private $transactions;
        public function getTransactionsConnection() {
            if (!$transactions)
                $transactions = new couchClient ('https://{{account}}.cloudant.com/','transactions');
            return $transactions;
        }
        */
    }

?>
