<?php

use config\config;
use crypto\Hash;

session_start();
mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');

// APP_PATH determines is root of the application
define('APP_PATH', $_SERVER['DOCUMENT_ROOT']);
const SERVICE_URI = '/services/';
const ASSETS_FOLDER = APP_PATH . '/assets';
const CACHE_FOLDER = APP_PATH . '/cache';
const DATA_FOLDER = APP_PATH . '/data';
const HTML_FOLDER = APP_PATH . '/html';
const TEMPLATE_FOLDER = APP_PATH . '/templates';
const LOG_FOLDER = APP_PATH . '/logs';

#region make sure all default folders exist
if (!is_dir(ASSETS_FOLDER)) {
    mkdir(ASSETS_FOLDER, 755, true);
}
if (!is_dir(CACHE_FOLDER)) {
    mkdir(CACHE_FOLDER, 755, true);
}
if (!is_dir(DATA_FOLDER)) {
    mkdir(DATA_FOLDER, 755, true);
}
if (!is_dir(HTML_FOLDER)) {
    mkdir(HTML_FOLDER, 755, true);
}
if (!is_dir(TEMPLATE_FOLDER)) {
    mkdir(TEMPLATE_FOLDER, 755, true);
}
if (!is_dir(LOG_FOLDER)) {
    mkdir(LOG_FOLDER, 755, true);
}
#endregion

// Use this when root of application is not same as root of website
//define('APP_PATH', str_replace('/src','/',str_replace('\\', '/', __DIR__)));

// use new line (\n) instead of PHP_EOL which is different based on system.
const CH_EOL = "\n";

// TODO: change in Production environment
//const JSON_ENCODE_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_SLASHES | JSON_OBJECT_AS_ARRAY;
const JSON_ENCODE_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_SLASHES | JSON_OBJECT_AS_ARRAY | JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT;
const JSON_DECODE_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_SLASHES | JSON_OBJECT_AS_ARRAY | JSON_PRESERVE_ZERO_FRACTION;

//TODO uncomment default key
// This is default key which is used to encrypt critical data sent to clients. It needs to be replaced for each application
define('ENCRYPTION_KEY', hex2bin('4b51448b7bea4f689b5155d5fd3dad0d'));
define('ENCRYPTION_IV', hex2bin('00000000000000000000000000000000'));

require_once 'autoload.php';       // Auto load script
require_once 'utils.php';          // Utility function
require_once 'debug_utils.php';    // Debug utility functions (such as debug), can be removed when published to production

if (Hash::md5(ENCRYPTION_KEY) == '820df8ef8af3d7eb376c5576ae3a87bd')
    die('Using default Encryption key is not safe and will compromise your application'); // replace the key and IV above
