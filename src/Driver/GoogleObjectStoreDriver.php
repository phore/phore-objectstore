<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 20.08.18
 * Time: 13:00
 */

namespace Phore\ObjectStore\Driver;


use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;

use Phore\Core\Exception\NotFoundException;
use Phore\ObjectStore\Type\ObjectStoreObject;
use Psr\Http\Message\StreamInterface;

class GoogleObjectStoreDriver implements ObjectStoreDriver
{

    /**
     * @var \Google\Cloud\Storage\Bucket
     */
    private $bucket;

    public function __construct(string $keyFilePath, string $bucketName)
    {
        if ( ! class_exists(StorageClient::class))
            throw new \InvalidArgumentException("Package google/cloud-storage is missing. Install it by running 'composer install google/cloud-storage'");
        $store = new StorageClient([
            "keyFilePath" => $keyFilePath
        ]);

        $this->bucket = $store->bucket($bucketName);
    }


    public function has(string $objectId): bool
    {
        return $this->bucket->object($objectId)->exists();
    }

    private function _getPutOpts($objectId, array $metadata=null)
    {
        $opts = [
            "name" => $objectId,
            'predefinedAcl' => 'projectprivate'
        ];
        if ($metadata !== null) {
            $opts["metadata"] = [
                "metadata" => $metadata
            ];
        }
        return $opts;
    }

    public function put(string $objectId, $content, array $metadata=null)
    {
        $opts = $this->_getPutOpts($objectId, $metadata);
        $this->bucket->upload($content, $opts);
    }

    public function putStream(string $objectId, $ressource, array $metadata=null)
    {
        $opts = $this->_getPutOpts($objectId, $metadata);
        $this->bucket->upload($ressource, $opts);
    }

    /**
     * @param string $objectId
     * @return StreamInterface
     * @throws NotFoundException
     */
    public function get(string $objectId, array &$meta = null): string
    {
        try {
            $object = $this->bucket->object($objectId);
            $data = $object->downloadAsString();
            $info = $object->info();
            if (isset ($info["metadata"]))
                $meta = $info["metadata"];
            return $data;
        } catch (\Google\Cloud\Core\Exception\NotFoundException $e) {
            throw new NotFoundException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string $objectId
     * @return StreamInterface
     * @throws NotFoundException
     */
    public function getStream(string $objectId, array &$meta=null) : StreamInterface
    {
        try {
            $object = $this->bucket->object($objectId);
            $stream = $object->downloadAsStream();

            $info = $object->info();
            if (isset ($info["metadata"]))
                $meta = $info["metadata"];

            return $stream;
        } catch (NotFoundException $e) {
            throw new NotFoundException($e->getMessage(), $e->getCode(), $e);
        }
    }


    public function getBucket() : Bucket
    {
        return $this->bucket;
    }


    public function walk(callable $walkFunction, string $filter=null): bool
    {
        foreach ($this->bucket->objects() as $object) {
            $meta = null;
            $info = $object->info();
            if (isset($info["metadata"]))
                $meta = $info["metadata"];
            $ret = $walkFunction(new ObjectStoreObject($this, $object->name(), $meta));
            if ($ret === false)
                return false;
        }
        return true;
    }


    public function getMeta(string $objectId): array
    {
        try {
            $data = $this->bucket->object($objectId . ".__meta__.json")->downloadAsString();
            $data = json_decode($data, true);
            if ($data === null)
                throw new \InvalidArgumentException("Cannot json-decode meta data for object '$objectId': Invalid json data.");
        } catch (\Google\Cloud\Core\Exception\NotFoundException $e) {
            return [];
        }
    }

    public function setMeta(string $objectId, array $metadata)
    {
        $this->bucket->upload(json_encode($metadata), [
            "name" => $objectId . ".__meta__.json",
            'predefinedAcl' => 'projectprivate'
        ]);
    }


    /**
     * @param string $objectId
     * @throws NotFoundException
     */
    public function remove(string $objectId)
    {
        try {
            $this->bucket->object($objectId)->delete();
        } catch (\Google\Cloud\Core\Exception\NotFoundException $e) {
            throw new NotFoundException("Object '$objectId' not found for removing.");
        }
    }

    /**
     * @param string $objectId
     * @param string $newObjectId
     * @throws NotFoundException
     */
    public function rename(string $objectId, string $newObjectId)
    {
        try {
            $object = $this->bucket->object($objectId);
            $object->rename($newObjectId);
        } catch (\Google\Cloud\Core\Exception\NotFoundException $e) {
            throw new NotFoundException("Object '$objectId' not found for moving.");
        }
    }
}
