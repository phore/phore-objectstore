<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 20.08.18
 * Time: 13:05
 */

namespace Phore\ObjectStore;


use Phore\ObjectStore\Driver\AzureObjectStoreDriver;
use Phore\ObjectStore\Driver\GoogleObjectStoreDriver;
use Phore\ObjectStore\Driver\ObjectStoreDriver;
use Phore\ObjectStore\Type\ObjectStoreObject;


class ObjectStore
{

    /**
     * @var ObjectStoreDriver
     */
    private $driver;


    public function __construct(ObjectStoreDriver $objectStoreDriver)
    {
        $this->driver = $objectStoreDriver;
    }

    /**
     * @param string|array $configFile
     * @param string $bucketName
     * @return ObjectStore
     * @throws \Phore\FileSystem\Exception\FileNotFoundException
     * @throws \Phore\FileSystem\Exception\FileParsingException
     */
    public static function loadFromConfig($configFile, string $bucketName) {
        if(is_array($configFile)) {
            $config = $configFile;
        } else if (is_string($configFile)) {
            $config = phore_file($configFile)->get_json();
        }
        $provider = phore_pluck('provider', $config, new \InvalidArgumentException("Missing provider in ObjectStore config."));
        $credentials = phore_pluck('credentials', $config, new \InvalidArgumentException("Missing credentials in ObjectStore config."));
        switch ($provider) {
            case 'azure-blob-storage':
                $account = phore_pluck('account', $credentials, new \InvalidArgumentException("Missing account name in ObjectStore config."));
                $key = phore_pluck('key', $credentials, new \InvalidArgumentException("Missing account key in ObjectStore config."));
                return new ObjectStore(new AzureObjectStoreDriver($account, $key, $bucketName));
            case 'google-cloud-storage':
                return new ObjectStore(new GoogleObjectStoreDriver($credentials, $bucketName));
        }
    }

    public function getDriver() : ObjectStoreDriver
    {
        return $this->driver;
    }

    public function has(string $objectId) : bool
    {
        return $this->driver->has($objectId);
    }

    public function object(string $objectId) : ObjectStoreObject
    {
        return new ObjectStoreObject($this->driver, $objectId);
    }

    public function walk(callable $fn) : bool
    {
        return $this->driver->walk($fn);
    }

}
