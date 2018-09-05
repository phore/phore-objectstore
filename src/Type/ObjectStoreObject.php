<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 05.09.18
 * Time: 09:51
 */

namespace Phore\ObjectStore\Type;


use Phore\ObjectStore\Driver\ObjectStoreDriver;
use Phore\ObjectStore\ObjectNotFoundException;
use Psr\Http\Message\StreamInterface;

class ObjectStoreObject
{

    /**
     * @var ObjectStoreDriver
     */
    private $driver;
    private $objectId;

    private $metaData;

    public function __construct(ObjectStoreDriver $driver, string $objectId, array $metadata=null)
    {
        $this->driver = $driver;
        $this->objectId = $objectId;
        $this->metaData = $metadata;
    }

    public function exists() : bool
    {
        return $this->driver->has($this->objectId);
    }


    /**
     * @param string $objectId
     * @return string
     * @throws ObjectNotFoundException
     */
    public function get() : string
    {
        return $this->driver->get($this->objectId, $this->metaData);
    }

    /**
     * @param string $objectId
     * @return StreamInterface
     * @throws ObjectNotFoundException
     */
    public function getStream() : StreamInterface
    {
        return $this->driver->getStream($this->objectId, $this->metaData);
    }

    /**
     * @param string $objectId
     * @return array
     * @throws ObjectNotFoundException
     */
    public function getJson (string $objectId) : array
    {
        $data = $this->get();
        $ret = json_decode($data, true);
        if ($ret === null)
            throw new \InvalidArgumentException("Cannot json-decode data from object '$objectId'");
        return $ret;
    }

    public function put (string $data) : self
    {
        $this->driver->put($this->objectId, $data, $this->metaData);
        return $this;
    }

    public function putJson (array $data) : self
    {
        $this->driver->put($this->objectId, json_encode($data), $this->metaData);
        return $this;
    }

    public function putStream ($ressource) : self
    {
        $this->driver->putStream($this->objectId, $ressource, $this->metaData);
        return $this;
    }

    public function getMeta() : array
    {
        if ( ! $this->metaData)
            $this->metaData = $this->driver->getMeta($this->objectId);
        return $this->metaData;
    }

    public function setMeta(array $metaData) : self
    {
        $this->metaData = $metaData;
        $this->driver->setMeta($this->objectId, $metaData);
        return $this;
    }

    public function withMeta(array $metaData) : self
    {
        $this->metaData = $metaData;
        return $this;
    }


    /**
     * @param string $newObjectName
     * @return ObjectStoreObject
     * @throws ObjectNotFoundException
     */
    public function rename(string $newObjectName) : ObjectStoreObject
    {
        $this->driver->rename($this->objectId, $newObjectName);
        return new ObjectStoreObject($this->driver, $newObjectName);
    }

    /**
     * @throws ObjectNotFoundException
     */
    public function remove()
    {
        $this->driver->remove($this->objectId);
    }




}