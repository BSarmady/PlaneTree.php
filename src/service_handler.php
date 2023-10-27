<?php

use config\config;
use exceptions\core_exception;
use exceptions\service_exception;
use exceptions\HttpException;
use i8n\Translate;
use logger\logger;
use security\user;
use security\users;
use security\token;
use white_list\white_list;

class service_handler {
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
            $logger->debug('== Service Request ' . $IP);
            if (!white_list::get_instance()->check($IP)) {
                throw new HttpException('Forbidden: IP address of the client has been rejected.', '403.6');//403.6 - Forbidden: IP address
            }

            $post_size = intval($_SERVER['CONTENT_LENGTH']);
            if ($post_size < 1) {
                throw new HttpException('Request is not a JSON document', 400); // 400 Bad Request
            }
            if ($post_size > config::MAX_POST_SIZE) {
                throw new HttpException('Max Post size limit reached', 413); // 413 Payload Too Large
            }

            $REQ_POST = file_get_contents('php://input');
            $json_req = json_decode($REQ_POST, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY | JSON_UNESCAPED_SLASHES);

            if (count($json_req) < 1) {
                // $json_req should never be empty, at least a cmd property is expected
                throw new HttpException('Request is not a JSON document', 400);//400 Bad Request
            }
            if (!isset($json_req['cmd'])) {
                throw new HttpException('Request does not contain cmd', 400);//400 Bad Request
            }
            $command = strtolower($json_req['cmd']);

            // "ping" is special command to return the request exactly (used for testing service availability and testing headers)
            if ($command == 'ping') {
                echo 'pong';
                die();
            }

            #region Get user Information if any
            $this->find_current_user();

            if ($this->user->language != '') {
                $this->language = $this->user->language;
            }
            $logger->info('user ' . $this->user->username);
            #endregion

            // Split command to namespace\class and method
            $class_details = $this->parse_command($command);
            if (!class_exists($class_details['class'])) {
                // class should exist in \service\namespace\classname location for autoloader to find and load it, otherwise fail
                throw new HttpException('service class ' . $class_details['class'] . ' does not exists.', 404);
            }
            // check if class implements IService
            if (!in_array(IService::class, class_implements($class_details['class']))) {
                // For security reason requested class should be an instance of service_base
                throw new HttpException('service class ' . $class_details['class'] . ' does not implement IService.', 404);
            }
            // Make sure method exists
            if (!method_exists($class_details['class'], $class_details['method'])) {
                // Method doesn't exist
                throw new HttpException('A public method ' . $class_details['method'] . ' does not exist in ' . $class_details['class'] . '.', 404);
            }
            // Instantiate command class
            $class = new $class_details['class']();
            // Bob: Only public method can be called.
            $reflection = new \ReflectionMethod($class, $class_details['method']);
            if (!$reflection->isPublic()) {
                throw new HttpException('404 Not Found', 404);
            }
            $translate = true;
            // check for method attributes
            $attributes = (new \ReflectionMethod($class, $class_details['method']))->getAttributes();
            foreach ($attributes as $attribute) {
                if ($attribute->getName() == 'attributes\authenticate') {
                    if ($this->user == null) {
                        throw new service_exception('401 Unauthorized', 401);
                    } else if (!$this->user->has_permission($command)) {
                        // fail with 403 if user doesn't have permission to use service
                        $logger->error('User ' . $this->user->username . ' has no access!');
                        throw new service_exception('403 Forbidden', 403);
                    }
                } else if ($attribute->getName() == 'attributes\no_translate') {
                    // do not translate output of the method
                    $translate = false;
                }
            }

            //Bob: If there is a response from command processor, translate and return response
            $response = $class->{$class_details['method']}($json_req, $this->user);
            if ($response === '') {
                //Bob: If command processor didn't return response, fail with 404
                throw new service_exception('Not found');
            }
            if ($translate) {
                $language = $this->user->language;
                $response = Translate::get_instance()->translate($response, $language);
            }
            //TODO set user cookie if it is not null
            header('Cache-Control: no-cache, no-store');
            header('Content-type: application/json; charset=utf-8');
            header('Content-Length: ' . strlen($response));

