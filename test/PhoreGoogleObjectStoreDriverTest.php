<?php

namespace test;

use DateTime;
use Phore\ObjectStore\Driver\PhoreGoogleObjectStoreDriver;
use PHPUnit\Framework\TestCase;

class PhoreGoogleObjectStoreDriverTest extends TestCase
{

    private $configPath;
    private $driver;

    protected function setUp(): void
    {
        $this->configPath = "/run/secrets/google_test";
        $this->driver = new PhoreGoogleObjectStoreDriver($this->configPath, "phore-objectstore-unit-testing");
    }

    public function testHas()
    {

        $this->assertFalse($this->driver->has("fail"));
    }

    public function testPutWithoutMeta()
    {
        $this->driver->put("test.txt", "test");
        $this->assertTrue($this->driver->has("test.txt"));
    }

    public function testGetMeta()
    {
        $meta = $this->driver->getMeta("DO_NOT_TOUCH_test_2019-12-02.txt");

        $this->assertEquals("2019-12-02T16:20:41.732Z", $meta['timeCreated']);
    }

    public function testGet()
    {
        $objectContent = $this->driver->get("DO_NOT_TOUCH_test_2019-12-02.txt");

        $this->assertEquals("DO NOT DELETE OR UPDATE", $objectContent);
    }

}
