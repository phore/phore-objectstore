<?php


namespace unit;


use Phore\Core\Exception\NotFoundException;
use Phore\ObjectStore\Driver\S3ObjectStoreDriver;
use PHPUnit\Framework\TestCase;

class S3ObjectStoreDriverTest extends TestCase
{

    private function getDriver() : S3ObjectStoreDriver
    {
        return new S3ObjectStoreDriver([
            "version" => "latest",
            "region" => "eu-central-1",
            "credentials" => [
                "key" => AWS_ACCESS_KEY_ID,
                "secret" => AWS_SECRET_ACCESS_KEY
            ]
        ], "raw-data-dev-talpa-cloud");
    }

    public function testPutLoadObject()
    {
        $c = $this->getDriver();
        $c->put("unit1", "ABC");

        self::assertEquals("ABC", $c->get("unit1"));
    }

    public function testLoadInvalidKeyObject()
    {
        $this->expectException(NotFoundException::class);
        $c = $this->getDriver();
        $c->get("invalid_key");
    }

}
