<?php

namespace services;

use attributes\authenticate;
use config\config;
use IService;
use logger\logger;
use security\organizations;
use security\user;

class reports implements IService {

    const REPORTS_FOLDER = HTML_FOLDER . '/reports/';
    const REPORTS_LIST_CACHE = CACHE_FOLDER . '/reports_list.json';

    #region public string index(...)
    #[authenticate("##LIST_REPORTS##")]
    public function index(array $request, user $user): string {
        $reports = $this->get_report_list();
        $user_reports = [];
        foreach ($reports as $report) {
            if ($user->has_permission($report['permission']))
                $user_reports[] = [
                    'g' => $report['group'],
                    'k' => $report['route'],
                    'v' => $report['name']
                ];
        }
        return json_encode($user_reports, JSON_ENCODE_OPTIONS);
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

    #region private function get_menu_info(...): array
    private function get_menu_info(string $menu_info_string, array $default_dir_info): array {
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

    #region private function get_report_list(): array
    private function get_report_list(): array {
        if (config::ENABLE_CACHING && file_exists(static::REPORTS_LIST_CACHE)) {
            return json_decode(file_get_contents(static::REPORTS_LIST_CACHE), true, 512, JSON_DECODE_OPTIONS);
        }

        $report_files = [];
        $groups = [];
        foreach (get_files_recursive(static::REPORTS_FOLDER, '*.html', []) as $k => $v) {
            if ($v == static::REPORTS_FOLDER . 'index.auth.html')
                continue;

            $name = str_replace('.html', '', str_replace(static::REPORTS_FOLDER, '', $v));
            $permission = '';
            if (str_ends_with($name, '.auth')) {
                $name = str_replace('\\', '/', str_replace('.auth', '', $name));
                $permission = strtolower(str_replace('/', '.', $name));
            }

            $title = $this->html_tag_content(file_get_contents($v), '<title>', '</title>');
            $group_title = pathinfo($name)['dirname'];
            $group_title = $group_title == '.' ? '##REPORTS##' : $group_title;
            $group = pathinfo($v)['dirname'];
            if (key_exists($group, $groups)) {
                $group_title = $groups[$group];
            } else if (is_readable($group . '/dir_info.txt')) {
                $dir_info = $this->get_menu_info(file_get_contents($group . '/dir_info.txt'), []);
                if (isset($dir_info['title'])) {
                    $group_title = $dir_info['title'];
                }
                $groups[$group] = $group_title;
            }
            $report_files[] = [
                'group'      => $group_title,
                'route'      => $name,
                'name'       => $title,
                'permission' => $permission
            ];
        }

        if (config::ENABLE_CACHING) {
            file_put_contents(static::REPORTS_LIST_CACHE, json_encode($report_files, JSON_ENCODE_OPTIONS));
        }
        return $report_files;
    }
    #endregion

}