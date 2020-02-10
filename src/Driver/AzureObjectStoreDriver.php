<?php
/**
 * Created by IntelliJ IDEA.
 * User: oem
 * Date: 28.01.20
 * Time: 14:14
 */

namespace Phore\ObjectStore\Driver;


use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\AppendBlockOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateBlobBlockOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateBlobOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Common\Models\ServiceOptions;
use Phore\Core\Exception\NotFoundException;
use Psr\Http\Message\StreamInterface;

class AzureObjectStoreDriver implements ObjectStoreDriver
{
    /**
     * @var \MicrosoftAzure\Storage\Blob\BlobRestProxy
     */
    private $blobClient;
    private $containerName;

    public function __construct(string $accountName, string $accountKey,string $containerName)
    {
        $connectionString = "DefaultEndpointsProtocol=https;AccountName=$accountName;AccountKey=$accountKey";
        $this->blobClient = BlobRestProxy::createBlobService($connectionString);
        $this->containerName = $containerName;
    }

    public function has(string $objectId): bool
    {
        try {
            $this->blobClient->getBlobMetadata($this->containerName, $objectId);
            return true;
        } catch (ServiceException $e) {
            if($e->getCode() === 404 && strpos($e->getMessage(), "The specified blob does not exist.")) {
                return false;
            }
            throw $e;
        }
    }

    public function put(string $objectId, $content, array $metadata = null)
    {
        $options = null;
        if( $metadata !== null) {
            $options = new CreateBlockBlobOptions();
            $options->setMetadata($metadata);
        }
        $this->blobClient->createBlockBlob($this->containerName, $objectId , $content, $options);
    }

    public function putStream(string $objectId, $ressource, array $metadata = null)
    {
        $options = null;
        if( $metadata !== null) {
            $options = new CreateBlockBlobOptions();
            $options->setMetadata($metadata);
        }
        $this->blobClient->createBlockBlob($this->containerName, $objectId , $ressource, $options);
    }

    public function get(string $objectId, array &$meta = null): string
    {
        try {
            $blob = $this->blobClient->getBlob($this->containerName, $objectId);
            $meta = $blob->getMetadata();
        } catch (\Exception $e) {
            if($e->getCode() === 404) {
                throw new NotFoundException($e->getMessage(), $e->getCode(), $e);
            }
            throw $e;
        }
        return stream_get_contents($blob->getContentStream());
    }

    public function getStream(string $objectId, array &$meta = null): StreamInterface
    {
        // TODO: Implement getStream() method.
    }

    public function remove(string $objectId)
    {
        $this->blobClient->deleteBlob($this->containerName, $objectId);
    }

    public function rename(string $objectId, string $newObjectId)
    {
        $this->blobClient->copyBlob($this->containerName, $newObjectId, $this->containerName, $objectId);
        $this->blobClient->deleteBlob($this->containerName, $objectId);
    }

    public function append(string $objectId, string $data)
    {
        $leaseID = $this->blobClient->acquireLease($this->containerName, $objectId, null, 60)->getLeaseId();
        $blobOptions = new CreateBlockBlobOptions();
        $blobOptions->setLeaseId($leaseID);
        $content = $this->get($objectId, $meta);
        $content .= $data;
        $blobOptions->setMetadata($meta);
        $this->blobClient->createBlockBlob($this->containerName, $objectId , $content, $blobOptions);
        $this->blobClient->releaseLease($this->containerName, $objectId, $leaseID);

    }

    public function getMeta(string $objectId): array
    {
        return $this->blobClient->getBlobMetadata($this->containerName, $objectId)->getMetadata();
    }

    public function setMeta(string $objectId, array $metadata)
    {
        $this->blobClient->setBlobMetadata($this->containerName, $objectId, $metadata);
    }

    public function walk(callable $walkFunction): bool
    {
        // TODO: Implement walk() method.
    }

    public function list() : array
    {
        $blobList = [];
        try {
            $blob_list = $this->blobClient->listBlobs($this->containerName);
            $blobs = $blob_list->getBlobs();
            foreach($blobs as $blob)
            {
                $blobList[] = ["blobName" => $blob->getName(), "blobUrl" => $blob->getUrl()];
            }
        } catch(ServiceException $e){
            throw $e;
        }
        return $blobList;
    }
}
