<?php

namespace templates;

use config\config;
use i8n\Translate;
use logger\logger;
use security\user;
use Exception;
use security\user_photos;

class Template {

    #region Constants
    private const TEMPLATE_CACHE_FOLDER = CACHE_FOLDER . "/templates/";
    private const MENU_FILE_CACHE_NAME = CACHE_FOLDER . '/menus.json';
    private const DEFAULT_PAGE_TEMPLATE = '<!DOCTYPE html><html lang="%%LANGUAGE%%" dir="%%TEXT_DIRECTION%%">'
    . '<head><meta charset="utf-8" /><meta http-equiv="content-type" content="text/html; charset=utf-8"><title>%%APP_NAME%% - %%TITLE%%</title>%%HEADER%%</head>'
    . '<body>%%BODY%%</body></html>';
    private const EXCLUDED_PAGES = [];
    #endregion

    private static Template $instance;

    #region public static function getInstance(): self
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

    #region private function list_files_recursive(...): array
    private function list_files_recursive($folder): array {
        $results = glob($folder . '/*.html', GLOB_NOSORT);
        foreach ($results as $key => $dir) {
            // remove directories, we only need files, recursively
            if (is_dir($dir)) {
                unset($results[$key]);
            }
        }
        foreach (glob($folder . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $results = array_merge($results, $this->list_files_recursive($dir));
        }
        return $results;
    }
    #endregion

    #region private function meta_tag_value(...)
    private function meta_tag_value(string $haystack, string $tag_name, string $default_value = ''): string {
        $start_tag = '<meta name="' . $tag_name . '" ';
        $start = stripos($haystack, $start_tag);
        if ($start !== false) {
            // $haystack contains a $startTag
            $end = stripos($haystack, '/>', $start);
            if ($end !== false) {
                // $haystack contains a $endTag after $startTag
                // Get Content between $startTag and $endTag
                $value = trim(substr($haystack, $start + strlen($start_tag), $end - $start - strlen($start_tag)));
                $value = str_ireplace('content="', '', trim($value, ' "'));
                return $value === '' ? $default_value : $value;
            }
        }
        return $default_value;
    }
    #endregion

    #region private function get_menu_info(...): array
    private function get_menu_info(string $menu_info_string, array $dir_info): array {
        $logger = logger::get_instance();
        $chunks = explode(';', $menu_info_string);
        foreach ($chunks as $v) {
            if ($v == '')
                continue;
            $chunks = explode('=', $v);
            if (count($chunks) != 2) {
                $logger->fatal('Invalid menu info content in ' . $menu_info_string);
                continue;
            }
            $dir_info[$chunks[0]] = $chunks[1];
        }
        return $dir_info;
    }
    #endregion

    #region private function html_tag_content(...)
    private function html_tag_content(string $haystack, string $start_tag, $end_tag, $default_value = ''): string {
        $start = stripos($haystack, $start_tag);
        if ($start !== false) {
            // $haystack contains a $startTag
            $end = stripos($haystack, $end_tag, $start);
            if ($end !== false) {
                // $haystack contains a $endTag after $startTag
                // Get Content between $startTag and $endTag
                return trim(substr($haystack, $start + strlen($start_tag), $end - $start - strlen($start_tag)));
            }
        }
        return $default_value;
    }
    #endregion

    #region private function build_menu_data(...): array
    private function build_menu_data(): array {
        $logger = logger::get_instance();

        if (!is_dir(HTML_FOLDER)) {
            return [];
        }

        // If menu is cached, return that one
        if (config::ENABLE_CACHING && is_readable(self::MENU_FILE_CACHE_NAME)) {
            return json_decode(file_get_contents(self::MENU_FILE_CACHE_NAME), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        // otherwise build menu list again
        $files = $this->list_files_recursive(HTML_FOLDER);
        asort($files);
        $menus = [];
        foreach ($files as $file) {
            $route = str_replace('.html', '', str_replace('.auth', '', str_replace(HTML_FOLDER, '', $file)));
            if (contains($route, static::EXCLUDED_PAGES)) {
                continue;
            }
            if (!is_readable($file)) {
                continue;
            }

            // Read menu meta tag
            $file_content = file_get_contents($file);
            $menu = $this->meta_tag_value($file_content, 'menu');
            $title = $this->html_tag_content($file_content, '<title>', '</title>');
            if ($menu == '') {
                // Page has no menu, ignore it
                continue;
            }

            #region find parent
            $parent = '/';
            if (($parent_pos = strpos($route, '/', 1)) !== false) {
                $parent1 = substr($route, 0, $parent_pos);
                if (str_replace($parent1, '', $route) != '/' && !key_exists($parent, $menus)) {
                    $parent = $parent1;
                    $dir_info = [
                        'parent'     => '/',
                        'route'      => $parent,
                        'title'      => Translate::TRANSLATION_MARKER . mb_strtoupper(str_replace('/', '_', ltrim($parent, '/'))) . Translate::TRANSLATION_MARKER,
                        'order'      => 0,
                        'permission' => '',
                        'icon'       => '',
                        'folder'     => 1
                    ];
                    $dir_info_file = HTML_FOLDER . $parent . '/dir_info.txt';
                    if (is_readable($dir_info_file)) {
                        $dir_info = $this->get_menu_info(file_get_contents($dir_info_file), $dir_info);
                    }
                    $menus[$parent] = $dir_info;
                }
            }
            #endregion
            $menu_info = [
                'parent'     => $parent,
                'route'      => $route,
                'title'      => $title != '' ? $title : Translate::TRANSLATION_MARKER . mb_strtoupper(str_replace('/', '_', $route)) . Translate::TRANSLATION_MARKER,
                'order'      => 0,
                'permission' => ends_with($file, '.auth.html') ? str_replace('/', '.', ltrim($route, '/')) : '',
                'icon'       => '',
                'folder'     => 0
            ];
            $menus[$route] = $this->get_menu_info($menu, $menu_info);
        }
        $menus['/users'] = [
            'parent'     => '/',
            'route'      => '/users',
            'title'      => 'User',
            'order'      => 10000,
            'permission' => '',
            'icon'       => '',
            'folder'     => 1
        ];
        $menus['user/user_profile'] = [
            'parent'     => '/users',
            'route'      => '/users/user_profile',
            'title'      => '##USER_PROFILE##',
            'order'      => 1,
            'permission' => 'users.user_profile',
            'icon'       => '',
            'folder'     => 0
        ];
        if (config::TWO_STEP_SIGN_IN) {
            $menus['user/security_image'] = [
                'parent'     => '/users',
                'route'      => '/users/user_profile#SecurityImage',
                'title'      => '##CHANGE_SECURITY_IMAGE##',
                'order'      => 2,
                'permission' => 'users.user_profile',
                'icon'       => '',
                'folder'     => 0
            ];
        }
        $menus['user/change_password'] = [
            'parent'     => '/users',
            'route'      => '/users/user_profile#ChangePassword',
            'title'      => '##CHANGE_PASSWORD##',
            'order'      => 2,
            'permission' => 'users.user_profile',
            'icon'       => '',
            'folder'     => 0
        ];
        if (config::ENABLE_QA_RECOVERY) {
            $menus['user/password_recovery'] = [
                'parent'     => '/users',
                'route'      => '/users/user_profile#PasswordRecovery',
                'title'      => '##PASSWORD_RECOVERY_OPTIONS##',
                'order'      => 4,
                'permission' => 'users.user_profile',
                'icon'       => '',
                'folder'     => 0
            ];
        }
        $menus['user/sep'] = [
            'parent'     => '/users',
            'route'      => '/users/-',
            'title'      => '-',
            'order'      => 5,
            'permission' => 'users.user_profile',
            'icon'       => '',
            'folder'     => 0
        ];
        $menus['user/sign_out'] = [
            'parent'     => '/users',
            'route'      => '/users/sign-out',
            'title'      => '##SIGN_OUT##',
            'order'      => 6,
            'permission' => 'users.user_profile',
            'icon'       => '',
            'folder'     => 0
        ];
        ksort($menus);
        if (config::ENABLE_CACHING) {
            try {
                file_put_contents(self::MENU_FILE_CACHE_NAME, json_encode($menus, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } catch (Exception $ex) {
                $logger->fatal('cannot write menu cache file', $ex);
            }
        }
        return $menus;
    }
    #endregion

    #region private function user_menu(...): string
    private function user_menu(user $user): string {
        global $logger;
        $menus = $this->build_menu_data();
        $user_menus = [];
        foreach ($menus as $menu) {
            if ($menu['folder'] == 1)
                continue;
            // only add to user menu if they have permission
            if ($menu['permission'] != '' && !$user->has_permission($menu['permission']))
                continue;
            unset($menu['permission']);
            $user_menus[$menu['route']] = $menu;
            $parent = $menu['parent'];
            if ($parent != '/' && !isset($user_menus[$parent]) && isset($menus[$parent])) {
                $menu = $menus[$parent];
                unset($menu['permission']);
                $user_menus[$parent] = $menu;
            }
        }
        if ($user->is_guest()) {
            $user_menus['user'] = [
                'parent' => '/',
                'route'  => '/sign-in',
                'title'  => '##SIGN_IN##',
                'order'  => 9999,
                'icon'   => '',
                'folder' => 0
            ];
        }
        //ksort($user_menus);
        $json_menu = '[]';
        try {
            $json_menu = json_encode(array_values($user_menus), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (Exception $ex) {
            $logger->fatal('cannot json encode user_menu for ' . $user->username, $ex);
        }
        return 'data_user_menu = ' . $json_menu . ';';
    }
    #endregion

    #region private function current_user_info(...): string
    private function current_user_info(user $user): string {
        //return '<div class="float-left image">' . ($user->photo === '' ? '<i class="fa fa-3x fa-user-circle" style="color:#cccccc"></i>' : '<img src="uploads/user_photo/' . $user->photo . '" class="img-circle" alt="##User_image##">') . '</div>' .
        try {
            return json_encode([
                'username'  => $user->username,
                'real_name' => ucwords($user->real_name === '' ? $user->username : $user->real_name),
                'roles'     => $user->roles,
                'photo'     => user_photos::get_photo_as_b64_image_data($user->photo, 32, 32)

            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (Exception $ex) {
            logger::get_instance()->fatal('cannot json encode user info for ' . $user->username, $ex);
            return '[]';
        }
    }
    #endregion

    #region private function prepare_template(...): string
    /**
     * @param string $filename
     * @return string
     * @throws template_exception
     */
    private function prepare_template(string $filename): string {
        $content = file_get_contents($filename);
        $start_tag_prefix = '<!--%%';
        $start_tag_prefix_len = strlen($start_tag_prefix);
        $start_tag_suffix = '-->';
        //$start_tag_suffix_len = strlen($start_tag_suffix);
        $end_tag_prefix = '<!--';
        $end_tag_suffix = '%%-->';
        while (($tag_start = stripos($content, $start_tag_prefix)) !== false) {
            // $haystack contains a $startTag
            if (($tag_end = stripos($content, $start_tag_suffix, $tag_start)) === false) {
                throw new template_exception('invalid tag found in ' . $filename . ' template');
            }
            // $haystack contains a $endTag after $startTag
            // Get Content between $startTag and $endTag
            $tag_name = trim(substr($content, $tag_start + $start_tag_prefix_len, $tag_end - $tag_start - $start_tag_prefix_len));

            $start_tag_pos = stripos($content, $start_tag_prefix . $tag_name . $start_tag_suffix);
            $end_tag_pos = stripos($content, $end_tag_prefix . $tag_name . $end_tag_suffix, $start_tag_pos);
            if ($end_tag_pos === false) {
                throw new template_exception('unclosed/invalid tag found in ' . $filename . ' template');
            }
            $content = substr($content, 0, $start_tag_pos) . '%%' . $tag_name . '%%' . substr($content, $end_tag_pos + strlen($end_tag_prefix . $tag_name . $end_tag_suffix));
        }
        return $content;
    }
    #endregion

    #region private function get_template(...): string
    private function get_template(string $template_name): string {
        $template_file_name = TEMPLATE_FOLDER . '/' . $template_name . '.html';
        $template_cache_file_name = self::TEMPLATE_CACHE_FOLDER . $template_name . '.html';

        // If template file doesn't exist, use default template
        if (!is_readable($template_file_name)) {
            return static::DEFAULT_PAGE_TEMPLATE;
        }
        // If cache is enabled ...
        if (config::ENABLE_CACHING) {
            // if cache file exist and is newer than template file, use that
            if (is_readable($template_cache_file_name) && filemtime($template_cache_file_name) > filemtime($template_file_name)) {
                return file_get_contents($template_cache_file_name);
            }
        }
        // ... otherwise rebuild template and use it.
        try {
            $template = $this->prepare_template($template_file_name);
        } catch (Exception $ex) {
            logger::get_instance()->error($ex);
            return static::DEFAULT_PAGE_TEMPLATE;
        }
        if (config::ENABLE_CACHING) {
            // If cache is enabled, store it in cache for later use
            file_put_contents($template_cache_file_name, $template);
        }
        return $template;
    }
    #endregion

    #region public function apply(...): string
    public function apply(user|null $user, array $page_data, string $current_page, string $template_name): string {
        $template = $this->get_template($template_name);
        if ($user == null) {
            $user = user::guest();
        }
        $template_data['USER_MENU'] = $this->user_menu($user);
        $template_data['CURRENT_USER_INFO'] = 'data_current_user = ' . $this->current_user_info($user) . ';';
        $template_data['CURRENT_PAGE'] = 'data_current_page = "' . $current_page . '";';
        $template_data['SERVICE_URI'] = 'data_service_uri = "' . SERVICE_URI . '";';
        $template_data['APP_SETTINGS'] = 'data_app_config = ' . $this->get_app_config() . ';';
        $page_data['%%TEMPLATE_DATA%%'] = '<script type="application/javascript">' . join(CH_EOL, $template_data) . '</script>';

        foreach ($page_data as $key => $value) {

            // include custom renders to template
            $template = str_replace($key, $value, $template);
        }
        return $template;
    }
    #endregion

    #region private function get_app_config()
    private function get_app_config(): string {
        try {
            return json_encode([
                'twostep'  => config::TWO_STEP_SIGN_IN,
                'captcha'  => config::ENABLE_CAPTCHA,
                'ar_qa'    => config::ENABLE_QA_RECOVERY,
                'ar_email' => config::ENABLE_EMAIL_RECOVERY,
                'ar_sms'   => config::ENABLE_SMS_RECOVERY
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return '[]';
        }
    }
    #endregion
}
