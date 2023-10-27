<?php
namespace crypto;

class AES256 {

    public static function Encrypt($key, $text, $IV = ENCRYPTION_IV): bool|string {
        return openssl_encrypt($text, 'aes-256-cbc', $key, 0, $IV);
    }

    public static function Decrypt($key, $text, $IV = ENCRYPTION_IV): bool|string {
        return openssl_decrypt($text, 'aes-256-cbc', $key, 0, $IV);
    }
}
