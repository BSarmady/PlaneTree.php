<?php

namespace logger;

use config\config;
use UUID;

class logger {

    #region LOG_LEVEL
    const LOG_LEVEL_DEBUG = 1;
    const LOG_LEVEL_INFO = 2;
    const LOG_LEVEL_WARN = 3;
    const LOG_LEVEL_ERROR = 4;
    const LOG_LEVEL_FATAL = 5;
    #endregion

    #region private properties
    private string $file_name = '';
    private int $level = 1;
    private static string $log_id;
    private static logger $instance;
    #endregion

    #region public static function get_instance(): self
    public static function get_instance(): self {
        if (!isset(static::$instance)) {
            static::$instance = new static(config::LOG_LEVEL);
        }
        return static::$instance;
    }
    #endregion

    #region private function __construct(...): void
    private function __construct(int $level) {
        static::$log_id = substr(str_replace('-', '', UUID::v4()), 16);

        $this->level = $level;
        $this->file_name = LOG_FOLDER . '/' . date('Ymd-H') . '00.txt';
    }
    #endregion

    #region private function combine(...): void
    private function combine(array $input): string {
        return count($input) > 0 ? CH_EOL . json_encode($input, JSON_UNESCAPED_UNICODE) : '';
    }
    #endregion

    #region private function message(...): void
    private function message(string $level, string $message, array|null $params): void {
        $line_prefix = date("H:i:s") . ' ' . static::$log_id . ' ' . $level . ' ';
        $padding = str_pad('', strlen($line_prefix), ' ');

        file_put_contents($this->file_name,
            $line_prefix . str_replace(CH_EOL, CH_EOL . $padding, trim($message) . $this->combine($params)) . CH_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
    #endregion

    #region public function debug(...): void
    public function debug(string $message, array|null $params = []): void {
        if ($this->level <= static::LOG_LEVEL_DEBUG)
            $this->message('DEBUG', $message, $params);
    }
    #endregion

    #region public function info(...): void
    public function info(string $message, $params = []): void {
        if ($this->level <= static::LOG_LEVEL_INFO)
            $this->message(' INFO', $message, $params);
    }
    #endregion

    #region public function warn(...): void
    public function warn(string $message, $params = []): void {
        if ($this->level <= static::LOG_LEVEL_WARN)
            $this->message(' WARN', $message, $params);
    }
    #endregion

    #region public function error(...): void
    public function error(string $message, $params = []): void {
        if ($this->level <= static::LOG_LEVEL_ERROR)
            $this->message('ERROR', $message, $params);
    }
    #endregion

    #region public function fatal(...): void
    public function fatal(string $message, \Exception $ex = NULL, $params = []): void {
        if ($this->level <= static::LOG_LEVEL_FATAL) {
            $message = trim($message . ' ' . $this->combine($params)) . CH_EOL;
            if ($ex instanceof \Exception) {
                do {
                    $message .= $ex;
                } while ($ex = $ex->getPrevious());
            }
            $this->message('FATAL', $message, $params);
        }
    }
    #endregion
}
