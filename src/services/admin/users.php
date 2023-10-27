<?php

namespace services\admin;

use attributes\authenticate;
use attributes\no_translate;
use crypto\Hash;
use exceptions\command_exception;
use exceptions\db_exception;
use IService;
use logger\logger;
use security\permissions;
use security\user;
use UUID;

class users implements IService {

    #region private function get_valid_organization(...)
    private function get_valid_organization(string|null $organization_id): int {
        $org_id = intval($organization_id ?? -1);
        if ($organization_id != $org_id) {
            // is user being added in controlled organization of current user?
            throw new command_exception('##ERROR_ORGANIZATION_NOT_FOUND##');
        }
        return $org_id;
    }
    #endregion

    #region private function get_valid_username(...)
    private function get_valid_username(string|null $username): string {
        $username = trim($username ?? '');
        if ($username == '') {
            throw new command_exception('##ERROR_USERNAME_IS_REQUIRED##');
        }
        return $username;
    }
    #endregion

    #region private function get_valid_password(...)
    private function get_valid_password(string|null $password): string {
        return $password ?? '';
    }
    #endregion

    #region public function list(...)
    /**
     * @param array $json_req
     * @param user $session_user
     * @return string
     * @throws command_exception
     * @throws db_exception
     */
    #[authenticate("##LIST_USERS##")]
    public function list(array $json_req, user $session_user): string {
        $logger = logger::get_instance();
        $db_users = \security\users::get_instance();

        $organization_id = $this->get_valid_organization($json_req['organization_id']);
        $search = $json_req['search'];
        $node_only = $json_req['node_only'];

        // is selected organization controlled by current user?
        $user_orgs = \security\organizations::get_instance()->tree_by_parent_id($session_user->organization_id);
        if (!key_exists($organization_id, $user_orgs)) {
            throw new command_exception('##ERROR_ORGANIZATION_NOT_FOUND##');
        }

        $data = $db_users->search($organization_id, $search, $node_only);

        $logger->info('Listing users for organization "' . $session_user->organization_id . '"');
        $out = [];
        foreach ($data as $user) {
            $out[] = [
                "organization_id"  => $user->organization_id,
                "account_type"     => $user->account_type,
                "username"         => $user->username,
                "real_name"        => $user->real_name,
                "mobile_no"        => $user->mobile_no,
                "email"            => $user->email,
                "date_pwd_changed" => $user->date_pwd_changed ?? '',
                "roles"            => $user->roles,
                "permissions"      => $user->permissions,
                "date_signed_in"   => $user->date_signed_in ?? '',
                "date_locked_out"  => $user->date_locked_out ?? '',
                "status"           => $user->status,
                "created_by"       => $user->created_by ?? '',
                "date_created"     => $user->date_created ?? '',
                "modified_by"      => $user->modified_by ?? '',
                "date_modified"    => $user->date_modified ?? '',
                "comment"          => $user->comment ?? ''
            ];
        }
        return json_encode($out, JSON_ENCODE_OPTIONS);
    }
    #endregion

    #region public function add(...)
    /**
     * Public service for adding users
     *
     * @param array $json_req
     * @param user $session_user
     * @return string
     *
     * @throws command_exception
     * @throws db_exception
     */
    #[authenticate("##ADD_USER##")]
    public function add(array $json_req, user $session_user): string {
        $db_users = \security\users::get_instance();
        $db_roles = \security\roles::get_instance();
        $db_orgs = \security\organizations::get_instance();

        $organization_id = $this->get_valid_organization($json_req['organization_id']);
        $username = $this->get_valid_username($json_req['username']);
        $password = $this->get_valid_password($json_req['password']);
        $account_type = $json_req['account_type'] ?? '';
        $real_name = trim($json_req['real_name'] ?? '');
        $mobile_no = trim($json_req['mobile_no'] ?? '');
        $email = trim($json_req['email'] ?? '');
        $comment = trim($json_req['comment'] ?? '');
        $roles = $json_req['roles'] ?? [];

        // Validate inputs

        // is session user controlling the selected organization?
        $user_orgs = $db_orgs->tree_by_parent_id($session_user->organization_id);
        if (!key_exists($organization_id, $user_orgs)) {
            throw new command_exception('##ERROR_ORGANIZATION_NOT_FOUND##');
        }
        // Is user with same username exist?
        $existing_user = $db_users->get($username);
        if ($existing_user !== null) {
            throw new command_exception('##ERROR_USERNAME_IS_ALREADY_TAKEN##');
        }
        //only valid types are a:application and u:user
        if (!in_array($account_type, ['U', 'A'])) {
            throw new command_exception('##ERROR_INVALID_ACCOUNT_TYPE##');
        }
        if ($password == '') {
            throw new command_exception('##ERROR_PASSWORD_IS_REQUIRED##');
        }
        if (count($roles) < 1) {
            throw new command_exception('##ERROR_NO_ROLES_WERE_SELECTED##');
        }

        //Make sure the user being created can have selected roles
        $org_roles = $db_roles->list_for_current_node($organization_id);
        $user_roles = [];
        foreach ($roles as $role) {
            if (key_exists($role, $org_roles)) {
                $user_roles[] = $role;
            }
        }
        if (count($user_roles) > 0)
            $roles = ';' . implode(';', $user_roles) . ';';
        else
            $roles = '';

        // hash and salt the password
        $password_salt = Hash::sha256(UUID::v4());
        $password = Hash::sha256($password . $password_salt);

        $result = $db_users->add($organization_id, $account_type, $username, $real_name, $mobile_no, $email, $password, $password_salt, $roles, $session_user->username, $comment);
        // both email and phone number are unique for password recovery purposes
        if ($result === -1) {
            throw new command_exception('##ERROR_A_USER_WITH_SAME_EMAIL_OR_PHONE_NUMBER_EXIST##');
        }
        if ($result < 1) {
            throw new command_exception('##ERROR_CREATING_USER_FAILED##');
        }

        logger::get_instance()->info('user ' . $session_user->username . ' added user ' . $username . '.');
        $out = ['result' => 'ok'];
        return json_encode($out, JSON_ENCODE_OPTIONS);
    }
    #endregion

