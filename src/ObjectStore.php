<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 20.08.18
 * Time: 13:05
 */

namespace Phore\ObjectStore;


use Phore\ObjectStore\Driver\ObjectStoreDriver;
use Phore\ObjectStore\Type\ObjectStoreObject;


class ObjectStore
{

    /**
     * @var ObjectStoreDriver
     */
    private $driver;


    public function __construct(ObjectStoreDriver $objectStoreDriver)
    {
        $this->driver = $objectStoreDriver;
    }

    public function getDriver() : ObjectStoreDriver
    {
        return $this->driver;
    }

    public function has(string $objectId) : bool
    {
        return $this->driver->has($objectId);
    }

    public function object(string $objectId) : ObjectStoreObject
    {
        return new ObjectStoreObject($this->driver, $objectId);
    }

    public function walk(callable $fn) : bool
    {
        return $this->driver->walk($fn);
    }

}
