<?php

namespace Phore\ObjectStore\Encryption;

interface ObjectStoreEncryption
{
    
    public function encrypt(string $data) : string;
    
    public function decrypt(string $data) : string;

    
    public function supportsStreaming() : bool;
    public function supportsAppending() : bool;
    
}