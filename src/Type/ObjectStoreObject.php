<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 05.09.18
 * Time: 09:51
 */

namespace Phore\ObjectStore\Type;


use Phore\Core\Exception\NotFoundException;
use Phore\ObjectStore\Driver\ObjectStoreDriver;

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

    public function getObjectId() : string 
    {
        return $this->objectId;
    }
    
    public function exists() : bool
    {
        return $this->driver->has($this->objectId);
    }


    /**
     * @param string $objectId
     * @return string
     * @throws NotFoundException
     */
    public function get() : string
    {
        return $this->driver->get($this->objectId, $this->metaData);
    }

    /**
     * @param string $objectId
     * @return StreamInterface
     * @throws NotFoundException
     */
    public function getStream() : StreamInterface
    {
        return $this->driver->getStream($this->objectId, $this->metaData);
    }

    /**
     * @param string $objectId
     * @return array
     * @throws NotFoundException
     */
    public function getJson () : array
    {
        $data = $this->get();
        $ret = json_decode($data, true);
        if ($ret === null)
            throw new \InvalidArgumentException("Cannot json-decode data from object '$this->objectId'");
        return $ret;
    }

    /**
     * @return array
     * @throws NotFoundException
     */
    public function getYaml() : array
    {
        $data = $this->get();
        $ret = yaml_parse($data);
        if ($ret === false)
            throw new \InvalidArgumentException("Cannot yaml_parse data from object '$this->objectId'");
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

    public function getMeta($key=null, $default=null) : array
    {
        if ( ! $this->metaData)
            $this->metaData = $this->driver->getMeta($this->objectId);
        $data = $this->metaData;
        if ($key !== null)
            return phore_pluck($key, $data, $default);
        return $data;
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
     * @throws NotFoundException
     */
    public function rename(string $newObjectName) : ObjectStoreObject
    {
        $this->driver->rename($this->objectId, $newObjectName);
        return new ObjectStoreObject($this->driver, $newObjectName);
    }

    /**
     * @throws NotFoundException
     */
    public function remove()
    {
        $this->driver->remove($this->objectId);
    }


    /**
     * Append data to the object (atomic)
     *
     * If object does not exist: Create a new one.
     *
     * @param string $data
     */
    public function append(string $data)
    {
        $this->driver->append($this->objectId, $data);
    }



}
