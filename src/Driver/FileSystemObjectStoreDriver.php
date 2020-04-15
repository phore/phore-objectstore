<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 05.09.18
 * Time: 10:43
 */

namespace Phore\ObjectStore\Driver;


use InvalidArgumentException;
use Phore\Core\Exception\NotFoundException;
use Phore\FileSystem\Exception\FileAccessException;
use Phore\FileSystem\Exception\FileNotFoundException;
use Phore\FileSystem\Exception\FileParsingException;
use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\Exception\PathOutOfBoundsException;
use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;
use Phore\ObjectStore\Type\ObjectStoreObject;
use Psr\Http\Message\StreamInterface;

class FileSystemObjectStoreDriver implements ObjectStoreDriver
{

    /**
     * @var PhoreDirectory
     */
    private $rootDir;

    public function __construct(string $rootDir)
    {
        if (!class_exists('\Phore\FileSystem\PhoreDirectory')) {
            throw new InvalidArgumentException('PhoreFilesystem is currently not installed. Install phore/filesystem to use FileSystemObjectStoreDriver');
        }
        $rootDirAbs = realpath($rootDir);
        if ($rootDirAbs === false) {
            throw new InvalidArgumentException("Root directory '$rootDir' not accessible");
        }
        $this->rootDir = phore_dir($rootDirAbs);
    }


    public const META_SUFFIX = '.~META~';

    /**
     * @param string $objectId
     * @return bool
     * @throws PathOutOfBoundsException
     */
    public function has(string $objectId): bool
    {
        return $this->rootDir->withSubPath($objectId)->isFile();
    }

    /**
     * @param string $objectId
     * @param $content
     * @param array|null $metadata
     * @return mixed|void
     * @throws FileParsingException
     * @throws FilesystemException
     * @throws PathOutOfBoundsException
     */
    public function put(string $objectId, $content, array $metadata = null)
    {
        $file = $this->rootDir->withSubPath($objectId)->asFile();
        $dir = $file->getDirname()->asDirectory();
        if (!$dir->isDirectory()) {
            $dir->mkdir();
        }
        $file->set_contents($content);
        if ($metadata !== null) {
            $this->rootDir->withSubPath($objectId . self::META_SUFFIX)->asFile()->set_json($metadata);
        }
    }

    /**
     * @param string $objectId
     * @param $resource
     * @param array|null $metadata
     * @return mixed|void
     */
    public function putStream(string $objectId, $resource, array $metadata = null)
    {
        throw new InvalidArgumentException('Not implemented yet.');
        // TODO: Implement putStream() method.
    }

    /**
     * @param string $objectId
     * @param array|null $meta
     * @return StreamInterface
     * @throws NotFoundException
     * @throws FileAccessException
     * @throws FileNotFoundException
     * @throws FileParsingException
     * @throws PathOutOfBoundsException
     */
    public function get(string $objectId, array &$meta = null): string
    {
        $metaFile = $this->rootDir->withSubPath($objectId . self::META_SUFFIX)->asFile();
        if ($metaFile->isFile()) {
            $meta = $metaFile->get_json();
        }

        $file = $this->rootDir->withSubPath($objectId)->asFile();
        if (!$file->isFile()) {
            throw new NotFoundException("Object '$objectId' not existing.", 0);
        }
        return $file->get_contents();
    }

    /**
     * @param string $objectId
     * @param array|null $meta
     * @return StreamInterface
     * @throws PathOutOfBoundsException
     */
    public function getStream(string $objectId, array &$meta = null): StreamInterface
    {
        return $this->rootDir->withSubPath($objectId)->asFile()->fopen('r');
    }

    /**
     * @param string $objectId
     * @throws PathOutOfBoundsException|FileAccessException
     */
    public function remove(string $objectId): void
    {
        $metaFile = $this->rootDir->withSubPath($objectId . self::META_SUFFIX)->asFile();
        if ($metaFile->isFile()) {
            $metaFile->unlink();
        }

        $this->rootDir->withSubPath($objectId)->asFile()->unlink();
    }

    /**
     * @param string $objectId
     * @param string $newObjectId
     */
    public function rename(string $objectId, string $newObjectId)
    {
        throw new InvalidArgumentException('Not implemented yet.');
        // TODO: Implement rename() method.
    }

    /**
     * @param callable $walkFunction
     * @return bool
     */
    public function walk(callable $walkFunction): bool
    {
        return $this->rootDir->walkR(function (PhoreFile $file) use ($walkFunction) {
            if (endsWith($file, self::META_SUFFIX))
                return true; // Ignore file
            $metaFile = phore_file($file . self::META_SUFFIX);
            $meta = [];
            if ($metaFile->isFile())
                $meta = $metaFile->get_json();
            if (false === $walkFunction(new ObjectStoreObject($this, substr($file, strlen($this->rootDir) + 1), $meta)))
                return false;
        });
    }

    /**
     * @param string $objectId
     * @param string $appendData
     * @return mixed
     * @throws PathOutOfBoundsException
     * @throws FilesystemException
     */
    public function append(string $objectId, string $appendData)
    {
        $targetFile = $this->rootDir->withSubPath($objectId)->asFile();

        if ($targetFile->exists()) {
            $targetFile->append_content($appendData);
        } else {
            $targetFile->set_contents($appendData);
        }
        return true;
    }

    /**
     *
     *
     * @param string $objectId
     * @return array        Empty array if object not found
     * @throws FileNotFoundException
     * @throws FileParsingException
     * @throws PathOutOfBoundsException
     */
    public function getMeta(string $objectId): array
    {
        $metaFile = $this->rootDir->withSubPath($objectId . self::META_SUFFIX)->asFile();
        if ($metaFile->isFile()) {
            return $metaFile->get_json();
        }
        return [];
    }

    /**
     * @param string $objectId
     * @param array $metadata
     * @return void
     * @throws FileParsingException
     * @throws PathOutOfBoundsException
     */
    public function setMeta(string $objectId, array $metadata): void
    {
        $file = $this->rootDir->withSubPath($objectId . self::META_SUFFIX)->asFile();
        $dir = $file->getDirname()->asDirectory();
        if (!$dir->isDirectory()) {
            $dir->mkdir();
        }

        $file->set_json($metadata);
    }

    /**
     * list all objects in the bucket/container.
     *
     * Example:
     * ```
     * // Get all objects beginning with the prefix 'test'
     * $list = $driver->list('test');
     *
     * Result object has following structure:
     *  Array
     *  (
     *      [0] => Array
     *             (
     *                  [blobName] => googleConfig.json
     *                  [blobUrl] => file://tmp/googleConfig.json
     *             )
     *      [1] => Array ....
     * ```
     * @param string|null $prefix [optional]
     *     Configuration options.
     *          @type string $prefix Result will contain only objects whose names, contains the prefix
     *
     *          @type null $prefix Result contains all objects in container
     *
     * @return array returns an empty array if no data is available
     */
    public function list(string $prefix = null): array
    {
        $scanned_directory = array_diff(scandir($this->rootDir), array('..', '.'));
        $objectList = [];
        foreach($scanned_directory as $file){
            if(($prefix !== null) && !startsWith($file, $prefix)) {
                continue;
            }
            $objectList[] = ['blobName' => $file, 'blobUrl' => 'file:/'. $this->rootDir . '/' . $file];
        }
        return $objectList;
    }
}
