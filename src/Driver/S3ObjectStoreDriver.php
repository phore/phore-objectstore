<?php


namespace Phore\ObjectStore\Driver;


use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Phore\Core\Exception\NotFoundException;
use Psr\Http\Message\StreamInterface;

class S3ObjectStoreDriver implements ObjectStoreDriver
{

    /**
     * @var S3Client
     */
    private $client;

    private $bucket;

    public function __construct(array $credentials, string $bucket)
    {
        $this->client = new S3Client($credentials);
        $this->bucket = $bucket;
    }


    public function has(string $objectId): bool
    {
        return $this->client->getObject();
    }

    public function put(string $objectId, $content, array $metadata = null)
    {
        $this->client->putObject([
            "Bucket" => $this->bucket,
            "Key" => $objectId,
            "Body" => $content
        ]);
    }

    public function putStream(string $objectId, $resource, array $metadata = null)
    {
        // TODO: Implement putStream() method.
    }

    public function get(string $objectId, array &$meta = null): string
    {
        try {
            $result = $this->client->getObject([
                "Bucket" => $this->bucket,
                "Key" => $objectId
            ]);
            return $result["Body"];
        } catch (S3Exception $e) {
            switch ($e->getAwsErrorCode()) {
                case "NoSuchKey":
                    throw new NotFoundException("ObjectID '$objectId': Key not found.", 404, $e);
            }
            throw new \Exception("S3 Code {$e->getAwsErrorCode()} Storage exception: {$e->getMessage()}", $e->getCode(), $e);
        }

    }

    public function getStream(string $objectId, array &$meta = null): StreamInterface
    {
        // TODO: Implement getStream() method.
    }

    public function remove(string $objectId)
    {
        $this->client->deleteObject([
            "Bucket" => $this->bucket,
            "Key" => $objectId
        ]);
    }

    public function rename(string $objectId, string $newObjectId)
    {

    }

    public function append(string $objectId, string $data)
    {
        // TODO: Implement append() method.
    }

    public function getMeta(string $objectId): array
    {
        // TODO: Implement getMeta() method.
    }

    public function setMeta(string $objectId, array $metadata)
    {
        // TODO: Implement setMeta() method.
    }

    public function walk(callable $walkFunction): bool
    {
        // TODO: Implement walk() method.
    }

    public function list(string $prefix = null): array
    {
        // TODO: Implement list() method.
    }
}
