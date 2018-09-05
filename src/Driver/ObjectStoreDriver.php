<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 20.08.18
 * Time: 13:02
 */

namespace Phore\ObjectStore\Driver;


use Phore\ObjectStore\ObjectNotFoundException;
use Psr\Http\Message\StreamInterface;

interface ObjectStoreDriver
{
    public function has(string $objectId) : bool;
    public function put(string $objectId, $content, array $metadata=null);
    public function putStream(string $objectId, $ressource, array $metadata=null);

    /**
     * @param string $objectId
     * @return StreamInterface
     * @throws \Phore\ObjectStore\ObjectNotFoundException
     */
    public function get(string $objectId, array &$meta=null) : string;
    
    /**
     * @param string $objectId
     * @return StreamInterface
     * @throws \Phore\ObjectStore\ObjectNotFoundException
     */
    public function getStream(string $objectId, array &$meta=null) : StreamInterface;


    /**
     * @param string $objectId
     * @throws ObjectNotFoundException
     */
    public function remove(string $objectId);

    /**
     * @param string $objectId
     * @param string $newObjectId
     * @throws ObjectNotFoundException
     */
    public function rename(string $objectId, string $newObjectId);

    public function walk(callable $walkFunction) : bool;
}
