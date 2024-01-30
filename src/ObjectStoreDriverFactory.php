<?php

namespace Phore\ObjectStore;

use Phore\ObjectStore\Driver\AzureObjectStoreDriver;
use Phore\ObjectStore\Driver\FileSystemObjectStoreDriver;
use Phore\ObjectStore\Driver\GoogleObjectStoreDriver;
use Phore\ObjectStore\Driver\PhoreGoogleObjectStoreDriver;
use Phore\ObjectStore\Driver\S3ObjectStoreDriver;
use Phore\ObjectStore\Encryption\SodiumSyncEncryption;

class ObjectStoreDriverFactory
{

    /**
     * Choose the correct driver based on the connection string
     *
     * Examples
     * file:///path/to/dir?encrypt=SodiumSnyc&encryptSecret=xyz  =>  FileSystemObjectStoreDriver mit SodiumSyncEncryption
     * file:///path/to/dir?encrypt=SodiumSnyc&encryptSecretFile=/path/to/secret  =>  FileSystemObjectStoreDriver mit SodiumSyncEncryption (load secret from file)
     * gcs+phore://bucket-name?keyFile=/path/to/keyfile.json  =>  PhoreGoogleObjectStoreDriver
     * gcs+phore://bucket-name?keyFile=/path/to/keyfile.json&encrypt=SodiumSnyc&encryptSecretFile=/path/to/secret  =>  PhoreGoogleObjectStoreDriver
     *
     *
     * @param string $connectionString
     * @return void
     */
    public static function Build(string $connectionString) {
        $url = phore_parse_url($connectionString);

        // Parse the query string into array
        $query = [];
        parse_str($url->query, $query);

        if ($url->scheme === "file") {
            $path = $url->path;
            if ($path === null)
                throw new \InvalidArgumentException("Missing path in connection string. Specify path as 'file:///path/to/dir' (3 slashes)");
            $driver = new FileSystemObjectStoreDriver($path);
            if ($query['encrypt'] !== null) {
                $driver->setEncryption(new SodiumSyncEncryption($query['encryptSecret'] ?? phore_file($query['encryptSecretFile'] ?? throw new \InvalidArgumentException("encryptSecret or encryptSecretFile missing"))->get_contents()));
            }
            return $driver;
        }
        if ($url->scheme === "gcs+phore") {
            $keyFile = $query['keyFile'] ?? null;
            if ($keyFile === null)
                throw new \InvalidArgumentException("Missing keyFile in connection string. Specify keyFile as 'gcs+phore://bucket-name?keyFile=/path/to/keyfile.json'");
            $driver = new PhoreGoogleObjectStoreDriver($keyFile, $url->host);
            if ($query['encrypt'] !== null) {
                $driver->setEncryption(new SodiumSyncEncryption($query['encryptSecret'] ?? phore_file($query['encryptSecretFile'])->get_contents()));
            }
            return $driver;
        }
        if ($url->scheme === "gcs") {
            $keyFile = $query['keyFile'] ?? null;
            if ($keyFile === null)
                throw new \InvalidArgumentException("Missing keyFile in connection string. Specify keyFile as 'gcs+phore://bucket-name?keyFile=/path/to/keyfile.json'");
            $driver = new GoogleObjectStoreDriver($keyFile, $url->host);
            if ($query['encrypt'] !== null) {
                $driver->setEncryption(new SodiumSyncEncryption($query['encryptSecret'] ?? phore_file($query['encryptSecretFile'])->get_contents()));
            }
            return $driver;
        }


    }

}
