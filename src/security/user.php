<?php

namespace security;

use config\config;
use exceptions\core_exception;
use exceptions\db_exception;
use logger\logger;
use UUID;

class user {

    #region Properties
    /**
     * [MUL] int(11)
     * default 0
     *
     * @var int Organization ID
     */
    public int $organization_id;

    /**
     * enum('a','u'), utf8mb3_general_ci
     * default 'u'
     *
     * @var string
     */
    public string $account_type;

    /**
     * [PK] varchar(32), utf8mb3_general_ci
     *
     * @var string
     */
    public string $username;

    /**
     * varchar(50), utf8mb3_general_ci
     *
     * @var string
     */
    public string $real_name;

    /**
     * [MUL] varchar(16), utf8mb3_general_ci
     *
     * @var string
     */
    public string $mobile_no;

    /**
     * [UNI] varchar(50), utf8mb3_general_ci
     *
     * @var string
     */
    public string $email;

    /**
     * varchar(44), utf8mb3_general_ci
     *
     * @var string|null
     */
    public string|null $photo;

    /**
     * varchar(2), utf8mb3_general_ci
     *
     * @var string
     */
    public string $language;

    /**
     * varchar(64), utf8mb3_general_ci
     *
     * @var string
     */
    public string $password;

    /**
     * varchar(44), utf8mb3_general_ci
     *
     * @var string
     */
    public string $password_salt;

    /**
     * datetime
     *
     * @var string|null
     */
    public string|null $date_pwd_changed;

    /**
     * varchar(36), utf8mb3_general_ci
     *
     * @var string
     */
    public string $security_image;

    /**
     * varchar(32), utf8mb3_general_ci
     *
     * @var string
     */
    public string $security_phrase;

    /**
     * varchar(100), utf8mb3_general_ci
     *
     * @var array
     */
    public array $roles;

    /**
     * varchar(4000), utf8mb3_general_ci
     *
     * @var array
     */
    public array $permissions;

    /**
     * varchar(64), utf8mb3_general_ci
     *
     * @var string
     */
    public string $unique_login_id;

    /**
     * int(11)
     * default 0
     *
     * @var int
     */
    public int $failed_sign_in_count;

    /**
     * datetime
     *
     * @var string|null
     */
    public string|null $date_signed_in;

    /**
     * datetime
     *
     * @var string|null
     */
    public string|null $date_locked_out;

    /**
     * enum('initial','verified','approved','active','dormant','banned','deleted'), utf8mb3_general_ci
     *
     * @var string
     */
    public string $status;

    /**
     * varchar(32), utf8mb3_general_ci
     *
     * @var string
     */
    public string $created_by;

    /**
     * datetime
     * default current_timestamp()
     *
     * @var string|null
     */
    public string|null $date_created;

    /**
     * varchar(32), utf8mb3_general_ci
     *
     * @var string
     */
    public string $modified_by;

    /**
     * datetime
     *
     * @var string|null
     */
    public string|null $date_modified;

    /**
     * varchar(400), utf8mb3_general_ci
     *
     * @var string
     */
    public string $comment;

    /**
     * Internally used to keep all user permissions from roles and explicit
     */
    private array $all_permissions;
    #endregion

    #region public function __construct(...)
    /**
     * user Constructor
     */
    public function __construct() {
        $this->organization_id = -1;
        $this->account_type = 'u';
        $this->username = '';
        $this->real_name = '';
        $this->mobile_no = '';
        $this->email = '';
        $this->photo = '';
        $this->language = config::DEFAULT_LANGUAGE;
        $this->password = UUID::v4();
        $this->password_salt = UUID::v4();
        $this->date_pwd_changed = null;
        $this->security_image = '';
        $this->security_phrase = '';
        $this->roles = [];
        $this->permissions = [];
        $this->unique_login_id = '';
        $this->failed_sign_in_count = 0;
        $this->date_signed_in = null;
        $this->date_locked_out = null;
        $this->status = '';
        $this->created_by = '';
        $this->date_created = null;
        $this->modified_by = '';
        $this->date_modified = null;
        $this->comment = '';
        $this->all_permissions = [];
    }
    #endregion

