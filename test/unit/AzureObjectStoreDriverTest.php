<?php
/**
 * Created by IntelliJ IDEA.
 * User: oem
 * Date: 29.01.20
 * Time: 09:25
 */

namespace test;

use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use Phore\Core\Exception\NotFoundException;
use Phore\ObjectStore\Driver\AzureObjectStoreDriver;
use PHPUnit\Framework\TestCase;

class AzureObjectStoreDriverTest extends TestCase
{
    private $accountName = 'talpateststorage';
    private $containerName = 'test';
    /**
     * @var AzureObjectStoreDriver $driver
     */
    private $driver;

    protected function setUp(): void
    {
        $accountKey = phore_file(AZURE_KEYFILE_PATH)->get_contents();
        $this->driver = new AzureObjectStoreDriver($this->accountName, $accountKey, $this->containerName);
    }

    public function testPut(): void
    {
        $this->driver->put('test/test.txt', 'wurst', ['test' => 'test']);
        $this->assertTrue($this->driver->has('test/test.txt'));
    }

    public function testPutEmptyMeta(): void
    {
        $this->driver->put('test/test.txt', 'wurst');
        $this->assertTrue($this->driver->has('test/test.txt'));
    }

    public function testGetExisting(): void
    {
        $result = $this->driver->get('test/test.txt', $meta);
        $this->assertEquals('wurst', $result);
    }

    public function testGetNonExisting(): void
    {
        $this->expectException(NotFoundException::class);
        $this->driver->get('test/fail.txt', $meta);
    }

    public function testHas(): void
    {
        $this->assertTrue($this->driver->has('test/test.txt'));
        $this->assertFalse($this->driver->has('test/fail.txt'));
    }

    public function testGetMetaData(): void
    {
        $this->driver->put('test/test.txt', 'wurst', ['test' => 'test']);
        $meta = $this->driver->getMeta('test/test.txt');
        $this->assertEquals(['test' => 'test'], $meta);
    }

    public function testSetMetaData(): void
    {
        $this->driver->setMeta('test/test.txt', ['bla' => 'bla']);
        $meta = $this->driver->getMeta('test/test.txt');
        $this->assertEquals(['bla' => 'bla'], $meta);
    }

    public function testRemove(): void
    {
        $this->driver->remove('test/test.txt');
        $this->assertFalse($this->driver->has('test/test.txt'));
    }

    public function testRemoveNonExisting(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('The specified blob does not exist.');
        $this->driver->remove('test/fail.txt');
    }

    public function testAppend(): void
    {
        $this->driver->put('test/test.txt', 'wurst', ['test' => 'test']);
        $this->driver->append('test/test.txt', "\nwurst");
        $result = $this->driver->get('test/test.txt');
        $this->assertEquals("wurst\nwurst", $result);
    }

    public function testList(): void
    {
        $list = $this->driver->list();
        $this->assertArrayHasKey('blobName', $list[0]);
        $this->assertArrayHasKey('blobUrl', $list[0]);
        $this->assertCount(164, $list);
        $list = $this->driver->list('test');
        $this->assertCount(4, $list);
        $list = $this->driver->list('kuchen');
        $this->assertCount(0, $list);
    }
}
