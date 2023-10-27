<?php

namespace white_list;

use config\config;
use logger\logger;

class white_list {

    #region properties
    private static white_list $instance;
    #endregion

    #region public static function get_instance(): static
    public static function get_instance(): static {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }
    #endregion

    #region private function __construct(...)
    private function __construct() {
    }
    #endregion

    #region public function __construct(...)
    public function check(string $IP): bool {
        $logger = logger::get_instance();
        if ($IP == '') {
            return true;
        }
        if (count(config::IP_BLACK_LIST) > 0 && in_array($IP, config::IP_BLACK_LIST)) {
            return false;
        }
        if (count(config::IP_WHITE_LIST) > 0 && !in_array($IP, config::IP_WHITE_LIST)) {
            return false;
        }
        return true;
    }
    #endregion


}