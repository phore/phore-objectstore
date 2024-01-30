<?php


namespace Phore\ObjectStore\Driver;


use Aws\Credentials\CredentialProvider;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Phore\Core\Exception\NotFoundException;
use Phore\ObjectStore\Encryption\ObjectStoreEncryption;
use Psr\Http\Message\StreamInterface;

class S3ObjectStoreDriver implements ObjectStoreDriver
{

    /**
     * @var S3Client
     */
    private $client;

    private $bucket;

    public function __construct(string $region, string $bucket, string $account=null, string $secretkey=null)
    {
        if ( ! class_exists(S3Client::class))
            throw new \InvalidArgumentException("Package 'aws/aws-sdk-php' is required to use s3nd");

        $config = [
            "version" => "latest",
            "region" => $region
        ];
        if ($account !== null) {
            // Use credentials from uri
            $config["credentials"] = [
                "key" => $account,
                "secret" => $secretkey
            ];
        } else {
            // Use default provider
            $config["credentials"] = CredentialProvider::defaultProvider();
        }

        $this->client = new S3Client($config);
        $this->bucket = $bucket;
    }

    public function setEncryption(ObjectStoreEncryption $encryption)
    {
        throw new \InvalidArgumentException("Encryption not supported in S3 implementation");
    }
    public function has(string $objectId): bool
    {
        try {
            $this->get($objectId);
            return true;
        } catch (NotFoundException $e) {
            return false;
        }
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
        $this->client->copyObject([
            "Bucket" => $this->bucket,
            "Key" => $newObjectId,
            "CopySource" => $objectId
        ]);
        $this->remove($objectId);

    }

    public function append(string $objectId, string $data)
    {
        try {
            $content = $this->get($objectId);
        } catch (NotFoundException $e) {
            $content = "";
        }
        $content .= $data;
        $this->put($objectId, $content);
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
        throw new \InvalidArgumentException("Umimplemented walk() method in S3 implementation");
        // TODO: Implement walk() method.
    }

    public function list(string $prefix = null): array
    {
        $opts = ["Bucket" => $this->bucket];
        if ($prefix !== null)
            $opts["Prefix"] = $prefix;

        $results = $this->client->getPaginator("ListObjects", $opts);
        $res = [];
        foreach ($results as $result) {
            foreach ($result["Contents"] as $obj) {
                $res[] = $obj["Key"];
            }
        }
        return $res;
    }
}
