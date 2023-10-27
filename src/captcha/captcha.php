<?php

namespace captcha;

use config\config;

const CAPTCHA_SESSION_VAR = 'CAPTCHA';
class captcha {

    #region public static function get_b64(...)
    public static function get_b64($language = 'digits', $digits = 6, $width = 200, $height = 96) {
        if ($language == 'digits') {
            $fonts = glob(__DIR__ . "/en/*.ttf");
            //$fonts = glob(__DIR__ . "/fa/*.ttf");
            $chars = mb_str_split('0123456789');
        } else if ($language == 'fa') {
            $fonts = glob(__DIR__ . "/fa/*.ttf");
            $chars = mb_str_split('آبپتثجچحخدذرزژسشصضطظعغفقکگلمنوهى');
        } else {
            $fonts = glob(__DIR__ . "/en/*.ttf");
            $chars = mb_str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
        }
        $text = [];
        for ($i = 0; $i < $digits; $i++) {
            $text[] = $chars[random_int(0, count($chars) - 1)];
        }
        $_SESSION[CAPTCHA_SESSION_VAR] = implode('', $text);

        $arr_fg_color = [random_int(65, 127), random_int(65, 127), random_int(65, 127)];
        $arr_bg_color = [random_int(191, 255), random_int(191, 255), random_int(191, 255)];
        $arr_txt_color = [random_int(32, 48), random_int(32, 48), random_int(32, 48)];
        $bg_index = random_int(0, 46);

        $captcha_image = imagecreatetruecolor($width, $height);
        $bg_color = imagecolorallocate($captcha_image, $arr_bg_color[0], $arr_bg_color[1], $arr_bg_color[2]);
        imagefilledrectangle($captcha_image, 0, 0, $width, $height, $bg_color);

        $captcha_bg = str_replace('\\', '/', __DIR__ . '/captcha_bg.png');
        $bg_image = @imagecreatefrompng($captcha_bg);

        if ($bg_image) {
            // add a random background
            $bg_color = imagecolorallocate($captcha_image, $arr_bg_color[0], $arr_bg_color[1], $arr_bg_color[2]);
            imagecolorset($bg_image, 1, $arr_bg_color[0], $arr_bg_color[1], $arr_bg_color[2]);
            imagecolorset($bg_image, 0, $arr_fg_color[0], $arr_fg_color[1], $arr_fg_color[2]);
            imagecopy($captcha_image, $bg_image, 0, 0, 0, $bg_index * 96, $width, $height);
        }

        $text_color = imagecolorallocate($captcha_image, $arr_txt_color[0], $arr_txt_color[1], $arr_txt_color[2]);
        $spacing = intval($width / ($digits + 1));
        $x = intval($spacing / 2);
        for ($i = 0; $i < $digits; $i++) {
            $size = random_int(intval($spacing / 2), intval($spacing / 4 * 3));
            $font = $fonts[random_int(0, count($fonts) - 1)];
            $pos_y = intval(($height + $size + random_int(-45, 45)) / 2); // -30~30 from center horizon
            $angle = random_int(-6, 6) * 5; // -30~30 degree angle
            imagettftext($captcha_image, $size, $angle, $x, $pos_y, $text_color, $font, $text[$i]);
            $x += $spacing;
        }
        ob_start();
        imagepng($captcha_image);
        imagedestroy($captcha_image);
        $image_b64 = base64_encode(ob_get_clean());
        return 'data:image/png;base64,' . $image_b64;
    }
    #endregion

    #region public static function verify(...)
    public static function verify($captcha_code, bool $case_sensitive = false): bool {
        return
            !empty($_SESSION[CAPTCHA_SESSION_VAR]) &&
            $case_sensitive ?
                $captcha_code == $_SESSION[CAPTCHA_SESSION_VAR] :
                strcasecmp($captcha_code, $_SESSION[CAPTCHA_SESSION_VAR]);
    }
    #endregion

