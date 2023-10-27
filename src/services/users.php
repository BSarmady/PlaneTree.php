<?php

namespace services;

use attributes\authenticate;
use attributes\no_translate;
use captcha\captcha;
use config\config;
use crypto\AES256;
use crypto\Hash;
use exceptions\command_exception;
use i8n\translate;
use IService;
use logger\logger;
use security\organizations;
use security\security_image;
use security\token;
use security\user;

class users implements IService {

    #region public function captcha(...): string
    #[no_translate]
    public function captcha(array $json_req, user $user): string {
        return json_encode([
            'captcha' => (config::ENABLE_CAPTCHA ? captcha::get_b64(config::DEFAULT_LANGUAGE) : '')
        ], JSON_ENCODE_OPTIONS);

    }
    #endregion

    #region  public function sign_in(...): string
    public function sign_in(array $request, user $session_user): string {

        #region /*Roles*/
        /* Roles:
            -- Single step login ------------------------
            Username, Password, Captcha if enabled, hashed if coming from mobile device

            Validate Captcha is valid if enabled
            Validate username is not empty
            Validate password is not empty
            hash is optional, if not exist then hash the password
            process login steps
            generate session,
            generate redirect url
            return to user

            -- Two step login ------------------------
            Step 1: (step==1)
            inputs: step, username, captcha if enabled
            Validate Captcha is valid if enabled
            Validate username is not empty
            get user info and store it in session tempuser
            generate auth image
            return auth image and session

            Step 2: (step==2)
            inputs: step, password, session
            verify session is valid
            get user from session
            process login steps
            save user to session store
            generate redirect url
            return to user

        */
        #endregion

        //TODO: throttle login attempts from same IP
        $step = intval($request["step"] ?? "0");
        $username = trim($request["username"] ?? "");
        $password = trim($request["password"] ?? "");
        $hashed = boolval($request["hashed"] ?? "false");
        $captcha = trim($request["captcha"] ?? "");

        #region Validate inputs
        if (config::TWO_STEP_SIGN_IN && $step != 1 && $step != 2) {
            throw new command_exception('##ERROR_INVALID_REQUEST##');
        }

        if (!config::TWO_STEP_SIGN_IN || $step == 1) {
            if ($username == '' || $this->has_invalid_chars($username)) {
                throw new command_exception('##ERROR_INVALID_USERNAME_OR_PASSWORD##');
            }

            if (config::ENABLE_CAPTCHA && !captcha::verify($captcha, config::CAPTCHA_CASE_SENSITIVE)) {
                throw new command_exception('##ERROR_INVALID_CAPTCHA##');
            }
        }

        if (!config::TWO_STEP_SIGN_IN || $step == 2) {
            if ($password == '') {
                throw new command_exception('##ERROR_PASSWORD_IS_EMPTY##');
            }
        }
        #endregion

        $users = \security\users::get_instance();

        // Delete or lock inactive accounts
        $users->set_dormant();

        #region step 1 of two-step login
        if (config::TWO_STEP_SIGN_IN && $step == 1) {
            $user = $users->get($username);
            // Bob: Since user login is not complete, we are only storing username here.
            if ($user == null) {
                return json_encode(["image" => security_image::get_random($username)], JSON_ENCODE_OPTIONS);
            }
            $_SESSION['_username'] = $user->username;
            // Bob: if user doesn't exist, do not throw exception. instead return a random image
            return json_encode([
                'image' => security_image::get_security_image($user->security_image, $user->security_phrase)
            ], JSON_ENCODE_OPTIONS);
        }
        #endregion

        #region step 2 of two-step login
        if (config::TWO_STEP_SIGN_IN && $step == 2) {
            // We check session to ensure, step 2 is not called out of order, even though it would get caught in password checking
            if (!isset($_SESSION['_username'])) {
                throw new command_exception('##ERROR_INVALID_USERNAME_OR_PASSWORD##');
            }
            $username = $_SESSION['_username'];
        }
        #endregion

        #region make sure user exist
        $user = $users->get($username);
        if ($user == null) {
            throw new command_exception('##ERROR_INVALID_USERNAME_OR_PASSWORD##');
        }
        #endregion

        #region verify password
        $SaltedPassword = Hash::sha256($password . $user->password_salt);
        if ($SaltedPassword != $user->password) {
            if (!$user->is_super_admin()) {
                $user->failed_sign_in_count++;
                $users->update_sign_in($username, true);
            }
            if ($user->failed_sign_in_count >= config::MAX_FAILED_SIGN_IN_COUNT) {
                // Lock Account if $FailedLoginCount more than config::MaxLoginAttempt
                throw new command_exception('##ERROR_ACCOUNT_LOCKED_OUT_DUE_TO_MAX_INVALID_PASSWORD##');
            }
            throw new command_exception('##ERROR_INVALID_USERNAME_OR_PASSWORD##');
        }
        #endregion

        // Bob: If password matches do rest of checks in following order (This order is important).
        //      1- Is User Login attempt at max (Lock account too)
        //      2- Check if account is dormant (both new and old)
        //      4- Is User locked
        //      5- Is User Approved

        switch ($user->status) {
            case 'initial':
                throw new command_exception('##ERROR_ACCOUNT_EMAIL_IS_NOT_VERIFIED##');
            case 'verified':
                throw new command_exception('##ERROR_ACCOUNT_IS_NOT_APPROVED##');
            //case 'active': do nothing which means user can login
            case 'dormant':
                throw new command_exception('##ERROR_ACCOUNT_IS_LOCKED_DUE_TO_INACTIVITY##');
            case 'locked':
                throw new command_exception('##ERROR_ACCOUNT_IS_LOCKED_OUT##');
            case 'banned':
                throw new command_exception('##ERROR_ACCOUNT_IS_BANNED##');
            case 'deleted':
                throw new command_exception('##ERROR_INVALID_USERNAME_OR_PASSWORD##');
        }

        // All good, we can sign in now.
        $login_unique_id = \UUID::v4();
        $users->update_sign_in($username, false, $login_unique_id);
        $_SESSION['LID'] = $login_unique_id;
        $_SESSION['username'] = $user->username;

        // Check if password is expired
        //TODO check this with sso, looks like this one is changed
        $redirects = [];

        $redirects[] = '/';
        if (strtotime($user->date_pwd_changed . ' +' . config::PASSWORD_EXPIRES_IN_DAYS . ' day') < time()) {
            //redirect to change password
            //'Password is expired, You need to change your password.';
            $redirects[] = '/users/user_profile#ChangePassword';
        }
        if (config::TWO_STEP_SIGN_IN && ($user->security_image == '' || $user->security_phrase == '')) {
            //authorization image is not set
            //redirect to select auth image
            $redirects[] = '/users/user_profile#SecurityImage';
        }
        $result = [
            'success'  => 'success',
            'redirect' => array_pop($redirects),
            'token'    => (new token($user->username, $user->account_type, ''))->Encrypt(),
            'message'  => '##MESSAGE_SIGN_IN_SUCCESS##'
        ];
        //save remaining $redirects to session
        $_SESSION['redirects'] = $redirects;
        return json_encode($result, JSON_ENCODE_OPTIONS);
    }
    #endregion

