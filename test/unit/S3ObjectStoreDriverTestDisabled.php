<?php


namespace unit;


use Phore\Core\Exception\NotFoundException;
use Phore\ObjectStore\Driver\S3ObjectStoreDriver;
use Phore\ObjectStore\ObjectStore;
use PHPUnit\Framework\TestCase;

class S3ObjectStoreDriverTestDisabled extends TestCase
{



    private function getDriver() : S3ObjectStoreDriver
    {
        return new S3ObjectStoreDriver(
            "eu-central-1",
            "raw-data-dev-talpa-cloud",
            "AKIAUSDYXI6G64ORE65T",
            phore_file(AWS_KEYFILE_PATH)->get_contents()
        );
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

    public function testAppend()
    {
        $c = $this->getDriver();
        $c->remove("t/append-test");

        $c->append("t/append-test", "ab");
        $c->append("t/append-test", "cd");

        $content = $c->get("t/append-test");
        self::assertEquals("abcd", $content);
    }


    public function testList()
    {
        $c = $this->getDriver();
        $result = $c->list();
        print_r ($result);
        self::assertContains("unit1", $result);
    }


    public function testIntegrationUriConfig()
    {
        $d = ObjectStore::Connect("s3nd://raw-data-dev-talpa-cloud?region=eu-central-1&account=AKIAUSDYXI6G64ORE65T&keyfile=" . AWS_KEYFILE_PATH);
        $o = $d->object("testObj_integration");
        $o->put("Hello");
        self::assertEquals("Hello", $o->get());
    }

}
