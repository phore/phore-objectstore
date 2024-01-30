<?php

namespace Phore\ObjectStore\Encryption;

class SodiumSyncEncryption implements ObjectStoreEncryption
{
    private $key;

    public function __construct(string $key)
    {
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \InvalidArgumentException("Key must be exactly " . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . " bytes long");
        }
        $this->key = $key;
    }

    public function encrypt(string $data): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($data, $nonce, $this->key);
        return $nonce . $encrypted;
    }

    public function decrypt(string $data): string
    {
        $nonce = mb_substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, "8bit");
        $encrypted = mb_substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, "8bit");
        $decrypted = sodium_crypto_secretbox_open($encrypted, $nonce, $this->key);
        if ($decrypted === false) {
            throw new \InvalidArgumentException("Could not decrypt data");
        }
        return $decrypted;
    }

    public function supportsStreaming(): bool
    {
        return false;
    }
    
    public function supportsAppending(): bool
    {
        return false;
    }

}