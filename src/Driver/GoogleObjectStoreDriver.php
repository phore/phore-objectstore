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
use Google\Cloud\Core\Exception\FailedPreconditionException;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Core\Exception\NotFoundException;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use InvalidArgumentException;
use Phore\ObjectStore\Encryption\ObjectStoreEncryption;
use Phore\ObjectStore\Encryption\PassThruNoEncryption;
use Phore\ObjectStore\GenerationMismatchException;
use Phore\ObjectStore\Type\ObjectStoreObject;
use Psr\Http\Message\StreamInterface;

/**
 * Class GoogleObjectStoreDriver
 * @package Phore\ObjectStore\Driver
 * @implements ObjectStoreDriver
 */
class GoogleObjectStoreDriver implements ObjectStoreDriver
{

    /**
     * @var Bucket
     */
    private $bucket;


    private $putOpts = [];

    /**
     * @var ObjectStoreEncryption
     */
    private $encryption;

    /**
     * GoogleObjectStoreDriver constructor.
     *
     * To Put Public files set $putOpts to ["predefinedAcl"=>"publicRead"]
     *
     * @param string|array $keyFile
     * @param string $bucketName
     */
    public function __construct($keyFile, string $bucketName, array $putOpts = ["predefinedAcl"=>"projectprivate"])
    {
        $this->putOpts = $putOpts;
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
        $this->encryption = $this->encryption ?? new PassThruNoEncryption();
    }

    public function setEncryption(ObjectStoreEncryption $encryption)
    {
        $this->encryption = $encryption;
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
        $opts = $this->putOpts;
        $opts["name"] = $objectId;

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
     * @param bool $validateGeneration Update only if generation matches (useful for optimistic locking)
     * @return mixed|void
     */
    public function put(string $objectId, $content, array $metadata = null, bool $validateGeneration = false)
    {
        $opts = $this->_getPutOpts($objectId, $metadata);
        if ($validateGeneration && $metadata !== null) {
            $opts['ifGenerationMatch'] = $metadata['generation'];
        }
        try {
            $this->bucket->upload($this->encryption->encrypt($content), $opts);
        } catch (FailedPreconditionException $e) {
            throw new GenerationMismatchException($e->getMessage(), $e->getCode(), $e);
        }

    }

    /**
     * @param string $objectId
     * @param $resource
     * @param array|null $metadata
     * @return mixed|void
     */
    public function putStream(string $objectId, $resource, array $metadata = null)
    {
        if ( ! ($this->encryption instanceof PassThruNoEncryption)) {
            throw new \InvalidArgumentException("Cannot put stream with encryption enabled.");
        }
        $opts = $this->_getPutOpts($objectId, $metadata);
        $this->bucket->upload($resource, $opts);
    }

    /**
     * @param string $objectId
     * @param array|null $meta
     * @return StreamInterface
     * @throws Exception
     * @throws \Phore\Core\Exception\NotFoundException
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
                return $this->encryption->decrypt($data);
            } catch (NotFoundException $e) {
                throw new \Phore\Core\Exception\NotFoundException($e->getMessage(), $e->getCode(), $e);
            } catch (Exception $e) {
                if ($i > 2) {
                    throw $e;
                }
                usleep(random_int(10, 1000)); // On error - wait a period and try again
                continue;
            }
        }
        throw new GoogleException('it was not possible to create the GoogleBucket', 500);
    }

    /**
     * @param string $objectId
     * @param array|null $meta
     * @return StreamInterface
     * @throws \Phore\Core\Exception\NotFoundException
     */
    public function getStream(string $objectId, array &$meta = null): StreamInterface
    {
        if ( ! ($this->encryption instanceof PassThruNoEncryption)) {
            throw new \InvalidArgumentException("Cannot put stream with encryption enabled.");
        }
        try {
            $object = $this->bucket->object($objectId);
            $stream = $object->downloadAsStream();

            $info = $object->info();
            if (isset ($info['metadata'])) {
                $meta = $info['metadata'];
            }
            return $stream;
        } catch (NotFoundException $e) {
            throw new \Phore\Core\Exception\NotFoundException($e->getMessage(), $e->getCode(), $e);
        }
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
     * @return void
     */
    public function setMeta(string $objectId, array $metadata): void
    {
        $this->bucket->object($objectId)->update(['metadata' => $metadata]);
    }

    /**
     * @param string $objectId
     * @throws \Phore\Core\Exception\NotFoundException
     */
    public function remove(string $objectId): void
    {
        try {
            $this->bucket->object($objectId)->delete();
        } catch (NotFoundException $e) {
            throw new \Phore\Core\Exception\NotFoundException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string $objectId
     * @param string $newObjectId
     * @throws \Phore\Core\Exception\NotFoundException
     */
    public function rename(string $objectId, string $newObjectId): void
    {
        try {
            $object = $this->bucket->object($objectId);
            $object->rename($newObjectId);
        } catch (NotFoundException $e) {
            throw new \Phore\Core\Exception\NotFoundException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string $objectId
     * @param string $data
     * @return mixed
     */
    public function append(string $objectId, string $data)
    {
        if ( ! ($this->encryption instanceof PassThruNoEncryption)) {
            throw new \InvalidArgumentException("Cannot put stream with encryption enabled.");
        }
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
     * list all objects in the bucket/container.
     *
     * Example:
     * ```
     * // Get all objects beginning with the prefix 'test'
     * $list = $driver->list('test');
     *
     * Result object has following structure:
     *  Array
     *  (
     *      [0] => Array
     *             (
     *                  [blobName] => test.dat
     *                  [blobUrl] => https://storage.googleapis.com/phore-test2/test.dat
     *             )
     *      [1] => Array ....
     * ```
     * @param string|null $prefix [optional]
     *     Configuration options.
     * @type string $prefix Result will contain only objects whose names, contains the prefix
     *
     * @type null $prefix Result contains all objects in container
     *
     * @return array returns an empty array if no data is available
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
