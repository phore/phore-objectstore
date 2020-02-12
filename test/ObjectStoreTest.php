<?php

namespace test;

use Phore\ObjectStore\Driver\GoogleObjectStoreDriver;
use Phore\ObjectStore\ObjectStore;
use PHPUnit\Framework\TestCase;

class ObjectStoreTest extends TestCase
{

    public function testLoadFromConfigGoogle()
    {
        $configArray['provider'] = "google-cloud-storage";
        $configArray['credentials'] = phore_file( "../run/secrets/google_test")->get_json();
        $configFilePath = "/tmp/googleConfig.json";
        phore_file($configFilePath)->set_json($configArray);

        $objectStore = ObjectStore::loadFromConfig($configFilePath, "phore-test2");
        $objectStore->object('configTest')->put("test");
        $content = $objectStore->object('configTest')->get();
        $this->assertEquals("test", $content);
    }

    public function testLoadFromConfigAzure() {
        $configArray['provider'] = "azure-blob-storage";
        $configArray['credentials']['key'] = phore_file( "../run/secrets/azure")->get_contents();
        $configArray['credentials']['account'] = "talpateststorage";
        $configFilePath = "/tmp/azureConfig.json";
        phore_file($configFilePath)->set_json($configArray);

        $objectStore = ObjectStore::loadFromConfig($configFilePath, "test");
        $objectStore->object('configTest')->put("test");
        $content = $objectStore->object('configTest')->get();
        $this->assertEquals("test", $content);

    }
}
