<?php
/**
 * Created by IntelliJ IDEA
 * User: jan
 * Date: 15.04.20
 * Time: 08:38
 */

namespace test;

use Phore\ObjectStore\Driver\FileSystemObjectStoreDriver;
use PHPUnit\Framework\TestCase;

class FileSystemObjectStoreDriverTest extends TestCase
{
    public function testList(): void
    {
        $fileSystemObjectStoreDriver = new FileSystemObjectStoreDriver('/tmp');
        $list = $fileSystemObjectStoreDriver->list();
        $this->assertCount(3, $list);
        $list = $fileSystemObjectStoreDriver->list('goo');
        $this->assertCount(1, $list);
        $this->assertArrayHasKey('blobName', $list[0]);
        $this->assertArrayHasKey('blobUrl', $list[0]);
        $this->assertEquals('googleConfig.json', $list[0]['blobName']);
        $list = $fileSystemObjectStoreDriver->list('kuchen');
        $this->assertCount(0, $list);
    }
}
