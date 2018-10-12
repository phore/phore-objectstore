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
        $file = $this->rootDir->withSubPath($objectId)->asFile();
        $dir = $file->getDirname()->asDirectory();
        if ( ! $dir->isDirectory())
            $file->getDirname()->asDirectory()->mkdir();
        $file->set_contents($content);
        if ($metadata !== null)
            $this->rootDir->withSubPath($objectId . self::META_SUFFIX)->asFile()->set_json($metadata);
    }

    public function putStream(string $objectId, $ressource, array $metadata = null)
    {
        throw new \InvalidArgumentException("Not implemented yet.");
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

        $file = $this->rootDir->withSubPath($objectId)->asFile();
        if ( ! $file->isFile())
            throw new NotFoundException("Object '$objectId' not existing.", 0 );
        return $file->get_contents();
    }

    /**
     * @param string $objectId
     * @return StreamInterface
     * @throws  NotFoundException
     */
    public function getStream(string $objectId, array &$meta = null): StreamInterface
    {
        throw new \InvalidArgumentException("Not implemented yet.");
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
        throw new \InvalidArgumentException("Not implemented yet.");
        // TODO: Implement rename() method.
    }

    public function walk(callable $walkFunction): bool
    {
        return $this->rootDir->walkR(function(PhoreFile $file) use ($walkFunction) {
            if (endsWith($file, self::META_SUFFIX))
                return true; // Ignore file
            $metaFile = phore_file($file . self::META_SUFFIX);
            $meta = [];
            if ($metaFile->isFile())
                $meta = $metaFile->get_json();
            if (false === $walkFunction(new ObjectStoreObject($this, substr($file, strlen($this->rootDir)+1), $meta)))
                return false;
            
        });
    }

    /**
     * @param string $objectId
     * @param string $data
     * @return mixed
     * @throws NotFoundException
     */
    public function append(string $objectId, string $appendData)
    {
        $targetFile = $this->rootDir->withSubPath($objectId)->asFile();

        if ($targetFile->exists())
            $targetFile->append_content($appendData);
        else
            $targetFile->set_contents($appendData);
    }
}
