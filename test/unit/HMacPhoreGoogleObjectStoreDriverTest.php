<?php

namespace unit;

use InvalidArgumentException;
use Phore\Core\Exception\NotFoundException;
use Phore\HttpClient\Ex\PhoreHttpRequestException;
use Phore\ObjectStore\Driver\PhoreGoogleObjectStoreDriver;
use PHPUnit\Framework\TestCase;

class HMacPhoreGoogleObjectStoreDriverTest extends TestCase
{

    protected static $configPath;
    /**
     * @var PhoreGoogleObjectStoreDriver $driver
     */
    protected static $driver;

    public static function setUpBeforeClass(): void
    {
        self::$configPath = '/run/secrets/google_test';
        self::$driver = new PhoreGoogleObjectStoreDriver(self::$configPath, 'phore-objectstore-unit-testing');
    }

    public static function tearDownAfterClass(): void
    {
        self::$driver->remove('testMeta.txt');
        self::$driver->remove('testMetaRenamed.txt');
    }

    public function testHas(): void
    {

        $this->assertFalse(self::$driver->has('fail'));
    }

    public function testPutWithoutMeta(): void
    {
        self::$driver->put('test.txt', 'test');
        $this->assertTrue(self::$driver->has('test.txt'));
    }

    public function testPutWithMeta(): void
    {
        $meta['testdata'] = 'test';
        self::$driver->put('testMeta.txt', 'test', $meta);

        $meta = self::$driver->getMeta('testMeta.txt');
        $this->assertEquals('test', $meta['metadata']['testdata']);
    }

    public function testGetMetaOfExisting(): void
    {
        $meta = self::$driver->getMeta('DO_NOT_TOUCH_test_2019-12-02.txt');

        $this->assertEquals('2019-12-02T16:20:41.732Z', $meta['timeCreated']);
    }

    public function testGetMetaOfNonExisting(): void
    {
        $meta = self::$driver->getMeta('fail');

        $this->assertEmpty($meta);
    }

    public function testGetExisting(): void
    {
        $objectContent = self::$driver->get('DO_NOT_TOUCH_test_2019-12-02.txt');

        $this->assertEquals('DO NOT DELETE OR UPDATE', $objectContent);
    }

    public function testGetNonExisting(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No such object');
        self::$driver->get('fail');
    }

    public function testDeleteExisting(): void
    {
        $result = self::$driver->remove('testMeta.txt');
        $this->assertEmpty($result);
    }

    public function testDeleteNonExisting(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No such object');
        self::$driver->remove('fail');
    }

    public function testAppendToNonExisting (): void
    {
        $result = self::$driver->append('testMeta.txt', 'test append');
        $this->assertTrue($result);
    }

    public function testAppendToExisting (): void
    {
        $result = self::$driver->append('testMeta.txt', 'test append 2');
        $this->assertTrue( $result);
    }

    public function testRenameExistingToNonExisting(): void
    {
        $result = self::$driver->rename('testMeta.txt', 'testMetaRenamed.txt');
        $this->assertEquals( 'testMetaRenamed.txt', $result['name']);
    }

    public function testRenameExistingToExisting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot rename');
        self::$driver->rename('testMetaRenamed.txt', 'test.txt');
    }

    public function testRenameNonExisting(): void
    {
        $this->expectException(PhoreHttpRequestException::class);
        $this->expectExceptionMessage('No such object');
        $result = self::$driver->rename('fail', 'something');
        $this->assertEquals( 'testMetaRenamed.txt', $result['name']);
    }

    /*
    public function testAsync()
    {
        $queue = new PhoreHttpAsyncQueue();

        //$queue->queue(phore_http_request("http://localhost/test.php?case=wait"));

        phore_out("Start");

        $err = 0;
        $ok = 0;
        $token = self::$driver->accessToken;
        for ($i=0; $i<300; $i++) {
            $queue->queue(phore_http_request("https://storage.googleapis.com/storage/v1/b/phore-objectstore-unit-testing/o/DO_NOT_TOUCH_test_2019-12-02.txt")
                ->withBearerAuth($token)->withTimeout(1000,10000))->then(
                function(PhoreHttpResponse $response) use (&$data, &$ok)  {
                    phore_out("OK$ok:");
                    $ok++;

                },
                function (PhoreHttpRequestException $ex) use (&$err){
                    phore_out("ERR$err:" . $ex->getMessage());
                    $err++;
                });
        }

        $queue->wait();

        phore_out("stop");

        echo "\nOK: $ok Err: $err\n";
        echo $data;

    }
*/
}
