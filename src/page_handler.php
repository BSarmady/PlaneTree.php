<?php

use config\config;
use exceptions\core_exception;
use exceptions\HttpException;
use i8n\Translate;
use logger\logger;
use security\user;
use security\users;
use templates\Template;
use security\token;
use white_list\white_list;

class page_handler {

    private user|null $user = null;
    private string $language;

    #region public function __construct(...)
    public function __construct() {
        date_default_timezone_set(config::SETTINGS_TIMEZONE);
        $this->language = config::DEFAULT_LANGUAGE;
    }
    #endregion

    #region public function execute(): void
    public function execute(): void {
        $logger = logger::get_instance();
        try {

            // check to see if IP is black listed
            $IP = filter_input(INPUT_SERVER, 'REMOTE_ADDR', \FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $logger->debug('== Request ' . $IP);
            if (!white_list::get_instance()->check($IP)) {
                throw new HttpException('Forbidden: IP address of the client has been rejected.', '403.6');
            }

            $route = filter_input(\INPUT_SERVER, 'REQUEST_URI', \FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // clean up route
            $route = strtolower($this->sanitize_route($route));
            $logger->info('page: ' . $route);
            // strip last part of URL until we find a matching file (e.g. details/1/2/3/ will strip to details as URL and /1/2/3 as params
            $virtual_route = $route;
            $file_path = "";
            $page_security_key = "";
            $html_root = APP_PATH . "/html";
            $can_cache = false;
            while (true) {
                if (file_exists($html_root . $virtual_route . "/index.auth.html")) {
                    $file_path = $html_root . $virtual_route . "/index.auth.html";
                    $page_security_key = str_replace("/", ".", $virtual_route);
                    $can_cache = false;
                    break;
                }
                if (file_exists($html_root . $virtual_route . ".auth.html")) {
                    $file_path = $html_root . $virtual_route . ".auth.html";
                    $page_security_key = str_replace("/", ".", $virtual_route);
                    $can_cache = false;
                    break;
                }
                if (file_exists($html_root . $virtual_route . ".html")) {
                    $file_path = $html_root . $virtual_route . ".html";
                    $can_cache = true;
                    break;
                }
                if (file_exists($html_root . $virtual_route . "/index.html")) {
                    $file_path = $html_root . $virtual_route . "/index.html";
                    $can_cache = true;
                    break;
                }
                if (($pos = strripos($virtual_route, '/')) === false)
                    break;
                $virtual_route = substr($virtual_route, 0, $pos);
            }
            $url_params = str_replace($virtual_route, '', $route);
            $url_params = $url_params == '' ? [] : explode('/', substr($url_params, 1));
            $route = $virtual_route;

            $logger->info('file_path: ' . $file_path);

            if ($file_path == "" || !file_exists($file_path))
                throw new HttpException('Not Found', 404);

            #region Get user Information if any
            $this->find_current_user();

            if ($this->user != null) {
                if ($this->user->language != '') {
                    $this->language = $this->user->language;
                }
                $logger->info('user ' . $this->user->username);
            }
            #endregion

            if ($page_security_key !== '') {
                if (!isset($this->user)) {
                    // fail with 401 if user haven't signed in to force them to go to login page,
                    $_SESSION['redirect'] = $route;
                    throw new HttpException('Unauthorized', 401); // this will send user to login page
                }
                if (!$this->user->has_permission($page_security_key)) {
                    // if user singed in and the do not have permission, show forbidden error page
                    throw new HttpException('Forbidden', 403);
                }
            }

            $logger->debug('getting html file ' . $file_path);
            //TODO get a parsed html file to array instead (read from cache if one exist otherwise generate it and cache it)

            // Get Content of page requested
            $html_content = file_get_contents($file_path);
            $page_template_name = $this->meta_tag_value($html_content, 'template', config::DEFAULT_TEMPLATE);
            $page_data = [
                // Do not change order of these lines
                '%%HEADER%%'           => $this->template_tag_content($html_content, 'HEADER'),
                '%%TITLE%%'            => $this->html_tag_content($html_content, '<title>', '</title>'),
                '%%BODY%%'             => $this->template_tag_content($html_content, 'BODY'),
                '%%LANGUAGE%%'         => $this->language,
                '%%TEXT_DIRECTION%%'   => in_array($this->language, ['fa', 'ar']) ? 'rtl' : 'ltr',
                '%%APP_NAME%%'         => config::APP_NAME,
                '%%COPYRIGHT_NOTICE%%' => config::COPYRIGHT_NOTICE
            ];

            // Apply page template
            $content = Template::get_instance()->apply($this->user, $page_data, $route, $page_template_name);
            $content = Translate::get_instance()->Translate($content, $this->language);

            //if ($can_cache && $this->user != null && !$this->user->is_guest())
            //    header('Cache-Control: private, max-age=300');
            //else
                header('Cache-Control: no-cache, no-store');
            header('Content-type:text/html; charset=utf-8');
            header('Content-Length: ' . strlen($content));
            //TODO set user cookie if it is not null
            #endregion
            echo $content;

            #region Performance counter
            if (config::ENABLE_PERFORMANCE_COUNTERS) {
                //$logger->debug(debug_Autoload_Stack());
                $t = intval((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000000) / 1000;
                $logger->info('== Completed in ' . $t . 'ms ==========');
            }
            #endregion

            exit(0);

        } catch (\Exception $ex) {
            $this->handle_exception($ex);
        }
    }
    #endregion

    #region private void handle_Exception(...)
    private function handle_exception(\Exception $ex): void {
        $logger = logger::get_instance();
        $error_code = $ex->getCode();
        $error_message = $ex->getMessage();
        if ($ex instanceof HttpException) {
            // This is http exception that we throw, so we show the message as http error page
            $logger->error($error_code . ":" . $error_message);
        } else if ($ex instanceof core_exception) {
            // These type of errors are thrown by us, so we just show the message as normal page and log an error message
            $error_code = '200';
            $logger->error($error_code . ":" . $error_message);
            $ex = $ex->getPrevious();
            if ($ex !== null)
                $logger->error($ex->getCode() . ":" . $ex->getMessage() . ", " . $ex->getTrace());
        } else {
            //These error messages are not handled properly and might leak critical information,
            // so we log the complete detail and only show a generic 500 error message
            $error_code = 500;
            $logger->fatal($ex);
        }
        http_response_code($error_code);
        if (!file_exists(APP_PATH . '/errors/' . $error_code . '.html'))
            $error_code = '000';
        if (file_exists(APP_PATH . '/errors/000.html')) {
            echo translate::get_instance()->translate(
                str_replace('%%APP_NAME%%', config::APP_NAME,
                    str_replace('%%COPYRIGHT_NOTICE%%', config::COPYRIGHT_NOTICE,
                        file_get_contents(APP_PATH . '/errors/' . $error_code . '.html')
                    )
                ),
                config::DEFAULT_LANGUAGE);
        } else {
            echo $error_code . " - " . $error_message;
        }
    }
    #endregion

    #region private function sanitize_route(...): string
    public function sanitize_route($route): string {
        // remove query string
        if (($q_pos = strpos($route, '?')) !== false) {
            $route = substr($route, 0, $q_pos);
        }
        // replace \ with /
        $route = str_replace('\\', '/', $route);
        // When request is here it is obvious that we didn't have a file with that name in our app, so we don't care about extension either
        // browser will not send ../ or ./  but it is possible to send those from specially built app
        $out = str_replace('.', '/', $route);
        // replace // with / until no more remains (e.g. /// -> /)
        while (strpos($route, '//') > -1) {
            $route = str_replace('//', '/', $route);
        }
        $out = '';
        for ($i = 0; $i < strlen($route); $i++) {
            if (str_contains("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz `~!@#$%^&()_-+={}[];'.,/", $route[$i])) {
                $out .= $route[$i];
            }
        }
        return $out;
    }
    #endregion

    #region private User find_current_user()
    private function find_current_user(): void {
        try {
            $username = "";
            // Check token for username
            $token_query = filter_input(\INPUT_GET, 'token', \FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $token_cookie = filter_input(\INPUT_COOKIE, 'token', \FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (isset($token_query)) {
                $token = token::Decrypt($token_query);
                if ($token != null)
                    $username = $token->username;
            } else if (isset($_SESSION["username"])) {
                // if session has username
                $username = (string)$_SESSION["username"];
            } else if (isset($token_cookie)) {
                //TODO decrypt token cookie
                $token = token::Decrypt($token_cookie);
                if ($token != null)
                    $username = $token->username;
            }
            if ($username !== '')
                $this->user = users::get_instance()->get($username);
        } catch (\Exception) {
            // Bob: Exception is most probably due to incorrect QueryString or cookie value which we will ignore
        }

    }
    #endregion

    #region private function template_tag_content(...): string
    private function template_tag_content(string $haystack, string $tag_name, $default_value = ''): string {
        $start_tag = '<!--%%' . $tag_name . '-->';
        $end_tag = '<!--' . $tag_name . '%%-->';
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

    #region public function html_tag_content(...): string
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

    #region private function meta_tag_value(...): string
    private function meta_tag_value(string $haystack, string $tag_name, string $default_value = ''): string {
        $start_tag = '<meta name="' . $tag_name . '" content="';
        $start = stripos($haystack, $start_tag);
        if ($start !== false) {
            // $haystack contains a $startTag
            $end = stripos($haystack, '"', $start + strlen($start_tag));
            if ($end !== false) {
                // $haystack contains a $endTag after $startTag
                // Get Content between $startTag and $endTag
                $value = trim(substr($haystack, $start + strlen($start_tag), $end - $start - strlen($start_tag)));
                $value = trim($value, ' "');
                return $value === '' ? $default_value : $value;
            }
        }
        return $default_value;
    }
    #endregion

}