    #region public function edit(...)
    /**
     * @throws db_exception
     * @throws command_exception
     */
    #[authenticate("##EDIT_USER##")]
    public function edit(array $json_req, user $session_user): string {
        $organization_id = $this->get_valid_organization($json_req['organization_id']);
        $username = $this->get_valid_username($json_req['username']);
        $password = $this->get_valid_password($json_req['password']);
        $account_type = trim($json_req['account_type'] ?? '');
        $real_name = trim($json_req['real_name'] ?? '');
        $mobile_no = trim($json_req['mobile_no'] ?? '');
        $email = trim($json_req['email'] ?? '');
        $comment = trim($json_req['comment'] ?? '');
        $roles = $json_req['roles'] ?? [];

        $db_users = \security\users::get_instance();
        $db_orgs = \security\organizations::get_instance();
        $db_roles = \security\roles::get_instance();

        // Validate inputs
        $existing_user = $db_users->get($username);
        if ($existing_user === null) {
            throw new command_exception('##ERROR_USER_NOT_FOUND##');
        }
        $user_orgs = $db_orgs->tree_by_parent_id($session_user->organization_id);
        // is session user controlling the selected user's organization?
        if (!key_exists($existing_user->organization_id, $user_orgs)) {
            throw new command_exception('##ERROR_ORGANIZATION_NOT_FOUND##');
        }
        // in case organization is being changed, is new organization under control of current user?
        if ($organization_id !== $existing_user->organization_id && !key_exists($organization_id, $user_orgs)) {
            throw new command_exception('##ERROR_ORGANIZATION_NOT_FOUND##');
        }

        //only valid types are a:application and u:user
        if (!in_array($account_type, ['U', 'A'])) {
            throw new command_exception('##ERROR_INVALID_ACCOUNT_TYPE##');
        }

        if (count($roles) < 1) {
            throw new command_exception('##ERROR_NO_ROLES_WERE_SELECTED##');
        }
        //Make sure the user being created can have selected roles
        $org_roles = $db_roles->list_for_current_node($organization_id);
        $user_roles = [];
        foreach ($roles as $role) {
            if (key_exists($role, $org_roles)) {
                $user_roles[] = $role;
            }
        }
        if (count($user_roles) > 0)
            $roles = ';' . implode(';', $user_roles) . ';';
        else
            $roles = '';

        // set password to null if it is omitted, DAL will not change previous password if null is passed to it
        $password_salt = null;
        if ($password !== '') {
            // hash and salt the password
            $password_salt = Hash::sha256(UUID::v4());
            $password = Hash::sha256($password . $password_salt);
        } else {
            $password = null;
        }

        $result = $db_users->admin_edit($username, $organization_id, $account_type, $real_name, $mobile_no, $email, $password, $password_salt, $roles, $session_user->username, $comment);
        // both email and phone number are unique for password recovery purposes
        if ($result === -1) {
            throw new command_exception('##ERROR_A_USER_WITH_SAME_EMAIL_OR_PHONE_NUMBER_EXIST##');
        }
        if ($result < 1) {
            throw new command_exception('##ERROR_UPDATING_USER_FAILED##');
        }

        logger::get_instance()->info('user ' . $session_user->username . ' edited user ' . $username . '.');
        $out = ['result' => 'ok'];
        return json_encode($out, JSON_ENCODE_OPTIONS);
    }
    #endregion

