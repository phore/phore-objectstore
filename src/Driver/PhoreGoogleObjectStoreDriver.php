<?php


namespace Phore\ObjectStore\Driver;


use Phore\Core\Exception\NotFoundException;
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
        try {
            phore_http_request(UPLOAD_URI . "?uploadType=media&name={object}", ['bucket' => $this->bucketName, 'object' => $objectId])
                ->withBearerAuth($this->accessToken)
                ->withPostBody($content)->withHeaders(['Content-Type' => $this->_getContentType($objectId)])->send();
        } catch (PhoreHttpRequestException $ex) {
            return false;
        }
        return true;
    }

    public function putStream(string $objectId, $ressource, array $metadata = null)
    {
        // TODO: Implement putStream() method.
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
                return false;
            }
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function getStream(string $objectId, array &$meta = null): StreamInterface
    {
        // TODO: Implement getStream() method.
    }

    /**
     * @inheritDoc
     */
    public function remove(string $objectId)
    {
        // TODO: Implement remove() method.
    }

    /**
     * @inheritDoc
     */
    public function rename(string $objectId, string $newObjectId)
    {
        // TODO: Implement rename() method.
    }

    /**
     * @inheritDoc
     */
    public function append(string $objectId, string $data)
    {
        // TODO: Implement append() method.
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
                return false;
            }
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function setMeta(string $objectId, array $metadata)
    {
        // TODO: Implement setMeta() method.
    }

    public function walk(callable $walkFunction): bool
    {
        // TODO: Implement walk() method.
    }
}
