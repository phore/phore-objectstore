<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 20.08.18
 * Time: 13:00
 */

namespace Phore\ObjectStore\Driver;


use DateTime;
use Exception;
use Google\Cloud\Core\Exception\NotFoundException;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;

use InvalidArgumentException;
use Phore\ObjectStore\Type\ObjectStoreObject;
use Psr\Http\Message\StreamInterface;

class GoogleObjectStoreDriver implements ObjectStoreDriver
{

    /**
     * @var Bucket
     */
    private $bucket;


    /**
     * GoogleObjectStoreDriver constructor.
     * @param string|array $keyFile
     * @param string $bucketName
     */
    public function __construct($keyFile, string $bucketName)
    {
        if (!class_exists(StorageClient::class)) {
            throw new InvalidArgumentException("Package google/cloud-storage is missing. Install it by running 'composer install google/cloud-storage'");
        }
        $options = [];
        if (is_array($keyFile)) {
            $options = ['keyFile' => $keyFile];
        } else if (is_string($keyFile)) {
            $options = ['keyFilePath' => $keyFile];
        }
        $storage = new StorageClient($options);

        $this->bucket = $storage->bucket($bucketName);
    }


    /**
     * @param string $objectId
     * @return bool
     */
    public function has(string $objectId): bool
    {
        return $this->bucket->object($objectId)->exists();
    }

    /**
     * @param $objectId
     * @param array|null $metadata
     * @return array
     */
    private function _getPutOpts($objectId, array $metadata = null): array
    {
        $opts = [
            'name' => $objectId,
            'predefinedAcl' => 'projectprivate'
        ];
        if ($metadata !== null) {
            $opts['metadata'] = [
                'metadata' => $metadata
            ];
        }
        return $opts;
    }

    /**
     * @param string $objectId
     * @param $content
     * @param array|null $metadata
     * @return mixed|void
     */
    public function put(string $objectId, $content, array $metadata = null)
    {
        $opts = $this->_getPutOpts($objectId, $metadata);
        $this->bucket->upload($content, $opts);
    }

    /**
     * @param string $objectId
     * @param $resource
     * @param array|null $metadata
     * @return mixed|void
     */
    public function putStream(string $objectId, $resource, array $metadata = null)
    {
        $opts = $this->_getPutOpts($objectId, $metadata);
        $this->bucket->upload($resource, $opts);
    }

    /**
     * @param string $objectId
     * @param array|null $meta
     * @return StreamInterface
     * @throws Exception
     */
    public function get(string $objectId, array &$meta = null): string
    {
        for ($i = 0; $i < 10; $i++) {
            try {
                $object = $this->bucket->object($objectId);
                $data = $object->downloadAsString();
                $info = $object->info();
                if (isset ($info['metadata'])) {
                    $meta = $info['metadata'];
                }
                return $data;
            } catch (Exception $e) {
                if ($i > 2) {
                    throw $e;
                }
                usleep(random_int(10, 1000)); // On error - wait a period and try again
                continue;
            }
        }
    }

    /**
     * @param string $objectId
     * @param array|null $meta
     * @return StreamInterface
     */
    public function getStream(string $objectId, array &$meta = null): StreamInterface
    {
        $object = $this->bucket->object($objectId);
        $stream = $object->downloadAsStream();

        $info = $object->info();
        if (isset ($info['metadata'])) {
            $meta = $info['metadata'];
        }
        return $stream;
    }

    /**
     * @return Bucket
     */
    public function getBucket(): Bucket
    {
        return $this->bucket;
    }

    /**
     * @param callable $walkFunction
     * @param string|null $filter
     * @return bool
     */
    public function walk(callable $walkFunction, string $filter = null): bool
    {
        foreach ($this->bucket->objects() as $object) {
            $meta = null;
            $info = $object->info();
            if (isset($info['metadata'])) {
                $meta = $info['metadata'];
            }
            $ret = $walkFunction(new ObjectStoreObject($this, $object->name(), $meta));
            if ($ret === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $objectId
     * @return array
     */
    public function getMeta(string $objectId): array
    {
        return $this->bucket->object($objectId)->info();
    }

    /**
     * @param string $objectId
     * @param array $metadata
     * @return mixed|void
     */
    public function setMeta(string $objectId, array $metadata)
    {
        $this->bucket->object($objectId)->update(['metadata' => $metadata]);
    }

    /**
     * @param string $objectId
     */
    public function remove(string $objectId): void
    {
        $this->bucket->object($objectId)->delete();
    }

    /**
     * @param string $objectId
     * @param string $newObjectId
     */
    public function rename(string $objectId, string $newObjectId): void
    {
        $object = $this->bucket->object($objectId);
        $object->rename($newObjectId);
    }

    /**
     * @param string $objectId
     * @param string $data
     * @return mixed
     */
    public function append(string $objectId, string $data)
    {
        $ext = pathinfo($objectId)['extension'];
        if ($ext !== '') {
            $ext = ".$ext";
        }
        $tmpId = '/tmp/' . time() . '-' . sha1(microtime(true) . uniqid('', true)) . $ext;

        $origObj = $this->bucket->object($objectId);
        if (!$origObj->exists()) {
            // Create new Object
            $this->put($objectId, $data);
            return true;
        }
        $this->put($tmpId, $data);
        $this->bucket->compose([$origObj, $tmpId], $objectId);
        $this->bucket->object($tmpId)->delete();
        return true;
    }

    /**
     * @param string|null $prefix
     * @return array
     */
    public function list(string $prefix = null): array
    {
        $options = [];
        if ($prefix !== null) {
            $options = ['prefix' => $prefix];
        }
        $objectList = [];
        foreach ($this->bucket->objects($options) as $object) {
            $objectList[] = ['blobName' => $object->name(), 'blobUrl' => explode('?', $object->signedUrl(
                new DateTime('15 min'),
                [
                    'version' => 'v4',
                ]
            ))[0]];
        }
        return $objectList;
    }
}
