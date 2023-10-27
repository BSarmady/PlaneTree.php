<?php
$auto_loaded = [];
spl_autoload_register(function ($class_name) {
    global $auto_loaded;
    $filename = realpath(APP_PATH . '/src/' . $class_name . '.php');
    if (file_exists($filename)) {
        $info = pathinfo($class_name);
        $info = pathinfo($class_name);
        $auto_loaded[$info['filename']] = $info['dirname'];
        require_once $filename;
        return true;
    }
    return false;
});

function debug_Autoload_Stack(): string {
    global $auto_loaded;
    ksort($auto_loaded);
    asort($auto_loaded);
    $out = "List of classes loaded automatically\n";
    $length = max(array_map('strlen', $auto_loaded)) + 1;
    foreach ($auto_loaded as $p => $f) {
        $out .= str_pad($f . '\\', $length) . "\t" . $p . ".php\n";
    }
    return $out;
}
