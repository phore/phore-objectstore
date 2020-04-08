<?php
/**
 * Created by IntelliJ IDEA.
 * User: jan
 * Date: 28.01.20
 * Time: 14:14
 */

namespace Phore\ObjectStore\Driver;


use Exception;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use Phore\Core\Exception\NotFoundException;
use Psr\Http\Message\StreamInterface;

class AzureObjectStoreDriver implements ObjectStoreDriver
{
    /**
     * @var BlobRestProxy
     */
    private $blobClient;
    /**
     * @var string
     */
    private $containerName;

    /**
     * AzureObjectStoreDriver constructor.
     * @param string $accountName
     * @param string $accountKey
     * @param string $containerName
     */
    public function __construct(string $accountName, string $accountKey, string $containerName)
    {
        $connectionString = "DefaultEndpointsProtocol=https;AccountName=$accountName;AccountKey=$accountKey";
        $this->blobClient = BlobRestProxy::createBlobService($connectionString);
        $this->containerName = $containerName;
    }

    /**
     * @param string $objectId
     * @return bool
     */
    public function has(string $objectId): bool
    {
        try {
            $this->blobClient->getBlobMetadata($this->containerName, $objectId);
            return true;
        } catch (ServiceException $e) {
            if ($e->getCode() === 404 && strpos($e->getMessage(), 'The specified blob does not exist.')) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * @param string $objectId
     * @param $content
     * @param array|null $metadata
     */
    public function put(string $objectId, $content, array $metadata = null): void
    {
        $options = null;
        if ($metadata !== null) {
            $options = new CreateBlockBlobOptions();
            $options->setMetadata($metadata);
        }
        $this->blobClient->createBlockBlob($this->containerName, $objectId, $content, $options);
    }

    /**
     * @param string $objectId
     * @param $resource
     * @param array|null $metadata
     */
    public function putStream(string $objectId, $resource, array $metadata = null): void
    {
        $options = null;
        if ($metadata !== null) {
            $options = new CreateBlockBlobOptions();
            $options->setMetadata($metadata);
        }
        $this->blobClient->createBlockBlob($this->containerName, $objectId, $resource, $options);
    }

    /**
     * @param string $objectId
     * @param array|null $meta
     * @return string
     * @throws NotFoundException
     * @throws Exception
     */
    public function get(string $objectId, array &$meta = null): string
    {
        try {
            $blob = $this->blobClient->getBlob($this->containerName, $objectId);
            $meta = $blob->getMetadata();
        } catch (Exception $e) {
            if ($e->getCode() === 404) {
                throw new NotFoundException($e->getMessage(), $e->getCode(), $e);
            }
            throw $e;
        }
        return stream_get_contents($blob->getContentStream());
    }

    /**
     * Not Implemented Yet
     * @param string $objectId
     * @param array|null $meta
     * @return StreamInterface
     * @todo needs to be implemented like in the GoogleObjectStoreDriver
     */
    public function getStream(string $objectId, array &$meta = null): StreamInterface
    {
        // TODO: Implement getStream() method.
    }

    /**
     * @param string $objectId
     */
    public function remove(string $objectId): void
    {
        $this->blobClient->deleteBlob($this->containerName, $objectId);
    }

    /**
     * @param string $objectId
     * @param string $newObjectId
     */
    public function rename(string $objectId, string $newObjectId): void
    {
        $this->blobClient->copyBlob($this->containerName, $newObjectId, $this->containerName, $objectId);
        $this->blobClient->deleteBlob($this->containerName, $objectId);
    }

    /**
     * @param string $objectId
     * @param string $data
     * @return mixed|void
     * @throws NotFoundException
     */
    public function append(string $objectId, string $data)
    {
        $leaseID = $this->blobClient->acquireLease($this->containerName, $objectId, null, 60)->getLeaseId();
        $blobOptions = new CreateBlockBlobOptions();
        $blobOptions->setLeaseId($leaseID);
        $content = $this->get($objectId, $meta);
        $content .= $data;
        $blobOptions->setMetadata($meta);
        $this->blobClient->createBlockBlob($this->containerName, $objectId, $content, $blobOptions);
        $this->blobClient->releaseLease($this->containerName, $objectId, $leaseID);

    }

    /**
     * @param string $objectId
     * @return array
     */
    public function getMeta(string $objectId): array
    {
        return $this->blobClient->getBlobMetadata($this->containerName, $objectId)->getMetadata();
    }

    /**
     * @param string $objectId
     * @param array $metadata
     * @return mixed|void
     */
    public function setMeta(string $objectId, array $metadata)
    {
        $this->blobClient->setBlobMetadata($this->containerName, $objectId, $metadata);
    }

    /**
     * Not Implemented Yet
     * @param callable $walkFunction
     * @return bool
     * @todo needs to be implemented like in the GoogleObjectStoreDriver
     *
     */
    public function walk(callable $walkFunction): bool
    {
        // TODO: Implement walk() method.
        return true;
    }

    /**
     * @param null $prefix
     * @return array
     */
    public function list($prefix = null): array
    {
        $listBlobsOptions = new ListBlobsOptions();
        if ($prefix !== null) {
            $listBlobsOptions->setPrefix($prefix);
        }
        $blobList = [];
        do {
            try {
                $blob_list = $this->blobClient->listBlobs($this->containerName, $listBlobsOptions);
                $blobs = $blob_list->getBlobs();
                foreach ($blobs as $blob) {
                    $blobList[] = ['blobName' => $blob->getName(), 'blobUrl' => $blob->getUrl()];
                }
            } catch (ServiceException $e) {
                throw $e;
            }
            //$listBlobsOptions->setContinuationToken($blob_list->getContinuationToken());
        } while ($blob_list->getContinuationToken());
        return $blobList;
    }

}