    #region public function sign_out(...): string
    public function sign_out(array $request, user $user): string {
        session_destroy();
        return '{"message":"##SUCCESS##", "redirect":"/"}';
        //return '{"message":"##SUCCESS##", "redirect":"/sign-in"}';
    }
    #endregion

    #region public function user_profile(...): string
    #[authenticate("Get User's Profile Info")]
    public function user_profile(array $request, user $user): string {
        $data = [
            'user'      => $this->users_info($user),
            'languages' => ['en']
        ];
        if (config::ENABLE_QA_RECOVERY) {
            $data['qa_list'] = $this->get_qa_list($user->username);
        }
        if (config::TWO_STEP_SIGN_IN) {
            $data['security_images'] = $this->get_security_images();
        }
        return json_encode($data, JSON_ENCODE_OPTIONS);
    }
    #endregion

    #region public function set_personal(...)
    #[authenticate("Change Personal Info")]
    public function set_personal(array $request, user $user): string {
        //TODO complete this
        $display_name = $request["display_name"];
        $mobile_no = $request["mobile_no"];
        $email = $request["email"];
        $language = $request["language"];

        $users = \security\users::get_instance();
        $users->set_personal(
            $user->username,
            $display_name,
            $mobile_no,
            $email,
            $language
        );
        return "{\"message\":\"success\"}";
    }
    #endregion

    #region public bool set_qa(...)
    #[authenticate("Set Password Recovery Questions")]
    public function set_qa(array $request, user $user): string {
        //TODO complete this
        /*
        {"cmd":"users.set_qa",
        "qa":[
            {"q":"q1", "a":"a1"},
            {"q":"q2", "a":"a2"},
            {"q":"q3", "a":"a3"}
        ]
        }
        */
        if (!isset($request["qa"]) || count($request["qa"]) != 3) {
            throw new command_exception("##ERROR_SELECT_3_SET_QA##");
        }
        $answers = [];
        foreach ($request["qa"] as $qu) {
            if (trim($qu['a']) == "" || trim($qu['q']) == "") {
                throw new command_exception("##ERROR_RECOVERY_QA_EMPTY##");
            }
            if (in_array($qu['a'], $answers) || key_exists($qu['q'], $answers)) {
                throw new command_exception("##ERROR_RECOVERY_QA_DUPLICATE##");
            }
            $answers[$qu['q']] = $qu['a'];
        }
        foreach ($answers as $q => $a) {
            $answers[$q] = AES256::Encrypt(ENCRYPTION_KEY, $a);
        }
        $users = \security\users::get_instance();
        $users->set_recovery_qa($user->username, $answers);
        return '{"message":"##SUCCESS##"}';// will throw if not success
    }
    #endregion

