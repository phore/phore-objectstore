<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 20.08.18
 * Time: 13:05
 */

namespace Phore\ObjectStore;


use Exception;
use InvalidArgumentException;
use Phore\Core\Helper\PhoreUrl;
use Phore\FileSystem\Exception\FileAccessException;
use Phore\FileSystem\Exception\FileNotFoundException;
use Phore\FileSystem\Exception\FileParsingException;
use Phore\HttpClient\Ex\PhoreHttpRequestException;
use Phore\ObjectStore\Driver\AzureObjectStoreDriver;
use Phore\ObjectStore\Driver\FileSystemObjectStoreDriver;
use Phore\ObjectStore\Driver\GoogleObjectStoreDriver;
use Phore\ObjectStore\Driver\ObjectStoreDriver;
use Phore\ObjectStore\Driver\PhoreGoogleObjectStoreDriver;
use Phore\ObjectStore\Driver\S3ObjectStoreDriver;
use Phore\ObjectStore\Type\ObjectStoreObject;


/**
 * Class ObjectStore
 * @package Phore\ObjectStore
 */
class ObjectStore
{

    /**
     * @var ObjectStoreDriver
     */
    private $driver;
    

    /**
     * ObjectStore constructor.
     * @param ObjectStoreDriver $objectStoreDriver
     */
    public function __construct(ObjectStoreDriver $objectStoreDriver)
    {
        $this->driver = $objectStoreDriver;
 
    }

    /**
     * @param string|array $configFile
     * @param string $bucketName
     * @return ObjectStore
     * @throws FileNotFoundException
     * @throws FileParsingException
     * @throws Exception
     */
    public static function loadFromConfig($configFile, string $bucketName): ?ObjectStore
    {
        if (is_array($configFile)) {
            $config = $configFile;
        } else if (is_string($configFile)) {
            $config = phore_file($configFile)->get_json();
        }
        $provider = phore_pluck('provider', $config, new InvalidArgumentException('Missing provider in ObjectStore config.'));
        $credentials = phore_pluck('credentials', $config, new InvalidArgumentException('Missing credentials in ObjectStore config.'));
        switch ($provider) {
            case 'azure-blob-storage':
                $account = phore_pluck('account', $credentials, new InvalidArgumentException('Missing account name in ObjectStore config.'));
                $key = phore_pluck('key', $credentials, new InvalidArgumentException('Missing account key in ObjectStore config.'));
                return new ObjectStore(new AzureObjectStoreDriver((string)$account, (string)$key, $bucketName));
            case 'google-cloud-storage':
                return new ObjectStore(new GoogleObjectStoreDriver($credentials, $bucketName));
        }
        throw new InvalidArgumentException("Invalid scheme for '$provider'");
    }


    protected static function _GetKey(PhoreUrl $uriParts) : string
    {
        $key = $uriParts->getQueryVal("secretkey", "");
        if ($uriParts->hasQueryVal("keyfile"))
            $key = phore_file($uriParts->getQueryVal("keyfile"))->get_contents();
        return $key;
    }

    /**
     * @param string $uri
     * @return ObjectStore
     * @throws FileAccessException
     * @throws FileNotFoundException
     * @throws FileParsingException
     * @throws PhoreHttpRequestException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public static function Connect(string $uri): ObjectStore
    {
        $uriParts = phore_parse_url($uri);
        $bucketName = $uriParts->host;
        switch ($uriParts->scheme) {
            case 'gcsnd':
                $keyFilePath = $uriParts->getQueryVal('keyfile', new InvalidArgumentException('Missing keyfile in objectstore URI.'));
                return new ObjectStore(new GoogleObjectStoreDriver($keyFilePath, $bucketName));

            case 'gcs':
                $keyFilePath = $uriParts->getQueryVal('keyfile', new InvalidArgumentException('Missing keyfile in objectstore URI.'));
                return new ObjectStore(new PhoreGoogleObjectStoreDriver($keyFilePath, $bucketName));

            case 'azbsnd':
                $account = $uriParts->getQueryVal('account', new InvalidArgumentException('Missing account in objectstore URI.'));
                $key = self::_GetKey($uriParts);
                return new ObjectStore(new AzureObjectStoreDriver($account, $key, $bucketName));

            case 'file':
                return new ObjectStore(new FileSystemObjectStoreDriver('/' . $bucketName));

            case 's3nd':
                $account = $uriParts->getQueryVal("account", null);
                $region = $uriParts->getQueryVal("region", new InvalidArgumentException("Missing 'region' query parameter"));
                $key = self::_GetKey($uriParts);
                return new ObjectStore(new S3ObjectStoreDriver($region, $bucketName, $account, $key));
        }
        throw new InvalidArgumentException("Invalid scheme for '$uri'");
    }

    /**
     * @return ObjectStoreDriver
     */
    public function getDriver(): ObjectStoreDriver
    {
        return $this->driver;
    }
    

    /**
     * @param string $objectId
     * @return bool
     */
    public function has(string $objectId): bool
    {
        return $this->driver->has($objectId);
    }

    /**
     * @param string $objectId
     * @return ObjectStoreObject
     */
    public function object(string $objectId): ObjectStoreObject
    {
        return new ObjectStoreObject($this->driver, $objectId);
    }

    /**
     * @param callable $fn
     * @return bool
     */
    public function walk(callable $fn): bool
    {
        return $this->driver->walk($fn);
    }

}
