<?php

namespace security;

class security_image {

    private const SECURITY_IMAGES_FOLDER = ASSETS_FOLDER . '/security_images/';
    private const RANDOM_WORDS_FILE = DATA_FOLDER . '/random_words.txt';

    #region public static function get_random(...): string
    public static function get_random(string $username): string {

        if (isset($_SESSION["random_images"])) {
            $random_images = $_SESSION["random_images"];
        } else {
            $random_images = [];
        }
        if (key_exists($username, $random_images)) {
            return static::get_security_image($random_images[$username]['i'], $random_images[$username]['p']);
        }
        if (!is_dir(static::SECURITY_IMAGES_FOLDER)) {
            mkdir(static::SECURITY_IMAGES_FOLDER, 755, true);
        }
        $imagefiles = glob(static::SECURITY_IMAGES_FOLDER . '/*.png');
        $num = random_int(0, count($imagefiles) - 1);
        $image_id = str_replace('.png', '', str_replace(static::SECURITY_IMAGES_FOLDER . '/', '', $imagefiles[$num]));

        $word = '';
        if (file_exists(static::RANDOM_WORDS_FILE)) {
            $words = explode(CH_EOL, trim(file_get_contents(static::RANDOM_WORDS_FILE)));
            $word = $words[random_int(0, count($words) - 1)];
        }
        $random_images[$username] = ['p' => $word, 'i' => $image_id];
        $_SESSION["random_images"] = $random_images;
        return static::get_security_image($image_id, $word);
    }
    #endregion

    #region public function get_security_image(): string
    public static function get_security_image(string $security_image, string $security_phrase, $width = 200, $height = 96): string {
        $filename = self::SECURITY_IMAGES_FOLDER . $security_image . '.png';
        $font = ASSETS_FOLDER . '/fonts/roboto/v30/ttf/400.ttf';

        if (file_exists($filename)) {
            $image = @imagecreatefrompng($filename);
            $black = imagecolorallocate($image, 0, 0, 0);
        } else {
            $image = imagecreatetruecolor($width, $height);
            $black = imagecolorallocate($image, 0, 0, 0);
            imagefill($image, 0, 0, $black);
        }
        $white = imagecolorallocate($image, 255, 255, 255);

        // Drop a shadow behind text
        for ($i = 0; $i < 5; $i++) {
            for ($j = 0; $j < 5; $j++) {
                imagettftext($image, 12, 0, 7 - $i, $height - 3 - $j, $white, $font, $security_phrase);
            }
        }
        imagettftext($image, 12, 0, 5, $height - 5, $black, $font, $security_phrase);

        //imagepng($image, '/img.png');
        ob_start();
        imagepng($image);
        imagedestroy($image);
        $image_b64 = base64_encode(ob_get_clean());
        return 'data:image/png;base64,' . $image_b64;
    }
    #endregion


}