    #region public string set_security_info(...)
    #[authenticate("Change Security Image")]
    public function set_security_info(array $request, user $user): string {
        if (!isset($_SESSION["security_images"]) || count($_SESSION["security_images"]) != 16) {
            throw new command_exception("##ERROR_SECURITY_IMAGE_SELECTION_FAILED##");
        }
        $images = $_SESSION["security_images"];
        if (!isset($request["code"])) {
            throw new command_exception("##ERROR_SELECT_SECURITY_IMAGE##");
        }
        if (!isset($request["phrase"])) {
            throw new command_exception("##ERROR_ENTER_SECURITY_PHRASE##");
        }
        $security_image = $request["code"];
        $security_phrase = trim($request["phrase"]);
        if ($security_phrase == "") {
            throw new command_exception("##ERROR_ENTER_SECURITY_PHRASE##");
        }
        if (!in_array($security_image, $images)) {
            throw new command_exception("##ERROR_SELECT_SECURITY_IMAGE##");
        }

        $users = \security\users::get_instance();
        $users->set_security_image($user->username, $security_image, $security_phrase);
        if ($_SESSION["redirects"] != null) {
            $redirects = $_SESSION["redirects"];
            $redirect = array_pop($redirects);
            if (count($redirects) > 0) {
                $_SESSION["redirects"] = $redirects;
            } else {
                unset($_SESSION["redirects"]);
            }
            return json_encode([
                'message'  => '##SUCCESS##',
                'redirect' => $redirect
            ], JSON_ENCODE_OPTIONS);
        }
        return '{"message":"##SUCCESS##"}';
    }
    #endregion

    #region public string change_password(...)
    #[authenticate("Change Password")]
    public function change_password(array $request, user $user): string {
        //TODO complete this
        $current_password = $request["cur"] ?? '';
        $new_password = $request["new"] ?? '';
        if ($current_password == "")
            throw new command_exception("##ERROR_OLD_PASSWORD_IS_EMPTY##");
        if ($new_password == "")
            throw new command_exception("##ERROR_NEW_PASSWORD_IS_EMPTY##");
        if ($current_password == $new_password)
            throw new command_exception("##ERROR_NEW_PASSWORD_SAME_AS_OLD##");
        $salted_current_password = Hash::sha256($current_password . $user->password_salt);
        if ($salted_current_password != $user->password)
            throw new command_exception("Current password doesn't match");

        $this->is_weak_password($new_password);     // Check with dictionary
        $this->is_valid_password($new_password);    // This method will throw errors itself.

        $new_salt = hash::sha256(rand(10000, 99999));
        $salted_new_password = Hash::sha256($new_password . $new_salt);
        $users = \security\users::get_instance();
        $users->set_password($user->username, $salted_new_password, $new_salt);
        return "{\"message\":\"success\"}";
    }
    #endregion

    #region private function has_invalid_chars(...): bool
    private function has_invalid_chars(string $str): bool {
        //TODO change function to allow different character sets for site default languages e.g. russian + english for russian language website
        foreach (mb_str_split($str) as $ch) {
            if (!str_contains('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_-.@', $ch)) {
                return true;
            }
        }
        return false;
    }
    #endregion

    #region private function users_info(...):array
    private function users_info(user $user): array {
        $user_org = organizations::get_instance()->get($user->organization_id);

        $user_info = [
            'organization'      => $user_org->name,
            'organization_name' => $user_org->description,
            'account_type'      => $user->account_type,
            'username'          => $user->username,
            'real_name'         => $user->real_name,
            'mobile_no'         => $user->mobile_no,
            'email'             => $user->email,
            'language'          => $user->language,
            'photo'             => $user->photo,
            'roles'             => $user->roles
        ];
        if (config::ENABLE_QA_RECOVERY) {
            $users = \security\users::get_instance();
            $user_qa = $users->get_recovery_qa($user->username);
            $qa = [];
            foreach ($user_qa as $qu) {
                try {
                    $decrypted_answer = AES256::Decrypt(ENCRYPTION_KEY, $qu['answer']);
                    if ($decrypted_answer === false) {
                        $decrypted_answer = '[answer is hashed]';
                    }
                    $qa[] = [
                        'q' => $qu['question'],
                        'a' => $decrypted_answer
                    ];
                } catch (\Exception $ex) {
                }
            }
            $user_info['qa'] = $qa;
        }
        return $user_info;
    }
    #endregion

