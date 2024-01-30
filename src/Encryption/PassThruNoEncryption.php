<?php

namespace Phore\ObjectStore\Encryption;

class PassThruNoEncryption implements ObjectStoreEncryption
{
    public function encrypt(string $data): string
    {
        return $data;
    }

    public function decrypt(string $data): string
    {
        return $data;
    }

    public function supportsAppending(): bool
    {
        return true;
    }
    
    public function supportsStreaming(): bool
    {
        return true;
    }

}