    #region public static function test(...)
    public static function test($digits = 6, $language = 'en', $width = 1280, $height = 2960) {
        $chars = [
            //'𝄢𝄠𝄞𝄫𝄯𝄭𝄲𝄿𝄾𝅗𝅥 𝅘𝅥𝅘𝅗𝅘𝅥𝅮𝅘𝅥𝅯𝅘𝅥𝅰𝆘𝆺𝅥𝅮𝆹𝅥𝅮𝇗 𝇖𝇛𝇊𝆚𝄝𝄘𝄆𝄏𝄩𝅜',
            //'ఴᦎᦲᱫᱝᳩ⊗⊛⌨⌚⌛⍝⏱⏲⓲◕◑☊☋☙☢♛♞♳♴♵♶♷♸♹♺⚐⚖⚇⚽⚾⛀✀ꔮꕤ',
            //'𒑊𒑋𒑌𒑍𒑎𒑏𒑐𒑑𒑒𒑓𒑔𒑕𒑖𒑗𒑘𒑙𒑚𒑛𒑜𒑝𒑞𒑟𒑠𒑡𒑢𒑣𒑤𒑥𒑦𒄕𒃲𒀾𒀅𒀶𒂊𒄩𒅖𒆩𒈛𒉿',
            //'𓀆𓀃𓀢𓀹𓁀𓁓𓁫𓁻𓁼𓂀𓂱𓂶𓃗𓃡𓃙𓃓𓃭𓄣𓅀𓅐𓅟𓅳𓆃𓆊𓆘𓆦𓆧𓎹𓏢𓇌𓆾𓆣𓆏𓄜𓂿𓂈𓁟𓁚𓁔𓁁',
            //'🍎🍓🍆🌻🌵🌷🌍🌎🌞🌜🍄🍖🍐🍋🍊🍅🍕🍚🍦🍤🍭🍬🎄🎃🎸🏅🏆🏀🐓🐇🐸🐶🐭🐘👦👧💊💔👽💩',
            'en' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'fa' => 'آبپتثجچحخدذرزژسشصضطظعغفقکگلمنوهى'
        ];
        if ($language == 'fa') {
            $fonts = glob(__DIR__ . "/fa/*.ttf");
            $chars = mb_str_split($chars['fa']);
        } else {
            $fonts = glob(__DIR__ . "/en/*.ttf");
            $chars = mb_str_split($chars['en']);
        }

        $arr_fg_color = [0, 0, 0];
        $arr_bg_color = [255, 255, 255];

        $captcha_image = imagecreatetruecolor($width, $height);
        $bg_color = imagecolorallocate($captcha_image, $arr_bg_color[0], $arr_bg_color[1], $arr_bg_color[2]);
        imagefilledrectangle($captcha_image, 0, 0, $width, $height, $bg_color);

        $text_color = imagecolorallocate($captcha_image, $arr_fg_color[0], $arr_fg_color[1], $arr_fg_color[2]);
        $fonts = glob(__DIR__ . '/' . $language . '/*.ttf');
        $pos_y = 20;
        $size = 15;
        foreach ($fonts as $k2 => $font) {
            $pos_x = 10;
            $pos_y += 40;
            imagettftext($captcha_image, $size, 0, $pos_x, $pos_y, $text_color, $font, $font);
            $pos_y += 40;
            foreach ($chars as $k1 => $char) {
                $pos_x += 40;
                if ($pos_x > 1200) {
                    $pos_y += 40;
                    $pos_x = 40;
                }
                imagettftext($captcha_image, $size, 0, $pos_x, $pos_y, $text_color, $font, $char);
            }

        }

        //if (!isset($_SESSION[CAPTCHA_SESSION_VAR]) || $_SESSION[CAPTCHA_SESSION_VAR] === NULL)
        //    $_SESSION[CAPTCHA_SESSION_VAR] = openssl_random_pseudo_bytes(6);

        $_img = imagecreatetruecolor(200, 96);
        imagealphablending($_img, false);
        imagesavealpha($_img, true);


        header('content-type:image/png');
        imagepng($captcha_image);
        /*
        ob_start();
        imagepng($image);
        imagedestroy($image);
        $image_b64 = base64_encode(ob_get_clean());
        return 'data:image/png;base64,' . $image_b64;
        */
    }
    #endregion

