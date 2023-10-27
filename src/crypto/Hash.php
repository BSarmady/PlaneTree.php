<?php

namespace crypto;

class Hash {
    public static function md2(string $text): string {
        return \hash('md2', $text);
    }

    public static function md4(string $text): string {
        return \hash('md4', $text);
    }

    public static function md5(string $text): string {
        return \hash('md5', $text);
    }

    public static function sha1(string $text): string {
        return \hash('sha1', $text);
    }

    public static function sha256(string $text): string {
        return \hash('sha256', $text);
    }

    public static function sha384(string $text): string {
        return \hash('sha384', $text);
    }

    public static function sha512(string $text): string {
        return \hash('sha512', $text);
    }

    public static function crc32(string $text): string {
        return \hash('crc32', $text);
    }

    public static function crc32b(string $text): string {
        return \hash('crc32b', $text);
    }
}
