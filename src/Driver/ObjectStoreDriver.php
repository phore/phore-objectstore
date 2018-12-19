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

interface ObjectStoreDriver
{
    public function has(string $objectId) : bool;
    public function put(string $objectId, $content, array $metadata=null);
    public function putStream(string $objectId, $ressource, array $metadata=null);

    /**
     * @param string $objectId
     * @return StreamInterface
     * @throws NotFoundException
     */
    public function get(string $objectId, array &$meta=null) : string;
    
    /**
     * @param string $objectId
     * @return StreamInterface
     * @throws  NotFoundException
     */
    public function getStream(string $objectId, array &$meta=null) : StreamInterface;


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


    public function walk(callable $walkFunction) : bool;
}
