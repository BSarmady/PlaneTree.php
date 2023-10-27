<?php

namespace security;

use exceptions\core_exception;
use UUID;

class user_photos {

    #region properties
    private const DATA_URI_MARKER = 'data:image/png;base64,';
    private const USER_PHOTOS_FOLDER = ASSETS_FOLDER . '/user-photos/';
    #endregion

    #region public static function save_from_b64_image_data(...): string
    public static function save_from_b64_image_data(string $photo_data, int $save_width = 128, int $save_height = 128): string {
        if (!str_starts_with($photo_data, static::DATA_URI_MARKER)) {
            throw new core_exception('Image is not a valid png data uri');
        }
        if (!is_dir(static::USER_PHOTOS_FOLDER)) {
            mkdir(static::USER_PHOTOS_FOLDER, 755, true);
        }
        // resize image
        $uploaded_image = imagecreatefromstring(base64_decode(str_replace(static::DATA_URI_MARKER, '', $photo_data)));
        $image = imagecreatetruecolor($save_width, $save_height);
        $orig_width = imagesx($uploaded_image);
        $orig_height = imagesy($uploaded_image);
        $widthScale = $orig_width / $save_width;
        $heightScale = $orig_height / $save_height;
        $scale = max($widthScale, $heightScale);
        $new_width = intval($orig_width / $scale);
        $new_height = intval($orig_height / $scale);
        $padding_x = intval(($save_width - $new_width) / 2);
        $padding_y = intval(($save_height - $new_height) / 2);
        imagecopyresampled($image, $uploaded_image, $padding_x, $padding_y, 0, 0, $new_width, $new_height, $orig_width, $orig_height);
        //save with new uuid as file name
        $id = '';
        do {
            $id = str_replace('-', '', UUID::v4());
            $filename = static::USER_PHOTOS_FOLDER . $id . '.png';
        } while (file_exists($filename));
        if (imagepng($image, $filename) !== false)
            return $id;
        return 0;
    }
    #endregion

    #region public static function get_photo_as_b64_image_data():string
    public static function get_photo_as_b64_image_data(string $photo_id, $width = 128, $height = 128): string {
        if ($photo_id == '')
            $photo_id = 'no-photo';
        $filename = static::USER_PHOTOS_FOLDER . $photo_id . '.png';
        if (!file_exists($filename)) {
            $filename = static::USER_PHOTOS_FOLDER . 'no-photo.png';
        }
        $image = imagecreatetruecolor($width, $height);
        $user_image = imagecreatefrompng($filename);
        list($orig_width, $orig_height) = getimagesize($filename);
        $widthScale = $width / $orig_width;
        $heightScale = $height / $orig_height;
        $scale = max($widthScale, $heightScale);
        $new_width = intval($orig_width * $scale);
        $new_height = intval($orig_height * $scale);
        $padding_x = intval(($width - $new_width) / 2);
        $padding_y = intval(($height - $new_height) / 2);
        imagecopyresampled($image, $user_image, $padding_x, $padding_y, 0, 0, $new_width, $new_height, $orig_width, $orig_height);

        ob_start();
        //header('Content-Type: image/png');// to debug
        imagepng($image);
        imagedestroy($image);
        $image_b64 = base64_encode(ob_get_clean());
        return 'data:image/png;base64,' . $image_b64;
    }
    #endregion

}


