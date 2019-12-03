<?php


namespace Phore\ObjectStore\Driver;


use http\Exception\InvalidArgumentException;
use Phore\Core\Exception\NotFoundException;
use Phore\FileSystem\Exception\FileAccessException;
use Phore\HttpClient\Ex\PhoreHttpRequestException;
use Psr\Http\Message\StreamInterface;

const BASE_URI = 'https://storage.googleapis.com/storage/v1/';
const UPLOAD_URI = "https://storage.googleapis.com/upload/storage/v1/b/{bucket}/o";//{?query*}';
const DOWNLOAD_URI = "https://storage.googleapis.com/storage/v1/b/{bucket}/o/{object}";//{?query*}';

class PhoreGoogleObjectStoreDriver implements ObjectStoreDriver
{

    private $base_url = "https://storage.googleapis.com/storage/v1";

    private $bucketName;
    private $config;
    private $accessToken;

    public function __construct(string $configFilePath, string $bucketName)
    {
        $this->config = phore_file($configFilePath)->get_json();
        $this->bucketName = $bucketName;
        $this->base_url .= "/b/".$bucketName;

        $this->accessToken = $this->_getJwt()['access_token'];
    }

    protected function _base64Enc($input)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($input));
    }

    private function _getJwt()
    {

        $header = ["alg" => "RS256", "typ" => "JWT"];

        $time = time();

        $payload['iss'] = $this->config['client_email'];
        $payload['scope'] = "https://www.googleapis.com/auth/devstorage.full_control";
        $payload['aud'] = $this->config['token_uri'];
        $payload['exp'] = $time+3600;
        $payload['iat'] = $time;

        $b64header = $this->_base64Enc(json_encode($header));
        $b64payload = $this->_base64Enc(json_encode($payload));

        if ( ! openssl_sign($b64header . "." . $b64payload, $signature, $this->config['private_key'], OPENSSL_ALGO_SHA256))
            throw new \Exception("Cannot openssl_sign payload: ");

        $signedToken = $b64header . "." . $b64payload . "." . $this->_base64Enc($signature);

        return phore_http_request("https://oauth2.googleapis.com/token")->withPostFormBody(["grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer", "assertion" => $signedToken])->send()->getBodyJson();
    }

    public function has(string $objectId): bool
    {
        try {
            phore_http_request(DOWNLOAD_URI, ['bucket' => $this->bucketName, 'object' => $objectId])
                ->withBearerAuth($this->accessToken)->send()->getBodyJson();
        } catch (PhoreHttpRequestException $ex) {
            if($ex->getCode() === 404) {
                return false;
            }
            throw $ex;
        }

        return true;
    }

    private function _getContentType($objectId): string
    {
        switch (pathinfo($objectId)['extension']) {
            case "bin":
                return "application/octet-stream";
            case "txt":
                return "text/plain";
            case "json":
                return "application/json";
            case "csv":
                return "text/csv";
            case "yml":
                return "text/yaml";
            default:
                return "application/octet-stream";
        }
    }

    public function put(string $objectId, $content, array $metadata = null)
    {
        if($metadata === null) {
            try {
                phore_http_request(UPLOAD_URI . "?uploadType=media&name={object}", ['bucket' => $this->bucketName, 'object' => $objectId])
                    ->withBearerAuth($this->accessToken)
                    ->withPostBody($content)->withHeaders(['Content-Type' => $this->_getContentType($objectId)])->send();
            } catch (PhoreHttpRequestException $ex) {
                return false;
            }
            return true;
        }
        $meta['name']=$objectId;
        $meta['metadata'] = $metadata;
        if (is_array($content) || is_object($content)) {
            $content = phore_json_encode($content);
        }
        $meta = phore_json_encode($meta);
        $delimiter = "delimiter";
        $body = "--$delimiter\nContent-Type: application/json; charset=UTF-8\n\n$meta\n\n--$delimiter\nContent-Type: {$this->_getContentType($objectId)}\n\n$content\n--$delimiter--";
        try {
            phore_http_request(UPLOAD_URI . "?uploadType=multipart", ['bucket' => $this->bucketName])
                ->withBearerAuth($this->accessToken)
                ->withPostBody($body)->withHeaders(['Content-Type' => "multipart/related; boundary=$delimiter"])->send();
        } catch (PhoreHttpRequestException $ex) {
            return false;
        }
        return true;

    }

    public function putStream(string $objectId, $ressource, array $metadata = null)
    {
        throw new \InvalidArgumentException("Method not implemented.");
    }

    /**
     * @inheritDoc
     */
    public function get(string $objectId, array &$meta = null): string
    {
        try {
            return phore_http_request(DOWNLOAD_URI . "?alt=media", ['bucket' => $this->bucketName, 'object' => $objectId])
                ->withBearerAuth($this->accessToken)->send()->getBody();
        } catch (PhoreHttpRequestException $ex) {
            if($ex->getCode() === 404) {
                throw new NotFoundException($ex->getMessage(), $ex->getCode(), $ex);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getStream(string $objectId, array &$meta = null): StreamInterface
    {
        throw new \InvalidArgumentException("Method not implemented.");
    }

    /**
     * @inheritDoc
     * @throws PhoreHttpRequestException
     */
    public function remove(string $objectId)
    {
        try {
            return phore_http_request(DOWNLOAD_URI, ['bucket' => $this->bucketName, 'object' => $objectId])
                ->withBearerAuth($this->accessToken)->withMethod('DELETE')->send()->getBody();
        } catch (PhoreHttpRequestException $ex) {
            if($ex->getCode() === 404) {
                throw new NotFoundException($ex->getMessage(), $ex->getCode(), $ex);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function rename(string $objectId, string $newObjectId)
    {
        if($this->has($newObjectId)){
            throw new \InvalidArgumentException("Cannot rename '$objectId'. ObjectId '$newObjectId' already in use.");
        }
        return phore_http_request(DOWNLOAD_URI."/copyTo/b/{bucket}/o/{newObjectId}",
            ['bucket' => $this->bucketName, 'object' => $objectId, 'newObjectId' => $newObjectId]
        )->withBearerAuth($this->accessToken)->withPostBody()->send()->getBodyJson();

    }

    /**
     * @inheritDoc
     * @throws PhoreHttpRequestException
     * @throws NotFoundException
     */
    public function append(string $objectId, string $data)
    {
        $meta = $this->getMeta($objectId);
        if($meta === []) {
            $this->put($objectId, $data);
            return true;
        }

        $ext = pathinfo($objectId)["extension"];
        if ($ext != "") {
            $ext = ".$ext";
        }
        $tmpId = "/tmp/" . time() . "-" . sha1(microtime(true) . uniqid()) . "$ext";
        $this->put($tmpId, $data);
        $body['kind'] = "storage#composeRequest";
        $body['sourceObjects'] = [['name' => $objectId], ['name' => $tmpId]];
        $body['destination'] = $meta;

        return phore_http_request(DOWNLOAD_URI . "/compose", ['bucket' => $this->bucketName, 'object' => $objectId])
            ->withBearerAuth($this->accessToken)->withPostBody($body)->send()->getBodyJson();
    }

    /**
     * @inheritDoc
     */
    public function getMeta(string $objectId): array
    {

        try {
            return phore_http_request(DOWNLOAD_URI, ['bucket' => $this->bucketName, 'object' => $objectId])
                ->withBearerAuth($this->accessToken)->send()->getBodyJson();
        } catch (PhoreHttpRequestException $ex) {
            if($ex->getCode() === 404) {
                return [];
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function setMeta(string $objectId, array $metadata)
    {
        throw new \InvalidArgumentException("Metadata cannot be set. Method not implemented.");
    }

    public function walk(callable $walkFunction): bool
    {
        throw new \InvalidArgumentException("Method not implemented.");
    }
}