    #region public function from_array(...)
    /**
     * Used to convert returned record array from PDO to (user) class
     * @param array $record Array containing user properties
     * @throws db_exception
     */
    public static function from_array(array $record): user {
        if ($record == []) {
            throw new db_exception('##ERROR_ARRAY_IS_EMPTY##    ');
        }
        if (!key_exists('username', $record) || $record['username'] == '') {
            throw new db_exception('##ERROR_ARRAY_DOES_NOT_HAVE_NAME_KEY##');
        }
        $user = new user();
        $user->organization_id = $record['organization_id'] ?? $user->organization_id;
        $user->account_type = $record['account_type'] ?? $user->account_type;
        $user->username = $record['username'] ?? $user->username;
        $user->real_name = $record['real_name'] ?? $user->real_name;
        $user->mobile_no = $record['mobile_no'] ?? $user->mobile_no;
        $user->email = $record['email'] ?? $user->email;
        $user->photo = $record['photo'] ?? $user->photo;
        $user->language = $record['language'] ?? $user->language;
        $user->password = $record['password'] ?? $user->password;
        $user->password_salt = $record['password_salt'] ?? $user->password_salt;
        $user->date_pwd_changed = $record['date_pwd_changed'] ?? $user->date_pwd_changed;
        $user->security_image = $record['security_image'] ?? $user->security_image;
        $user->security_phrase = $record['security_phrase'] ?? $user->security_phrase;
        $user->roles = isset($record['roles']) ? explode(';', trim($record['roles'], ';')) : $user->roles;
        $user->permissions = isset($record['permissions']) ? explode(';', trim($record['permissions'], ';')) : $user->permissions;
        $user->unique_login_id = $record['unique_login_id'] ?? $user->unique_login_id;
        $user->failed_sign_in_count = $record['failed_sign_in_count'] ?? $user->failed_sign_in_count;
        $user->date_signed_in = $record['date_signed_in'] ?? $user->date_signed_in;
        $user->date_locked_out = $record['date_locked_out'] ?? $user->date_locked_out;
        $user->status = $record['status'] ?? $user->status;
        $user->created_by = $record['created_by'] ?? $user->created_by;
        $user->date_created = $record['date_created'] ?? $user->date_created;
        $user->modified_by = $record['modified_by'] ?? $user->modified_by;
        $user->date_modified = $record['date_modified'] ?? $user->date_modified;
        $user->comment = $record['comment'] ?? $user->comment;
        $user->update_permission();
        return $user;
    }
    #endregion

    #region public function get_all_permissions():array
    public function get_all_permissions(): array {
        return $this->all_permissions;
    }
    #endregion

    #region public function as_array(...): array
    /**
     * @return array user information as array
     */
    public function as_array(): array {
        return [
            'organization_id'      => $this->organization_id,
            'account_type'         => $this->account_type,
            'username'             => $this->username,
            'real_name'            => $this->real_name,
            'mobile_no'            => $this->mobile_no,
            'email'                => $this->email,
            'photo'                => $this->photo,
            'language'             => $this->language,
            'password'             => $this->password,
            'password_salt'        => $this->password_salt,
            'date_pwd_changed'     => $this->date_pwd_changed,
            'security_image'       => $this->security_image,
            'security_phrase'      => $this->security_phrase,
            'roles'                => ';' . implode(';', $this->roles) . ';',
            'permissions'          => ';' . implode(';', $this->permissions) . ';',
            'unique_login_id'      => $this->unique_login_id,
            'failed_sign_in_count' => $this->failed_sign_in_count,
            'date_signed_in'       => $this->date_signed_in,
            'date_locked_out'      => $this->date_locked_out,
            'status'               => $this->status,
            'created_by'           => $this->created_by,
            'date_created'         => $this->date_created,
            'modified_by'          => $this->modified_by,
            'date_modified'        => $this->date_modified,
            'comment'              => $this->comment
        ];
    }
    #endregion

    #region public function guest(...)
    /**
     * Returns a random guest user account
     *
     * @return user a guest user
     */
    public static function guest(): user {
        $user = new user();
        $user->username = mb_strimwidth(str_replace('-', '', UUID::v4()), 0, 32);
        $user->real_name = '##Guest##';
        $user->language = config::DEFAULT_LANGUAGE;
        $user->roles = [config::GUEST_ROLE];
        $user->status = 'active';
        $user->unique_login_id = UUID::v4();
        return $user;
    }
    #endregion

    #region public function is_super_admin(): bool
    /**
     * determines if user is a member of super admin group
     *
     * @return bool return true if user is super admin
     */
    public function is_super_admin(): bool {
        return in_array(config::SUPER_ADMIN_ROLE, $this->roles);
    }
    #endregion

    #region public function is_guest(): bool
    /**
     * determines if user is a member of super admin group
     *
     * @return bool return true if user is super admin
     */
    public function is_guest(): bool {
        return in_array(config::GUEST_ROLE, $this->roles) && count($this->roles) == 1;
    }
    #endregion

    #region public function has_permission(...): bool
    /**
     * If user has specific permission
     *
     * @param string $PermissionId permission id to check in user permissions
     * @return bool true if user has permission
     */
    public function has_permission(string $PermissionId): bool {
        //Bob: check permission for active users only, locked, dormant, suspended, banned ... users do not have any permissions
        return $this->is_super_admin() || (in_array($PermissionId, $this->all_permissions) && $this->status == 'active');
    }
    #endregion

    #region public function update_permission(...): bool
    /**
     * update user permissions after setting them manually
     *
     * @return void
     */
    public function update_permission(): void {
        //Bob: check permission for active users only, locked, dormant, suspended, banned ... users do not have any permissions
        try {
            $roles = Roles::get_instance();
            $this->all_permissions = $this->permissions;
            foreach ($this->roles as $role_name) {
                $role = $roles->get($role_name);
                if ($role !== null)
                    $this->all_permissions = array_merge($this->all_permissions, $roles->get($role_name)->permissions);
            }
        } catch (db_exception $ex) {
            logger::get_instance()->fatal('Cannot read roles from database', $ex);
        }
    }
    #endregion

}

//$user = new user();
//$user->photo = '64x64';
//$user->set_photo($user->get_photo());
//echo '<img src="' . $user->get_photo() . '">';
//exit();