            echo $response;

            #region Performance counter
            if (config::ENABLE_PERFORMANCE_COUNTERS) {
                //$logger->debug(debug_Autoload_Stack());
                $t = intval((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000000) / 1000;
                $logger->info('== Completed in ' . $t . 'ms ==========');
            }
            #endregion
            exit(0);

        } catch (\Exception $ex) {
            $this->handle_Exception($ex);
            //$this->handle_Exception($ex);
        }
    }
    #endregion

    #region private void handle_Exception(...)
    private function handle_Exception(\Exception $ex): void {
        $logger = logger::get_instance();
        $error_code = $ex->getCode();
        $error_message = $ex->getMessage();
        if ($ex instanceof HttpException) {
            // This is http exception that we throw, so we show the message as http error page
            $logger->error($error_code . ":" . $error_message);
            http_response_code(200);
            if (isset(HttpException::ERROR_MESSAGES[$error_code]))
                $error_message = HttpException::ERROR_MESSAGES[$ex->getCode()];
            echo $error_code . '- ' . $error_message;
        } else if ($ex instanceof core_exception) {
            // These type of errors are thrown by us, so we just show the message as normal page and log an error message
            $logger->error($error_code . ":" . $error_message);
            http_response_code(200);
            echo json_encode(['error' => $error_message], JSON_ENCODE_OPTIONS);
            $ex = $ex->getPrevious();
            if ($ex !== null)
                $logger->error($ex->getCode() . ":" . $ex->getMessage() . ", " . $ex->getTraceAsString());
        } else {
            //These error messages are not handled properly and might leak critical information,
            // so we log the complete detail and only show a generic 500 error message
            $logger->fatal($ex);
            http_response_code($error_code);
            echo "500 - Internal server error";
        }
    }
    #endregion

    #region private function service_runner(): string
    private function service_runner(): string {
        $logger = logger::get_instance();
        try {
            $language = config::DEFAULT_LANGUAGE;
            $post_data = file_get_contents('php://input');
            $json_req = json_decode($post_data, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY | JSON_UNESCAPED_SLASHES);
            if (count($json_req) < 1) {
                // $json_req should never be empty, at least a cmd property is expected
                throw new service_exception('##INVALID_REQUEST##');
            }
            if (!isset($json_req['cmd'])) {
                throw new service_exception('##NO_INSTRUCTIONS_WERE_RECEIVED##');
            }
            $command = strtolower($json_req['cmd']);
            // "ping" is special command to return the request exactly (used for testing service availability and testing headers)
            if ($command == 'ping') {
                echo '{"data":"pong"}';
                die();
            }
            $logger->info('srv ' . $command);
            // Split command to namespace\class and method
            $class_details = $this->parse_command($command);
            if (!class_exists($class_details['class'])) {
                // class should exist in \service\namespace\classname location for autoloader to find and load it, otherwise fail
                $logger->error('service class ' . $class_details['class'] . ' doesn\'t exists.');
                throw new service_exception('##NOT_FOUND##', 404);
            }
            // check if class implements IService
            if (!in_array(IService::class, class_implements($class_details['class']))) {
                // For security reason requested class should be an instance of service_base
                $logger->error('service class ' . $class_details['class'] . ' doesn\'t implement IService.');
                throw new service_exception('##NOT_FOUND##', 404);
            }
            // Make sure method exists
            if (!method_exists($class_details['class'], $class_details['method'])) {
                // Method doesn't exist
                $logger->error('A public method ' . $class_details['method'] . ' doesn\t exist in ' . $class_details['class'] . '.');
                throw new service_exception('##NOT_FOUND##', 404);
            }
            // Instantiate command class
            $class = new $class_details['class']();

            // Bob: Only public method can be called.
            $reflection = new \ReflectionMethod($class, $class_details['method']);
            if (!$reflection->isPublic()) {
                throw new service_exception('##NOT_FOUND##', 404);
            }

            $translate = true;
            $attributes = (new \ReflectionMethod($class, $class_details['method']))->getAttributes();
            foreach ($attributes as $i) {
                if ($i->getName() == 'core\attributes\authenticate' && !$this->user->has_permission($command)) {
                    // fail with 403 if user doesn't have permission to use service
                    $logger->error('User ' . $this->user->username . ' has no access!');
                    throw new service_exception('##ERROR_ACCESS_DENIED##', 403);
                } else if ($i->getName() == 'core\attributes\no_translate') {
                    // do not translate output of the method
                    $translate = false;
                }
            }
            //Bob: If there is a response from command processor, translate and return response
            $response = $class->{$class_details['method']}($json_req, $this->user);
            if ($response === '') {
                //Bob: If command processor didn't return response, fail with 404
                throw new service_exception('Not found');
            }
            if ($translate) {
                $language = $this->user->language;
                $response = Translate::get_instance()->translate($response, $language);
            }
            //TODO set user cookie if it is not null

            return $response;

        } catch (\Exception $ex) {
            $message = '##ERROR_INTERNAL_ERROR##';
            if ($ex instanceof core_exception) {
                $message = $ex->getMessage();
                $ex_ex = $ex->getPrevious();
                if (!is_null($ex_ex))
                    $logger->fatal('Internal Exception', $ex_ex);
            } else {
                $logger->fatal('Exception handling request ' . $post_data, $ex);
            }
            return '{"error":"' . Translate::get_instance()->translate($message, $language) . '", "code":"' . $ex->getCode() . '"}';
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
            $token_header = filter_input(\INPUT_SERVER, 'http_token', \FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (isset($token_header)) {
                $token = token::Decrypt($token_header);
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
            if ($username !== '') {
                $this->user = users::get_instance()->get($username);
            }
            if ($this->user == null)
                $this->user = user::guest();
        } catch (\Exception) {
            // Bob: Exception is most probably due to incorrect QueryString or cookie value which we will ignore
        }

    }
    #endregion


    #region private function check_access(): bool
    private function check_access(): bool {
        $origin = filter_input(\INPUT_SERVER, 'HTTP_ORIGIN', \FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        // Filter allowed origins from config, if CORS_WHITE_LIST is empty, then all CORS will not be allowed
        if (isset($origin) && $origin != '' && config::CORS_WHITE_LIST != '' && in_array(strtolower($origin), config::CORS_WHITE_LIST)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header("Vary: Origin");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
            $request_method = filter_input(\INPUT_SERVER, 'HTTP_ACCESS_CONTROL_REQUEST_METHOD', \FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (isset($request_method))
                header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
            $request_headers = filter_input(\INPUT_SERVER, 'HTTP_ACCESS_CONTROL_REQUEST_HEADERS', \FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (isset($request_headers))
                header("Access-Control-Allow-Headers: {$request_headers}");
            return true;
        }
        // Filter IP from config, if IP_WHITE_LIST is empty, then all IPs will be allowed
        if (count(config::IP_WHITE_LIST) < 1) {
            return true;
        }
        $remote_addr = filter_input(\INPUT_SERVER, 'REMOTE_ADDR', \FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (isset($remote_addr) && $remote_addr != '' && in_array(strtolower($remote_addr), config::IP_WHITE_LIST)) {
            return true;
        }
        return false;
    }
    #endregion

    #region private function parse_command(...): array|null
    /**
     * @param string $cmd a string containing command with namespace.class.method form
     * @return array|null null if command is not valid, array of method and class if command is valid
     */
    private function parse_command(string $cmd): array|null {
        // Command should end up as a valid php class -> method path
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]+[a-zA-Z0-9_]$/', $cmd) !== 1) {
            return null;
        }
        $cmd = explode('.', 'services.' . $cmd);
        $method = array_pop($cmd);
        if (count($cmd) < 2) {
            return null;
        }
        $class = '\\' . implode('\\', $cmd);
        return [
            'method' => $method,
            'class'  => $class,
        ];
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
        $start_tag = '<meta name="' . $tag_name . '" ';
        $start = stripos($haystack, $start_tag);
        if ($start !== false) {
            // $haystack contains a $startTag
            $end = stripos($haystack, ' />', $start);
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

}