    #region public static function get(...)
    public static function get($digits = 6, $language = 'fa', $width = 200, $height = 96): void {
        if ((date('md') == '0401') && random_int(0, 4) == 4) {
            // %20 chance of prank in April 1st
            $fonts = glob(__DIR__ . "/prank/*.ttf");
            $chars = [
                '𝄢𝄠𝄞𝄫𝄯𝄭𝄲𝄿𝄾𝅗𝅥 𝅘𝅥𝅘𝅗𝅘𝅥𝅮𝅘𝅥𝅯𝅘𝅥𝅰𝆘𝆺𝅥𝅮𝆹𝅥𝅮𝇗 𝇖𝇛𝇊𝆚𝄝𝄘𝄆𝄏𝄩𝅜',
                'ఴᦎᦲᱫᱝᳩ⊗⊛⌨⌚⌛⍝⏱⏲⓲◕◑☊☋☙☢♛♞♳♴♵♶♷♸♹♺⚐⚖⚇⚽⚾⛀✀ꔮꕤ',
                '𒑊𒑋𒑌𒑍𒑎𒑏𒑐𒑑𒑒𒑓𒑔𒑕𒑖𒑗𒑘𒑙𒑚𒑛𒑜𒑝𒑞𒑟𒑠𒑡𒑢𒑣𒑤𒑥𒑦𒄕𒃲𒀾𒀅𒀶𒂊𒄩𒅖𒆩𒈛𒉿',
                '𓀆𓀃𓀢𓀹𓁀𓁓𓁫𓁻𓁼𓂀𓂱𓂶𓃗𓃡𓃙𓃓𓃭𓄣𓅀𓅐𓅟𓅳𓆃𓆊𓆘𓆦𓆧𓎹𓏢𓇌𓆾𓆣𓆏𓄜𓂿𓂈𓁟𓁚𓁔𓁁',
                '🍎🍓🍆🌻🌵🌷🌍🌎🌞🌜🍄🍖🍐🍋🍊🍅🍕🍚🍦🍤🍭🍬🎄🎃🎸🏅🏆🏀🐓🐇🐸🐶🐭🐘👦👧💊💔👽💩'
            ];
            $chars = $chars[random_int(0, count($chars) - 1)];
            $chars = mb_str_split($chars);
        } else if ($language == 'fa') {
            $fonts = glob(__DIR__ . "/fa/*.ttf");
            $chars = 'ابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهى';
            $chars = mb_str_split($chars);
        } else {
            $fonts = glob(__DIR__ . "/en/*.ttf");
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $chars = mb_str_split($chars);
        }
        $text = [];
        for ($i = 0; $i < $digits; $i++) {
            $text[] = $chars[random_int(0, count($chars) - 1)];
        }
        $_SESSION[CAPTCHA_SESSION_VAR] = $text;

        $arr_fg_color = [random_int(65, 127), random_int(65, 127), random_int(65, 127)];
        $arr_bg_color = [random_int(191, 255), random_int(191, 255), random_int(191, 255)];
        $arr_txt_color = [random_int(32, 65), random_int(32, 65), random_int(32, 65)];
        $bg_index = random_int(0, 46);

        $captcha_image = imagecreatetruecolor($width, $height);
        $bg_color = imagecolorallocate($captcha_image, $arr_bg_color[0], $arr_bg_color[1], $arr_bg_color[2]);
        imagefilledrectangle($captcha_image, 0, 0, $width, $height, $bg_color);

        $captcha_bg = str_replace('\\', '/', __DIR__ . '/captcha_bg.png');
        $bg_image = @imagecreatefrompng($captcha_bg);

        if ($bg_image) {
            // add a random background
            $bg_color = imagecolorallocate($captcha_image, $arr_bg_color[0], $arr_bg_color[1], $arr_bg_color[2]);
            imagecolorset($bg_image, 0, $arr_bg_color[0], $arr_bg_color[1], $arr_bg_color[2]);
            imagecolorset($bg_image, 1, $arr_fg_color[0], $arr_fg_color[1], $arr_fg_color[2]);
            imagecopy($captcha_image, $bg_image, 0, 0, 0, $bg_index * 96, $width, $height);
        }

        $text_color = imagecolorallocate($captcha_image, $arr_txt_color[0], $arr_txt_color[1], $arr_txt_color[2]);
        $spacing = ($width - (10 * ($digits + 1))) / ($digits + 1);
        $x = $spacing / 2;
        for ($i = 0; $i < $digits; $i++) {
            $size = random_int(15, 25);
            $font = $fonts[random_int(0, count($fonts) - 1)];
            $pos_y = ($height + $size + random_int(-30, 30)) / 2; // -30~30 from center horizon
            $angle = random_int(-8, 8) * 5; // -40~40 degree angle
            imagettftext($captcha_image, $size, $angle, $x, $pos_y, $text_color, $font, $text[$i]);
            $x += $i + $spacing + 10;
        }

        header('content-type:image/png');
        imagepng($captcha_image);
    }
    #endregion
}

?>