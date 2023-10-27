<?php

namespace security;

use crypto\AES256;

class token {
    public string $username;
    public int $date;
    public string $account_type;
    public string $ip;

    #region public token(...)
    public function __construct(string $username, string $account_type, string $ip) {
        $this->username = $username;
        $this->account_type = $account_type;
        $this->ip = $ip;
        $this->date = time();
    }
    #endregion

    #region public string Encrypt()
    public function Encrypt(): string {
        return AES256::Encrypt(
            ENCRYPTION_KEY,
            random_int(10, 99) . ";" .
            ($this->account_type == "A" ? "A" : "U") . ";" .
            $this->username . ";" .
            time() . ";" .
            $this->ip
        );
    }
    #endregion

    #region public token Decrypt(...)
    public static function Decrypt(string $token): token|null {
        try {
            $token_parts = explode(';', AES256::Decrypt(ENCRYPTION_KEY, $token));
            /*
            $token_parts[0] is random number
            $token_parts[1] is account type (A:App, U:User)
            $token_parts[2] is username
            $token_parts[3] is last session update
            $token_parts[4] is last login IP
            */
            if (!is_array($token_parts) || count($token_parts) != 5)
                throw new \Exception(); // token is invalid
            //TODO Add token validity timeout of 20 minutes to config
            if (intval($token_parts[3]) + 3600 * 20 < time() && $token_parts[1] != "A")
                /*
                    A in part[1] is App token which does not expire
                    Note that apps have very limited permissions
                */
                throw new \Exception(); // token is expired
            return new token($token_parts[2], $token_parts[1] == "A" ? "A" : "U", $token_parts[4]);
        } catch (\Exception $ex) {
            return null;
        }
    }
    #endregion
}
