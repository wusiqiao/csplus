<?php

use Vender\MySQL;
require_once __DIR__."/ThinkPHP/Library/Vendor/mysql.class.php";
define("DOMAIN", "eshop");
define("DBNAME", "shop");
define("DBUSER", "caisuikx");
define("DBPASSWORD", "");
define("DBPORT", 3876);

if (count($argv) >0 && $argv[1] == DOMAIN) {
    $lock_file = "/home/wwwlogs/eshop_queue_sync.lock";
    $fp = fopen($lock_file, "w+"); //必须同步
    if (flock($fp, LOCK_EX + LOCK_NB)) {
        try {
            echo date("Y-m-d H:i:s")." start shop_queue_sync\n";
            $my_pdo = new \Vender\MySQL\Connection("localhost", DBPORT, DBUSER, DBPASSWORD, DBNAME);
            while (true) {
				$now = time();
                $queues = $my_pdo->query("select * from sys_req_queue where status = 0 and event_time<=$now");
                if ($queues) {
                    foreach ($queues as $queue) {
                        $my_pdo->query("update sys_req_queue set status=1 where id=:id", array("id" => $queue["id"]));
                        $salt = substr($queue["id"], 0, 4) . substr($queue["id"], -1, 4);
                        $token = md5($salt . $queue["id"]);
                        $head_data = array();
                        $head_data[] = "token:" . $token;
                        $head_data[] = "queueid:" . $queue["id"];
                        request($queue["url"], $head_data);
			echo date("Y-m-d H:i:s")." start request:".$queue["id"]."\n";
                        $head_data = null;
                    }
                }
                sleep(5);
            }
            $my_pdo->closeConnection();
        } catch (Exception $exception) {
            echo $exception->getMessage();
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function request($url, $header) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    if (stripos($url, "https://") !== FALSE) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
    }
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);     //注意，毫秒超时一定要设置这个
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 200); //不等待返回
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_exec($ch);
    curl_close($ch);
};

