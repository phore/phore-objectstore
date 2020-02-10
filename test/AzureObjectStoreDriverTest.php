<?php
/**
 * Created by IntelliJ IDEA.
 * User: oem
 * Date: 29.01.20
 * Time: 09:25
 */

namespace test;

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions;
use MicrosoftAzure\Storage\Blob\Models\PublicAccessType;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use Phore\Core\Exception\NotFoundException;
use Phore\ObjectStore\Driver\AzureObjectStoreDriver;
use PHPUnit\Framework\TestCase;

class AzureObjectStoreDriverTest extends TestCase
{
    private $accountName = "talpateststorage";
    private $accountKey;
    private $containerName = "test";
    /**
     * @var AzureObjectStoreDriver $driver
     */
    private $driver;

    protected function setUp(): void
    {
        $this->accountKey = phore_file( "../run/secrets/azure")->get_contents();
        $this->driver = new AzureObjectStoreDriver($this->accountName, $this->accountKey, $this->containerName);
    }

    public function testPut(){
        $this->driver->put("test/test.txt", "wurst",["test" => "test"]);
        $this->assertTrue($this->driver->has("test/test.txt"));
    }

    public function testPutEmptyMeta(){
        $this->driver->put("test/test.txt", "wurst");
        $this->assertTrue($this->driver->has("test/test.txt"));
    }

    public function testGetExisting(){
        $result = $this->driver->get("test/test.txt", $meta);
        $this->assertEquals("wurst", $result);
    }

    public function testGetNonExisting(){
        $this->expectException(NotFoundException::class);
        $this->driver->get("test/fail.txt", $meta);
    }

    public function testHas(){
        $this->assertTrue($this->driver->has("test/test.txt"));
        $this->assertFalse($this->driver->has("test/fail.txt"));
    }

    public function testGetMetaData(){
        $this->driver->put("test/test.txt", "wurst",["test" => "test"]);
        $meta = $this->driver->getMeta("test/test.txt");
        $this->assertEquals(["test" => "test"], $meta);
    }

    public function testSetMetaData(){
        $this->driver->setMeta("test/test.txt", ["bla" => "bla"]);
        $meta = $this->driver->getMeta("test/test.txt");
        $this->assertEquals(["bla" => "bla"], $meta);
    }

    public function testRemove(){
        $this->driver->remove("test/test.txt");
        $this->assertFalse($this->driver->has("test/test.txt"));
    }

    public function testRemoveNonexisting(){
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage("The specified blob does not exist.");
        $this->driver->remove("test/fail.txt");
    }

    public function testAppend(){
        $this->driver->put("test/test.txt", "wurst",["test" => "test"]);
        $this->driver->append("test/test.txt", "\nwurst");
        $result = $this->driver->get("test/test.txt");
        $this->assertEquals("wurst\nwurst", $result);
    }

}
