<?php

namespace services\admin;

use attributes\authenticate;
use config\config;
use IService;
use logger\logger;
use security\user;

class audit implements IService {

    #region properties
    private const LINES_PER_PAGE = 200;
    /**
     * chunk size in bytes (since it will be in base64 use 3/4 of actual size)
     */
    private const DOWNLOAD_CHUNCK_SIZE = 786432;

    #endegion

    #[authenticate('##GET_LIST##')]
    public function get_list(array $json_req, user $session_user): string {
        $files = glob(LOG_FOLDER . '/*.txt');
        $result = [];
        $size_name = ['B', 'KB', 'MB', 'GB', 'TB'];
        foreach ($files as $v) {
            $name = str_replace(LOG_FOLDER . '/', '', str_replace('.txt', '', $v));
            $size = filesize($v);
            $i = 0;
            while ($size > 1024) {
                $size = $size / 1024;
                $i++;
            }
            $result[$name] = ceil($size) . $size_name[$i];
        }
        krsort($result);
        $result = array_slice($result, 0, 24 * 30);// 30 days max
        return json_encode($result, JSON_ENCODE_OPTIONS);
    }


    #region public get_log(...): string {
    #[authenticate("##GET_LOG##")]
    public function get_log(array $json_req, user $session_user): string {
        if (!isset($json_req['page']) || !isset($json_req['name'])) {
            return '{"error":"##ERROR_INCOMPLETE_DATA##"}';
        }
        $page = intval($json_req['page']);
        $fileName = LOG_FOLDER . '/' . intval(substr($json_req['name'], 0, 8)) . '-' . str_pad(intval(substr($json_req['name'], 9, 4)), 4, '0', STR_PAD_LEFT) . '.txt';
        if (!is_readable($fileName))
            return '{"error":"##ERROR_RECORD_WAS_NOT_FOUND##"}';

        $fHandle = fopen($fileName, 'r');

        $line_counter = 0;
        if ($page == -1) {
            while ((fgets($fHandle)) !== false) {
                $line_counter++;
            }
            $page = floor($line_counter / self::LINES_PER_PAGE);
        }
        if ($page < 0)
            $page = 0;
        $line_counter = -1;
        rewind($fHandle);
        $data = '';
        $skip_lines = $page * self::LINES_PER_PAGE;
        while (($line = fgets($fHandle)) !== false) {
            $line_counter++;
            if ($line_counter < $skip_lines)
                continue;
            if ($line_counter < $skip_lines + self::LINES_PER_PAGE)
                $data .= strlen($line) < 1024 ? $line : substr($line, 0, 1024) . "... (long line " . strlen($line) . " truncated)";
        }
        fclose($fHandle);
        //debug($data);
        return json_encode([
            'total_pages' => floor($line_counter / self::LINES_PER_PAGE),
            'curr_page'   => $page,
            'lines'       => $data
        ], JSON_ENCODE_OPTIONS);
    }
    #endregion

    #region public function download(...): string
    public function download(array $json_req, user $session_user): string {
        $logger = logger::get_instance();
        if (!isset($json_req['name'])) {
            return '{"error":"##ERROR_INCOMPLETE_DATA##"}';
        }
        $chunk_requested = intval($json_req['chunk'] ?? 0);
        $display_name = intval(substr($json_req['name'], 0, 8)) . '-' . str_pad(intval(substr($json_req['name'], 9, 4)), 4, '0', STR_PAD_LEFT) . '.txt';
        $fileName = LOG_FOLDER . '/' . $display_name;
        if (!is_readable($fileName))
            return '{"error":"##ERROR_RECORD_WAS_NOT_FOUND##"}';

        $file_size = filesize($fileName);
        //debug($file_size);
        $chunks_count = intval(filesize($fileName) / self::DOWNLOAD_CHUNCK_SIZE);
        if ($file_size % self::DOWNLOAD_CHUNCK_SIZE != 0) {
            $chunks_count++;
        }
        //debug(self::DOWNLOAD_CHUNCK_SIZE, 'DOWNLOAD_CHUNCK_SIZE');
        //debug($chunks_count , 'chunks_count');
        //debug($chunk_requested, 'chunk_requested');
        $fHandle = fopen($fileName, 'r');
        if ($chunk_requested < 0 || $chunk_requested >= $chunks_count) {
            return '{"error":"##ERROR_OUT_OF_RANGE##"}';
        }
        $logger->info('Downloading file ' . $fileName . ', chunk_requested ' . $chunk_requested);

        fseek($fHandle, $chunk_requested * self::DOWNLOAD_CHUNCK_SIZE);
        //debug($chunk_requested * self::DOWNLOAD_CHUNCK_SIZE, 'seeking to ');
        $contents = fread($fHandle, self::DOWNLOAD_CHUNCK_SIZE);

        return json_encode([
            'size' => $chunks_count,
            'curr' => $chunk_requested,
            'name' => $display_name,
            'data' => base64_encode($contents)
        ], JSON_ENCODE_OPTIONS);
    }
    #endregion


}