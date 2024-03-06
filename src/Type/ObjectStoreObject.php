<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 05.09.18
 * Time: 09:51
 */

namespace Phore\ObjectStore\Type;


use Exception;
use Phore\Core\Exception\NotFoundException;
use Phore\ObjectStore\Driver\ObjectStoreDriver;
use Phore\ObjectStore\GenerationMismatchException;
use Psr\Http\Message\StreamInterface;

/**
 * Class ObjectStoreObject
 * @package Phore\ObjectStore\Type
 */
class ObjectStoreObject
{

    /**
     * @var ObjectStoreDriver
     */
    private $driver;
    /**
     * @var string
     */
    private $objectId;
    /**
     * @var array|null
     */
    private $metaData;

    /**
     * ObjectStoreObject constructor.
     * @param ObjectStoreDriver $driver
     * @param string $objectId
     * @param array|null $metadata
     */
    public function __construct(ObjectStoreDriver $driver, string $objectId, array $metadata = null)
    {
        $this->driver = $driver;
        $this->objectId = $objectId;
        $this->metaData = $metadata;
    }

    /**
     * @return string
     */
    public function getObjectId(): string
    {
        return $this->objectId;
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return $this->driver->has($this->objectId);
    }


    /**
     * @return string
     * @throws NotFoundException
     */
    public function get(): string
    {
        return $this->driver->get($this->objectId, $this->metaData);
    }

    /**
     * @return StreamInterface
     * @throws NotFoundException
     */
    public function getStream(): StreamInterface
    {
        return $this->driver->getStream($this->objectId, $this->metaData);
    }

    /**
     * @template T
     * @param class-string<T> $cast
     * @return array|T
     * @throws NotFoundException
     */
    public function getJson(string $cast = null): array|object
    {
        $data = $this->get();
        $ret = json_decode($data, true);
        if ($ret === null) {
            throw new \InvalidArgumentException("Cannot json-decode data from object '$this->objectId'");
        }
        if ($cast !== null) {
            $ret = phore_hydrate($ret, $cast);
        }
        return $ret;
    }

    /**
     * @return array
     * @throws NotFoundException
     */
    public function getYaml(): array
    {
        $data = $this->get();
        $ret = yaml_parse($data);
        if ($ret === false) {
            throw new \InvalidArgumentException("Cannot yaml_parse data from object '$this->objectId'");
        }
        return $ret;
    }


    /**
     * @param string $data
     * @param bool $validateGeneration  Put only if the generation (version) is the same (for optimistic locking)
     * @return $this
     * @throws GenerationMismatchException
     */
    public function put(string $data, bool $validateGeneration = false): self
    {
        $this->driver->put($this->objectId, $data, $this->metaData, $validateGeneration);
        return $this;
    }

    /**
     * @param array $data
     * @param bool $validateGeneration  Put only if the generation (version) is the same (for optimistic locking)
     * @return $this
     * @throws GenerationMismatchException
     */
    public function putJson(array|object $data, bool $validateGeneration = false): self
    {
        if (is_object($data))
            $data = (array)$data;
        $this->driver->put($this->objectId, phore_json_encode($data), $this->metaData, $validateGeneration);
        return $this;
    }

    /**
     * @param $resource
     * @return $this
     */
    public function putStream($resource, bool $validateGeneration = false): self
    {
        $this->driver->putStream($this->objectId, $resource, $this->metaData, $validateGeneration);
        return $this;
    }

    /**
     * @param null $key
     * @param null $default
     * @return array|mixed|null
     * @throws Exception
     */
    public function getMeta($key = null, $default = null)
    {
        if (!$this->metaData) {
            $this->metaData = $this->driver->getMeta($this->objectId);
        }
        $data = $this->metaData;
        if ($key !== null) {
            return phore_pluck($key, $data, $default);
        }
        return $data;
    }

    /**
     * @param array $metaData
     * @return $this
     */
    public function setMeta(array $metaData): self
    {
        $this->metaData = $metaData;
        $this->driver->setMeta($this->objectId, $metaData);
        return $this;
    }

    /**
     * @param array $metaData
     * @return $this
     */
    public function withMeta(array $metaData): self
    {
        $this->metaData = $metaData;
        return $this;
    }


    /**
     * @param string $newObjectName
     * @return ObjectStoreObject
     * @throws NotFoundException
     */
    public function rename(string $newObjectName): ObjectStoreObject
    {
        $this->driver->rename($this->objectId, $newObjectName);
        return new ObjectStoreObject($this->driver, $newObjectName);
    }

    /**
     * @throws NotFoundException
     */
    public function remove(): void
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
    public function append(string $data): void
    {
        $this->driver->append($this->objectId, $data);
    }


}