    #region public function delete(...)
    /**
     * @param array $json_req
     * @param user $session_user
     * @return string
     * @throws command_exception
     * @throws db_exception
     */
    #[authenticate("##DELETE_USER##")]
    public function delete(array $json_req, user $session_user): string {
        $username = $json_req['username'] ?? '';
        $db_users = \security\users::get_instance();
        $db_orgs = \security\organizations::get_instance();

        // validate input
        if ($username == '') {
            throw new command_exception('##ERROR_USERNAME_IS_REQUIRED##');
        }
        // is user exist
        $user = $db_users->get($username);
        if ($user === null) {
            throw new command_exception('##ERROR_USER_NOT_FOUND##');
        }
        // is user being edited in controlled organization of current user?
        $session_user_orgs = $db_orgs->tree_by_parent_id($user->organization_id);
        if (!key_exists($user->organization_id, $session_user_orgs)) {
            throw new command_exception('##ERROR_ORGANIZATION_NOT_FOUND##');
        }
        $result = $db_users->delete($username);
        if ($result !== 1) {
            throw new command_exception('##ERROR_DELETING_RECORD_FAILED##');
        }
        logger::get_instance()->info('user ' . $session_user->username . ' deleted user ' . $username . '.');
        $out = ['result' => 'ok'];
        return json_encode($out, JSON_ENCODE_OPTIONS);
    }
    #endregion

    #region public function explicit_permissions(...)
    /**
     * @param array $json_req
     * @param user $session_user
     * @return string
     * @throws command_exception
     * @throws db_exception
     */
    #[authenticate("##CHANGE_USER_EXPLICIT_PERMISSIONS##")]
    public function explicit_permissions(array $json_req, user $session_user): string {
        $username = $json_req['username'] ?? '';
        $permissions = $json_req['permissions'] ?? [];

        $db_users = \security\users::get_instance();
        $db_orgs = \security\organizations::get_instance();

        // validate input
        if ($username == '') {
            throw new command_exception('##ERROR_USERNAME_IS_REQUIRED##');
        }
        // is user exist
        $user = $db_users->get($username);
        if ($user === null) {
            throw new command_exception('##ERROR_USER_NOT_FOUND##');
        }
        // is user being edited in controlled organization of current user?
        $session_user_orgs = $db_orgs->tree_by_parent_id($user->organization_id);
        if (!key_exists($user->organization_id, $session_user_orgs)) {
            throw new command_exception('##ERROR_ORGANIZATION_NOT_FOUND##');
        }

        // does sesison_user have requested permissions themselves
        $user_permissions = [];
        foreach ($permissions as $permission) {
            if ($session_user->has_permission($permission)) {
                $user_permissions[] = $permission;
            }
        }
        if (count($user_permissions) > 0)
            $permissions = ';' . implode(';', $user_permissions) . ';';
        else
            $permissions = '';

        $result = $db_users->set_explicit_permissions($username, $permissions, $session_user->username);
        if ($result !== 1) {
            throw new command_exception('##ERROR_DELETING_RECORD_FAILED##');
        }
        logger::get_instance()->info('user ' . $session_user->username . ' changed explicit permissions for user ' . $username . '.');
        $out = ['result' => 'ok'];
        return json_encode($out, JSON_ENCODE_OPTIONS);
    }
    #endregion

    #region public function get_permissions(...): string
    #[no_translate]
    public function permissions(array $json_req, user $user): string {
        return json_encode(permissions::get_instance()->get_user_permissions($user), JSON_ENCODE_OPTIONS);
    }
    #endregion

    #region public function set_status(...)
    /**
     * @param array $json_req
     * @param user $session_user
     * @return string
     * @throws command_exception
     * @throws db_exception
     */
    #[authenticate("##SET_USER_STATUS##")]
    public function set_status(array $json_req, user $session_user): string {
        $username = $json_req['username'] ?? '';
        $status = $json_req['status'] ?? '';

        $db_users = \security\users::get_instance();
        $db_orgs = \security\organizations::get_instance();

        // validate input
        if ($username == '') {
            throw new command_exception('##ERROR_USERNAME_IS_REQUIRED##');
        }
        if (!in_array($status, ['active', 'banned', 'unlock'])) {
            // Admin page only can set to active, ban and unlock, other statuses are conditional shouldn't be set directly
            throw new command_exception('##ERROR_INVALID_STATUS_IS_SELECTED##');
        }
        // is user exist
        $user = $db_users->get($username);
        if ($user === null) {
            throw new command_exception('##ERROR_USER_NOT_FOUND##');
        }
        // is user being edited in controlled organization of current user?
        $session_user_orgs = $db_orgs->tree_by_parent_id($user->organization_id);
        if (!key_exists($user->organization_id, $session_user_orgs)) {
            throw new command_exception('##ERROR_ORGANIZATION_NOT_FOUND##');
        }
        $result = $db_users->set_status($username, $status, $session_user->username);
        if ($result !== 1) {
            throw new command_exception('##ERROR_SETTING_STATUS_FAILED##');
        }
        logger::get_instance()->info('user ' . $user->username . ' changed status of ' . $username . ' to ' . $status . '.');
        $out = ['result' => 'ok'];
        return json_encode($out, JSON_ENCODE_OPTIONS);
    }
    #endregion
}
