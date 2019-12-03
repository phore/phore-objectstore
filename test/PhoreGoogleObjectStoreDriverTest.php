<?php

namespace test;

use DateTime;
use Phore\Core\Exception\NotFoundException;
use Phore\HttpClient\Ex\PhoreHttpRequestException;
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

    public function testPutWithMeta()
    {
        $meta['testdata'] = "test";
        $this->driver->put("testMeta.txt", "test", $meta);

        $meta = $this->driver->getMeta("testMeta.txt");
        $this->assertEquals("test", $meta['metadata']['testdata']);
    }

    public function testGetMetaOfExisting()
    {
        $meta = $this->driver->getMeta("DO_NOT_TOUCH_test_2019-12-02.txt");

        $this->assertEquals("2019-12-02T16:20:41.732Z", $meta['timeCreated']);
    }

    public function testGetMetaOfNonExisting()
    {
        $meta = $this->driver->getMeta("fail");

        $this->assertEmpty($meta);
    }

    public function testGetExisting()
    {
        $objectContent = $this->driver->get("DO_NOT_TOUCH_test_2019-12-02.txt");

        $this->assertEquals("DO NOT DELETE OR UPDATE", $objectContent);
    }

    public function testGetNonExisting()
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("No such object");
        $this->driver->get("fail");
    }

    public function testDeleteExisting()
    {
        $result = $this->driver->remove("testMeta.txt");
        $this->assertEmpty($result);
    }

    public function testDeleteNonExisting()
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("No such object");
        $this->driver->remove("fail");
    }

    public function testAppendToNonExisting ()
    {
        $result = $this->driver->append("testMeta.txt", "test append");
        $this->assertTrue($result);
    }

    public function testAppendToExisting ()
    {
        $result = $this->driver->append("testMeta.txt", "test append 2");
        $this->assertIsArray($result);
        $this->assertEquals( "testMeta.txt", $result['name']);
    }

    public function testRenameExistingToNonExisting()
    {
        $result = $this->driver->rename("testMeta.txt", "testMetaRenamed.txt");
        $this->assertEquals( "testMetaRenamed.txt", $result['name']);
    }

    public function testRenameExistingToExisting()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot rename");
        $result = $this->driver->rename("testMetaRenamed.txt", "test.txt");
    }

    public function testRenameNonExisting()
    {
        $this->expectException(PhoreHttpRequestException::class);
        $this->expectExceptionMessage("No such object");
        $result = $this->driver->rename("fail", "something");
        $this->assertEquals( "testMetaRenamed.txt", $result['name']);
    }

    public function testCleanUpAfterTests()
    {
        $this->driver->remove("testMeta.txt");
        $this->driver->remove("testMetaRenamed.txt");
    }



}
