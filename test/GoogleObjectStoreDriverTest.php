<?php

namespace test;

use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Core\Exception\ServiceException;
use Phore\ObjectStore\Driver\GoogleObjectStoreDriver;
use PHPUnit\Framework\TestCase;

class GoogleObjectStoreDriverTest extends TestCase
{
    public function testValidKeyFilePath() {
        $keyFilePath = "/run/secrets/google_test";
        $driver = new GoogleObjectStoreDriver($keyFilePath, "phore-test2");
        $this->assertFalse($driver->has("fail"));
    }

    public function testValidKeyFileArray() {
        $keyFilePath = "/run/secrets/google_test";
        $keyFile = phore_file($keyFilePath)->get_json();
        $driver = new GoogleObjectStoreDriver($keyFile, "phore-test2");
        $this->assertFalse($driver->has("fail"));
    }

    public function testInvalidKeyFilePath() {
        $keyFilePath = "fail";
        $this->expectException(GoogleException::class);
        $this->expectExceptionMessage("Given keyfile path fail does not exist");
        $driver = new GoogleObjectStoreDriver($keyFilePath, "phore-test2");
        $driver->has("fail");
    }

    public function testInvalidKeyFileArray() {
        $keyFile = [];
        $driver = new GoogleObjectStoreDriver($keyFile, "phore-test2");
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage("Invalid Credentials");
        $driver->has("fail");
    }
}
