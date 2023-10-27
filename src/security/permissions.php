<?php

namespace security;

use attributes\authenticate;
use config\config;
use i8n\translate;
use IService;
use logger\logger;


class permissions {

    private static permissions $instance;
    private const PERMISSIONS_CACHE_FILE_NAME = CACHE_FOLDER . "/permissions.csv";

    #region public static function get_instance(): self
    public static function get_instance(): self {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }
    #endregion

    #region private function __construct(...)
    private function __construct() { }

    #endregion

    private function remove_auth_extension(string $filename) {
        return str_replace('.auth.html', '', str_replace('.auth.link', '', $filename));
    }

    #region private function system_permissions(): array

    /**
     * Collects system permissions from all applicable files (html and services)
     *
     * @return array array of permissions and their names
     */
    private function system_permissions(): array {
        $logger = logger::get_instance();

        #region Folder iterators
        $services_iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(APP_PATH . '/services/', \FilesystemIterator::UNIX_PATHS | \FilesystemIterator::SKIP_DOTS)
        );
        $html_iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(APP_PATH . '/html/', \FilesystemIterator::UNIX_PATHS | \FilesystemIterator::SKIP_DOTS)
        );
        #endregion

        #region 1- If cache enabled and exist return it
        if (is_readable(self::PERMISSIONS_CACHE_FILE_NAME)) {
            #region Check if cache is still valid
            $cache_time = filemtime(self::PERMISSIONS_CACHE_FILE_NAME);
            $cache_is_invalid = false;
            foreach ($services_iterator as $name => $info) {
                if (stripos($name, '.php') === false)
                    continue;
                if ($cache_time < $info->getMTime()) {
                    $cache_is_invalid = true;
                    break;
                }
            }
            if (!$cache_is_invalid) {
                foreach ($html_iterator as $name => $info) {
                    // only check for html and link files that requires authentication
                    if (stripos($name, '.auth.html') === false
                        && stripos($name, '.auth.link') === false
                    )
                        continue;
                    if ($cache_time < $info->getMTime()) {
                        $cache_is_invalid = true;
                        break;
                    }
                }
            }
            #endregion
            if (!$cache_is_invalid) {
                return json_decode(file_get_contents(self::PERMISSIONS_CACHE_FILE_NAME), JSON_OBJECT_AS_ARRAY);
            }
        }
        #endregion

        #region 2- Find all services and add methods with auth to list
        $service_permissions = [];
        $services_iterator->rewind();
        foreach ($services_iterator as $class_file => $info) {
            if (stripos($class_file, '.php') === false)
                continue;
            require_once($class_file);
            $info = pathinfo($class_file);
            $info['dirname'] = ltrim(str_replace(rtrim(APP_PATH . '/services/', '/'), '', $info['dirname']), '/');
            $className = 'services\\' . ($info['dirname'] == '' ? '' : $info['dirname'] . '\\') . $info['filename'];
            // Add permission only for classes that are instance of \core\IService
            try {
                if (class_exists($className) && is_subclass_of($className, IService::class)) {
                    $class_key_name = str_replace('services.', '', str_replace('\\', '.', ltrim($className, '\\')));
                    //$service_permissions[$class_key_name] = translate::TRANSLATION_MARKER . str_replace('.', '_', $keyName) . translate::TRANSLATION_MARKER;
                    foreach ((new \ReflectionClass($className))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                        if (PHP_MAJOR_VERSION < 8) {
                            // for php version below 8 we look in method comment
                            $doc_comment = (new \ReflectionMethod($className, $method))->getDocComment();
                            /** @noinspection PhpStrFunctionsInspection */
                            if (strpos($doc_comment, '@authenticate') === false) {
                                // this method does not require authentication so has no permission either
                                continue;
                            }
                        } else {
                            // For PHP version 8 and above we look for attribute
                            $attributes = (new \ReflectionMethod($className, $method->name))->getAttributes();
                            $has_authenticate = false;
                            foreach ($attributes as $att) {
                                if ($att->getName() == authenticate::class) {
                                    $has_authenticate = true;
                                    break;
                                }
                            }
                            if (!$has_authenticate) {
                                // this method does not require authentication so has no permission either
                                continue;
                            }
                        }
                        // Add method permission for methods that are public
                        $keyName = $class_key_name . '.' . $method->name;
                        //TODO read description from attribute itself instead of making it from key name
                        $service_permissions[$keyName] = translate::TRANSLATION_MARKER . strtoupper(str_replace('.', '_', $keyName)) . translate::TRANSLATION_MARKER;
                    }
                }
            } catch (\ReflectionException $ex) {
                $logger->fatal('Reading permission from ReflectionMethod failed', $ex);
            }
        }
        #endregion

        #region 3- Find all html and link files in html folder that has .auth and add them as permissions
        $page_permissions = [];
        foreach ($html_iterator as $filename => $info) {
            if (is_readable($filename)) {
                if (stripos($filename, '.auth.html') !== false) {
                    $file_content = file_get_contents($filename);
                    $start = stripos($file_content, '<title>');
                    $end = stripos($file_content, '</title>');

                    if ($start !== false && $end !== false) {
                        $title = trim(substr($file_content, $start + 7, $end - $start - 7), ":/* \r\n");
                    } else {
                        $title = str_replace(APP_PATH . '/html/', '', $this->remove_auth_extension($filename));
                        $logger->warn('page "' . $filename . '" does not have a title.');
                    }
                    $key = strtolower(str_replace('/', '.', str_replace(APP_PATH . '/html/', '', $this->remove_auth_extension($filename))));
                    $page_permissions[$key] = $title;

                } else if (stripos($filename, '.auth.link') !== false) {
                    $file_content = explode(';', file_get_contents($filename));
                    $title = str_replace(APP_PATH . '/html/', '', $this->remove_auth_extension($filename));
                    foreach ($file_content as $item) {
                        if (str_starts_with($item, 'title=')) {
                            $title = str_replace('title=', '', $item);
                            break;
                        }
                    }
                    $key = strtolower(str_replace('/', '.', str_replace(APP_PATH . '/html/', '', $this->remove_auth_extension($filename))));
                    $page_permissions[$key] = $title;
                }
            }
        }
        #endregion

        #region 4- Write to cache
        $arr_perm = array_merge($page_permissions, $service_permissions);
        ksort($arr_perm);
        file_put_contents(self::PERMISSIONS_CACHE_FILE_NAME, json_encode($arr_perm, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        #endregion

        return $arr_perm;
    }
    #endregion

    #region public function user_permissions(): array
    /**
     * returns Users permissions as array, if user is a super admin, all permissions will be returned
     *
     * @param user $user
     * @return array
     */
    public function get_user_permissions(user $user): array {
        // If user is super admin, return all available permissions
        if ($user->is_super_admin()) {
            $all_system_permissions = $this->system_permissions();
            return $all_system_permissions;
        } else {
            $permissions = [];
            foreach ($all_system_permissions as $permission => $permission_title) {
                if ($user->has_permission($permission))
                    $permissions[$permission] = $permission_title;
            }
            return $permissions;
        }
    }
    #endregion
}
