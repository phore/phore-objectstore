<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 05.09.18
 * Time: 10:43
 */

namespace Phore\ObjectStore\Driver;


use Phore\Core\Exception\NotFoundException;
use Phore\FileSystem\PhoreFile;
use Phore\ObjectStore\Type\ObjectStoreObject;
use Psr\Http\Message\StreamInterface;

class FileSystemObjectStoreDriver implements ObjectStoreDriver
{

    /**
     * @var \Phore\FileSystem\PhoreDirectory
     */
    private $rootDir;

    public function __construct(string $rootDir)
    {
        $this->rootDir = phore_dir($rootDir);
    }


    const META_SUFFIX = ".~META~";

    public function has(string $objectId): bool
    {
        return $this->rootDir->withSubPath($objectId)->isFile();
    }

    public function put(string $objectId, $content, array $metadata = null)
    {
        $this->rootDir->withSubPath($objectId)->asFile()->set_contents($content);
        if ($metadata !== null)
            $this->rootDir->withSubPath($objectId . self::META_SUFFIX)->asFile()->set_json($metadata);
    }

    public function putStream(string $objectId, $ressource, array $metadata = null)
    {
        // TODO: Implement putStream() method.
    }

    /**
     * @param string $objectId
     * @return StreamInterface
     * @throws NotFoundException
     */
    public function get(string $objectId, array &$meta = null): string
    {
        $metaFile = $this->rootDir->withSubPath($objectId . self::META_SUFFIX)->asFile();
        if ($metaFile->isFile())
            $meta = $metaFile->get_json();

        return $this->rootDir->withSubPath($objectId)->asFile()->get_contents();
    }

    /**
     * @param string $objectId
     * @return StreamInterface
     * @throws  NotFoundException
     */
    public function getStream(string $objectId, array &$meta = null): StreamInterface
    {
        // TODO: Implement getStream() method.
    }

    /**
     * @param string $objectId
     * @throws NotFoundException
     */
    public function remove(string $objectId)
    {
        $metaFile = $this->rootDir->withSubPath($objectId . self::META_SUFFIX)->asFile();
        if ($metaFile->isFile())
            $metaFile->unlink();

        $this->rootDir->withSubPath($objectId)->asFile()->unlink();
    }

    /**
     * @param string $objectId
     * @param string $newObjectId
     * @throws NotFoundException
     */
    public function rename(string $objectId, string $newObjectId)
    {
        // TODO: Implement rename() method.
    }

    public function walk(callable $walkFunction): bool
    {
        $this->rootDir->walkR(function(PhoreFile $file) use ($walkFunction) {
            if (endsWith($file, self::META_SUFFIX))
                return true; // Ignore file
            $metaFile = phore_file($file . self::META_SUFFIX);
            $meta = [];
            if ($metaFile->isFile())
                $meta = $metaFile->get_json();
            if (false === $walkFunction(new ObjectStoreObject($this, substr($file, strlen($this->rootDir)), $meta)))
                return false;
            
        });
    }
}