    #region private function get_qa_list(...)
    private function get_qa_list(string $username): array {
        $recovery_qa_file = DATA_FOLDER . '/recovery_qa.txt';
        $qa = [];
        if (file_exists($recovery_qa_file)) {
            $qa = explode(CH_EOL, trim(file_get_contents($recovery_qa_file)));
        }
        $users = \security\users::get_instance();
        $user_qa = $users->get_recovery_qa($username);
        foreach ($user_qa as $k => $v) {
            if (!in_array($v['question'], $qa))
                $qa[] = $v['question'];
        }
        return $qa;
    }
    #endregion

    #region private function get_security_images(...)
    private function get_security_images(): array {
        $logger = logger::get_instance();
        if (isset($_SESSION["security_images"])) {
            $images = $_SESSION["security_images"];
        } else {
            $images = [];
            // Select 16 random image from available images
            $images_path = ASSETS_FOLDER . '/security_images';
            if (!is_dir($images_path)) {
                mkdir($images_path, 755, true);
            }
            $imagefiles = glob($images_path . '/*.png');
            if (count($imagefiles) < 16) {
                $logger->error("not enough security images in " . $images_path . ", expecting at least 16");
                return [];
            }
            $numbers = [];
            while (count($numbers) < 16) {
                $num = random_int(0, count($imagefiles) - 1);
                if (!in_array($num, $numbers))
                    $numbers[] = $num;
            }
            foreach ($numbers as $v) {
                $images[] = str_replace('.png', '', str_replace($images_path . '/', '', $imagefiles[$v]));
            }
            $_SESSION["security_images"] = $images;
        }
        return $images;
    }
    #endregion

    #region private string IsWeakPassword(...)
    private function is_weak_password(string $password): void {
        $password_dictionary = DATA_FOLDER . '/password_dictionary.txt';
        $weak_passwords = [];
        if (file_exists($password_dictionary)) {
            $weak_passwords = explode(CH_EOL, trim(file_get_contents($password_dictionary)));
        }
        if (in_array($password, $weak_passwords))
            throw new command_exception("##ERROR_WEAK_PASSWORD##");
    }
    #endregion

    #region private function is_valid_password(...)
    private function is_valid_password(string $password): void {
        $translate = translate::get_instance();
        if (strlen($password) < config::PASSWORD_MIN_LEN)
            throw new command_exception(sprintf($translate->translate("##ERROR_PASSWORD_TOO_SHORT##", config::DEFAULT_LANGUAGE), config::PASSWORD_MIN_LEN)); // "Password is too short. Minimum password length is {0}"
        if (strlen($password) > config::PASSWORD_MAX_LEN)
            throw new command_exception(sprintf($translate->translate("##ERROR_PASSWORD_TOO_LONG##", config::DEFAULT_LANGUAGE), config::PASSWORD_MAX_LEN)); // "Password is too long. Maximum password length is {0}"
        /*
        // This is invalid for websites with languages other than english
        $count_digits = 0;
        $count_symbols = 0;
        $count_lowercase = 0;
        $count_uppercase = 0;
        foreach (mb_str_split($password) as $ch) {
            if (ctype_digit($ch)) {
                $count_digits++;
            } else if (ctype_upper($ch)) {
                $count_uppercase++;
            } else if (ctype_lower($ch)) {
                $count_lowercase++;
            } else if (ctype_graph($ch)) {
                $count_symbols++;
            }
        }
        if ($count_uppercase < config::PASSWORD_MIN_UPPERCASE)
            throw new command_exception(sprintf("Password should include minimum %s %s",config::PASSWORD_MIN_UPPERCASE, "Uppercase letters")); //"Password should include minimum {0} {1}"
        if ($count_lowercase < config::PASSWORD_MIN_LOWERCASE)
            throw new command_exception(sprintf("Password should include minimum %s %s", config::PASSWORD_MIN_LOWERCASE, "Lowercase letters")); //"Password should include minimum {0} {1}"
        if ($count_digits < config::PASSWORD_MIN_DIGITS)
            throw new command_exception(sprintf("Password should include minimum %s %s", config::PASSWORD_MIN_DIGITS, "Digits")); //"Password should include minimum {0} {1}"
        if ($count_symbols < config::PASSWORD_MIN_SYMBOLS)
            throw new command_exception(sprintf("Password should include minimum %s %s", config::PASSWORD_MIN_SYMBOLS, "Symbols")); //"Password should include minimum {0} {1}"
        */
    }
    #endregion

}