<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 20.08.18
 * Time: 13:02
 */

namespace Phore\ObjectStore\Driver;


use Phore\Core\Exception\NotFoundException;
use Psr\Http\Message\StreamInterface;

/**
 * Interface ObjectStoreDriver
 * @package Phore\ObjectStore\Driver
 */
interface ObjectStoreDriver
{
    /**
     * @param string $objectId
     * @return bool
     */
    public function has(string $objectId): bool;

    /**
     * @param string $objectId
     * @param $content
     * @param array|null $metadata
     * @return mixed
     */
    public function put(string $objectId, $content, array $metadata = null);

    /**
     * @param string $objectId
     * @param $resource
     * @param array|null $metadata
     * @return mixed
     */
    public function putStream(string $objectId, $resource, array $metadata = null);

    /**
     * @param string $objectId
     * @param array|null $meta
     * @return StreamInterface
     */
    public function get(string $objectId, array &$meta = null): string;

    /**
     * @param string $objectId
     * @param array|null $meta
     * @return StreamInterface
     */
    public function getStream(string $objectId, array &$meta = null): StreamInterface;


    /**
     * @param string $objectId
     * @throws NotFoundException
     */
    public function remove(string $objectId);

    /**
     * @param string $objectId
     * @param string $newObjectId
     * @throws NotFoundException
     */
    public function rename(string $objectId, string $newObjectId);

    /**
     * If object exists:
     * -> Append data
     *
     * If not:
     * -> Write data to new object file
     *
     * @param string $objectId
     * @param string $data
     * @return mixed
     */
    public function append(string $objectId, string $data);

    /**
     *
     *
     * @param string $objectId
     * @return array        Empty array if object not found
     */
    public function getMeta(string $objectId): array;

    /**
     * @param string $objectId
     * @param array $metadata
     * @return mixed
     */
    public function setMeta(string $objectId, array $metadata);


    /**
     * @param callable $walkFunction
     * @return bool
     */
    public function walk(callable $walkFunction): bool;

    /**
     * @param string $prefix
     * @return array
     */
    public function list(string $prefix): array;

}
