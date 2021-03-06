<?php

namespace test;

use Google\Cloud\Core\Exception\GoogleException;
use InvalidArgumentException;
use Phore\FileSystem\Exception\FileNotFoundException;
use Phore\ObjectStore\Driver\AzureObjectStoreDriver;
use Phore\ObjectStore\Driver\FileSystemObjectStoreDriver;
use Phore\ObjectStore\Driver\GoogleObjectStoreDriver;
use Phore\ObjectStore\Driver\PhoreGoogleObjectStoreDriver;
use Phore\ObjectStore\ObjectStore;
use PHPUnit\Framework\TestCase;

class ObjectStoreTest extends TestCase
{
    public function testConnectFromUriGcs(): void
    {
        $objectStore = ObjectStore::Connect('gcs://bucketname?keyfile=/../run/secrets/google_test');
        $this->assertInstanceOf(PhoreGoogleObjectStoreDriver::class, $objectStore->getDriver());
    }

    public function testConnectFromUriGcsNd(): void
    {
        $objectStore = ObjectStore::Connect('gcsnd://bucketname?keyfile=/../run/secrets/google_test');
        $this->assertInstanceOf(GoogleObjectStoreDriver::class, $objectStore->getDriver());
    }

    public function testConnectFromUriAzbs(): void
    {
        $objectStore = ObjectStore::Connect('azbsnd://bucketname?account=test&keyfile=/run/secrets/azure');
        $this->assertInstanceOf(AzureObjectStoreDriver::class, $objectStore->getDriver());
    }

    public function testConnectFromUriFile(): void
    {
        $objectStore = ObjectStore::Connect('file://tmp');
        $this->assertInstanceOf(FileSystemObjectStoreDriver::class, $objectStore->getDriver());
    }

    public function testConnectFromInvalidURIKeyfilePathGcs(): void
    {
        $this->expectException(FileNotFoundException::class);
        ObjectStore::Connect('gcs://bucketname?keyfile=fail');
    }

    public function testConnectFromInvalidURIKeyfilePathGcsnd(): void
    {
        $this->expectException(GoogleException::class);
        ObjectStore::Connect('gcsnd://bucketname?keyfile=fail');
    }

    public function testConnectFromInvalidURIMissingParamGcsnd(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing keyfile in objectstore URI');
        ObjectStore::Connect('gcsnd://bucketname');
    }

    public function testConnectFromInvalidUriNoAccessFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Root directory '/fail' not accessible");
        ObjectStore::Connect('file://fail');
    }

    public function testLoadFromConfigGoogle(): void
    {
        $configArray['provider'] = 'google-cloud-storage';
        $configArray['credentials'] = phore_file('../run/secrets/google_test')->get_json();
        $configFilePath = '/tmp/googleConfig.json';
        phore_file($configFilePath)->set_json($configArray);

        $objectStore = ObjectStore::loadFromConfig($configFilePath, 'phore-test2');
        $objectStore->object('configTest')->put('test');
        $content = $objectStore->object('configTest')->get();
        $this->assertEquals('test', $content);
    }

    public function testLoadFromConfigAzure(): void
    {
        $configArray['provider'] = 'azure-blob-storage';
        $configArray['credentials']['key'] = phore_file('../run/secrets/azure')->get_contents();
        $configArray['credentials']['account'] = 'talpateststorage';
        $configFilePath = '/tmp/azureConfig.json';
        phore_file($configFilePath)->set_json($configArray);

        $objectStore = ObjectStore::loadFromConfig($configFilePath, 'test');
        $objectStore->object('configTest')->put('test');
        $content = $objectStore->object('configTest')->get();
        $this->assertEquals('test', $content);

    }
}
