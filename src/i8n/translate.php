<?php

namespace i8n;

use i8n\translate_exception;
use logger\logger;
use config\config;

class translate {

    #region properties
    public const TRANSLATION_FOLDER = DATA_FOLDER . '/i8n/';
    public const TRANSLATION_MARKER = '#' . '#';

    private static translate $instance;
    private logger $logger;
    private string $default_language;
    private array $languages = [];
    #endregion

    #region public static function get_instance(): self
    public static function get_instance(): self {
        if (!isset(static::$instance)) {
            static::$instance = new static(config::DEFAULT_LANGUAGE);
        }
        return static::$instance;
    }
    #endregion

    #region private function __construct(...)
    private function __construct(string $default_language) {
        $this->default_language = $default_language;
        $this->logger = logger::get_instance();
        if (!is_dir(self::TRANSLATION_FOLDER))
            mkdir(self::TRANSLATION_FOLDER, true);
    }
    #endregion

    #region public function load(...)
    /**
     * Try and load requested language, if it doesn't exist, load the default language instead
     *
     * @param string $language try and load requested language if it is not loaded yet
     * @return string return loaded language name
     */
    public function load(string $language): string {
        if (key_exists($language, $this->languages)) {
            return $language;
        }
        // language is not loaded yet
        $lang_file = self::TRANSLATION_FOLDER . $language . '.json';
        if (!file_exists($lang_file)) {
            // if requested language doesn't exist, change to default language
            $this->logger->debug($language . ' language does not exist');
            $language = $this->default_language;
            $lang_file = self::TRANSLATION_FOLDER . $language . '.json';
        }
        // in case language has changed to default, and it is loaded previously
        if (key_exists($language, $this->languages)) {
            return $language;
        }

        // pity if default language doesn't exist either
        if (!file_exists($lang_file)) {
            $this->languages[$language] = [
                'can_capitalize' => 1,
                'data'           => [],
                'not_exists'     => 1
            ];
            return $language;
        }
        // load language and return
        //$this->logger->debug('loading ' . $language . ' language from file');
        $lang_data = json_decode(file_get_contents($lang_file), JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);

        if ($lang_data['can_capitalize'] == 1) {
            // Language can capitalize so:
            // if key is %%XXXXX%% (all caps) replace with exact value
            // if key is %%Xxxxx%% (first letter capital others lowercase) with title case of the value
            // if key is %%xxxxx%% (all lower case) replace with lower case value
            foreach ($lang_data['data'] as $key => $value) {
                $key1 = strtolower($key);
                $lang_data['data'][$key1] = strtolower($value);
                $lang_data['data'][ucfirst($key1)] = ucfirst($value);
            }
            foreach ($lang_data['data'] as $k => $v) {
                $lang_data['data'][static::TRANSLATION_MARKER . $k . static::TRANSLATION_MARKER] = $v;
                unset($lang_data['data'][$k]);
            }
        }
        uksort($lang_data['data'], 'strcasecmp');
        $this->languages[$language] = $lang_data;
        return $language;
    }
    #endregion

    #region public function translate(...): string
    /**
     * @param string $text A text string to be translated
     * @param string $language destination language
     * @return string translated text
     */
    public function translate(string $text, string $language): string {
        $language = $this->load($language);

        $can_capitalize = $this->languages[$language]['can_capitalize'] === 1;
        $keys = array_keys($this->languages[$language]['data']);
        $values = array_values($this->languages[$language]['data']);
        if (!$can_capitalize) {
            // only replace %%KEY%% with "value"
            return str_ireplace($keys, $values, $text);
        }
        return str_replace($keys, $values, $text);
    }
    #endregion

    #region public function list(...): array
    public function list(string $language): array {
        $lang_file = self::TRANSLATION_FOLDER . $language . '.json';
        if (!file_exists($lang_file)) {
            return [
                'can_capitalize' => 1,
                'data'           => []
            ];
        }
        return $this->languages[$language];
    }
    #endregion

    #region public function save(): array
    /**
     * Saves the language back to file
     *
     * @return bool always true
     */
    public function save(string $language, $lang_data): bool {
        //TODO: move this if to Business logic
        if (strlen($lang_data) <> 2 || !ctype_alpha($lang_data))
            throw new translate_exception('Invalid Language');

        foreach ($this->languages as $language => $data) {
            $lang_file = self::TRANSLATION_FOLDER . $language . '.json';
            if (file_exists($lang_file)) {
                rename($lang_file, $lang_file . '.' . date('Ymd-Hi') . '.bak');
            }
            file_put_contents($lang_file, json_encode($lang_data, JSON_ENCODE_OPTIONS));
            if (key_exists($language, $this->languages))
                unset($this->languages[$language]);
        }
        return true;
    }
    #endregion
}
