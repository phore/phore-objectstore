<?php

namespace test;

use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Core\Exception\NotFoundException;
use Google\Cloud\Core\Exception\ServiceException;
use Phore\ObjectStore\Driver\GoogleObjectStoreDriver;
use PHPUnit\Framework\TestCase;

class GoogleObjectStoreDriverTest extends TestCase
{
    /**
     * @var GoogleObjectStoreDriver $driver
     */
    public $driver;

    protected function setUp(): void
    {
        $keyFilePath = '/run/secrets/google_test';
        $this->driver = new GoogleObjectStoreDriver($keyFilePath, 'phore-test2');
        $objectId = 'test/test.txt';
        if($this->driver->has($objectId)){
            $this->driver->remove('test/test.txt');
        }
    }

    public function testValidKeyFilePath(): void
    {
        $this->assertFalse($this->driver->has('fail'));
    }

    public function testValidKeyFileArray(): void
    {
        $keyFilePath = '/run/secrets/google_test';
        $keyFile = phore_file($keyFilePath)->get_json();
        $driver = new GoogleObjectStoreDriver($keyFile, 'phore-test2');
        $this->assertFalse($driver->has('fail'));
    }

    public function testInvalidKeyFilePath(): void
    {
        $keyFilePath = 'fail';
        $this->expectException(GoogleException::class);
        $this->expectExceptionMessage('Given keyfile path fail does not exist');
        $driver = new GoogleObjectStoreDriver($keyFilePath, 'phore-test2');
        $driver->has('fail');
    }

    public function testInvalidKeyFileArray(): void
    {
        $keyFile = [];
        $driver = new GoogleObjectStoreDriver($keyFile, 'phore-test2');
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Invalid Credentials');
        $driver->has('fail');
    }

    public function testList(): void
    {
        $list = $this->driver->list();
        $this->assertArrayHasKey('blobName', $list[0]);
        $this->assertArrayHasKey('blobUrl', $list[0]);
        $this->assertCount(17, $list);
        $list = $this->driver->list('f90ed-M-t');
        $this->assertCount(2, $list);
    }

    public function testGetSetMetaData(): void
    {
        $this->driver->put('test/test.txt', 'test metadata',  ['filesize' => '2345']);
        $this->assertEquals('2345', $this->driver->getMeta('test/test.txt')['metadata']['filesize']);
        $this->driver->setMeta('test/test.txt', ['filesize' => '234567']);
        $this->assertEquals('234567', $this->driver->getMeta('test/test.txt')['metadata']['filesize']);
        $this->driver->remove('test/test.txt');
    }

}
