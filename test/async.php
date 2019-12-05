<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 04.12.19
 * Time: 16:59
 */

namespace test;

use Phore\HttpClient\Ex\PhoreHttpRequestException;
use Phore\HttpClient\PhoreHttpAsyncQueue;
use Phore\HttpClient\PhoreHttpResponse;
use Phore\ObjectStore\Driver\PhoreGoogleObjectStoreDriver;

require __DIR__ . "/../vendor/autoload.php";

$googleSecret = "/run/secrets/google_test";



$driver = new PhoreGoogleObjectStoreDriver($googleSecret, "phore-test2");
$url = "https://storage.googleapis.com/storage/v1/b/phore-test2/o/test.dat";


$driver = new PhoreGoogleObjectStoreDriver($googleSecret, "phore-objectstore-unit-testing");
$url = "https://storage.googleapis.com/storage/v1/b/phore-objectstore-unit-testing/o/test.dat";

#$url="https://ulan.talpa.io";


//$queue->queue(phore_http_request("http://localhost/test.php?case=wait"));



$err = 0;
$ok = 0;
$token = $driver->accessToken;



phore_out("put");
$driver->put("test.dat", "some content");




for ($iv=0; $iv<10; $iv++) {

    phore_out("Start $iv");
    $queue = new PhoreHttpAsyncQueue();
    for ($i = 0; $i < 100; $i++) {
        $queue->queue(phore_http_request($url)
            ->withBearerAuth($token)->withSslVerify(false)->withTimeout(15, 30))->then(
            function (PhoreHttpResponse $response) use (&$data, &$ok) {
                #phore_out("OK$ok:");
                $ok++;

            },
            function (PhoreHttpRequestException $ex) use (&$err) {
                $respons = $ex->getResponse();
                if ($respons instanceof PhoreHttpResponse)
                    print_r ($respons->getHeaders());

                phore_out("ERR$err:" . $ex->getMessage());
                $err++;
            });
    }

    $queue->wait();
    phore_out("\nOK: $ok Err: $err\n");
}





