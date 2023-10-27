<?php

namespace crypto;

class AES128 {

    public static function Encrypt($key, $text, $IV = ENCRYPTION_IV){
        return openssl_encrypt($text, 'aes-128-cbc', $key, 0, $IV);
    }

    public static function Decrypt($key, $text, $IV = ENCRYPTION_IV){
        return openssl_decrypt($text, 'aes-128-cbc', base64_decode($key), 0, $IV);
    }
}