<?php

namespace Phore\ObjectStore\Encryption;

class SodiumSyncEncryption implements ObjectStoreEncryption
{
    private $key;

    public function __construct(string $key)
    {
        if (strlen($key) < 10) {
            throw new \InvalidArgumentException("Key must be at least 10 bytes long");
        }

        $this->key = substr(sha1($key), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $data): string
    {
        $data = gzdeflate($data, 9); // Compress data (level 9)
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
        $deflatedData = gzinflate($decrypted);
        if ($deflatedData === false) {
            return $decrypted; // Transition period
            throw new \InvalidArgumentException("Could not gzinflate data");
        }
        return $deflatedData;
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
