<?php

function SanitizeFileName($fileName) {
    $out = "";
    for ($i = 0; $i < strlen($fileName); $i++) {
        if (strpos("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz `~!@#$%^&()_-+={}[];'.,", $fileName[$i]) !== false) {
            $out .= $fileName[$i];
        }
    }
    return $out;
}

function starts_with($haystack, $needle) {
    $length = strlen($needle);
    return substr($haystack, 0, $length) === $needle;
}

function ends_with($haystack, $needle) {
    $length = strlen($needle);
    if (!$length) {
        return true;
    }
    return substr($haystack, -$length) === $needle;
}


function SanitizeRoute($fileName) {
    $out = "";
    for ($i = 0; $i < strlen($fileName); $i++) {
        if (strpos("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz `~!@#$%^&()_-+={}[];'.,", $fileName[$i]) !== false) {
            $out .= $fileName[$i];
        }
    }
    while (strpos($out, '..') !== false)
        $out = str_replace('..', '.', $out);
    return trim($out, '.');
}

function get_files_recursive($folder, $filter, $ignore_folders) {
    $results = glob($folder . $filter, GLOB_NOSORT);
    foreach ($results as $key => $dir) {
        // remove directories, we only need files, recursively
        if (in_array($dir, $ignore_folders) || is_dir($dir)) {
            unset($results[$key]);
        }
    }
    foreach (glob($folder . '*/', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
        if (!in_array($dir, $ignore_folders)) {
            $results = array_merge($results, \get_files_recursive($dir, $filter, $ignore_folders));
        }
    }
    return $results;
}

function mb_ucfirst($string) {
    $strlen = mb_strlen($string, 'UTF-8');
    $firstChar = mb_substr($string, 0, 1, 'UTF-8');
    $then = mb_substr($string, 1, $strlen - 1, 'UTF-8');
    return mb_strtoupper($firstChar, 'UTF-8') . $then;
}

function contains($str, array $arr) {
    foreach ($arr as $a) {
        if (stripos($str, $a) !== false)
            return true;
    }
    return false;
}

function right_trim(string $haystack, string $needle): string {
    $needle_length = strlen($needle);
    if (substr($haystack, -$needle_length) === $needle) {
        return substr($haystack, 0, -$needle_length);
    }
    return $haystack;
}

enum allowed_chars {
    case uppercase;
    case lowercase;
    case digits;
    case space;
    case symbols;
    case dot;
    case at;
    case dash;
    case slash;
    case underline;
}

#region function sanitize(...)
function sanitize(string $inStr, array $allowed_chars): string {
    $filter =
        str_split(
            (in_array(allowed_chars::lowercase, $allowed_chars) ? "abcdefghijklmnopqrstuvwxyz" : "") .
            (in_array(allowed_chars::uppercase, $allowed_chars) ? "ABCDEFGHIJKLMNOPQRSTUVWXYZ" : "") .
            (in_array(allowed_chars::digits, $allowed_chars) ? "01234567890" : "") .
            (in_array(allowed_chars::space, $allowed_chars) ? " " : "") .
            (in_array(allowed_chars::symbols, $allowed_chars) ? "~!#$%^&()[]{}_+`-=," : "") .
            (in_array(allowed_chars::dot, $allowed_chars) ? "." : "") .
            (in_array(allowed_chars::at, $allowed_chars) ? "@" : "") .
            (in_array(allowed_chars::dash, $allowed_chars) ? "-" : "") .
            (in_array(allowed_chars::slash, $allowed_chars) ? "/" : "") .
            (in_array(allowed_chars::underline, $allowed_chars) ? "_" : "")
        );

    $sb = '';
    for ($i = 0; $i < strlen($inStr); $i++) {
        if (in_array($inStr[$i], $filter)) {
            $sb .= $inStr[$i];
        }
    }
    return $sb;
}
#endregion