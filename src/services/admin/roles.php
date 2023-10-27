<?php

namespace services\admin;

use attributes\authenticate;
use exceptions\command_exception;
use exceptions\db_exception;
use IService;
use logger\logger;
use security\user;

class roles implements IService {

    #region public function list(...)
    /**
     * @param array $json_req
     * @param user $session_user
     * @return string
     * @throws db_exception
     */
    #[authenticate("##LIST_ROLES##")]
    public function list(array $json_req, user $session_user): string {
        $logger = logger::get_instance();
        $db_roles = \security\roles::get_instance();
        $all_orgs = \security\organizations::get_instance()->tree_by_parent_id(0);

        $out = [];
        $roles = $db_roles->list_for_current_node($session_user->organization_id);
        foreach ($roles as $role) {
            $out[] = [
                'name'            => $role->name,
                'organization_id' => $role->organization_id,
                'description'     => $role->description,
                'date_created'    => $role->date_created,
                'created_by'      => $role->created_by,
                'date_modified'   => $role->date_modified,
                'modified_by'     => $role->modified_by,
                'permissions'     => $role->permissions,
                'visible_below'   => $role->visible_below ? 1 : 0
            ];
        }
        $logger->info('Listing roles for \"' . $session_user->organization_id . '"');
        return json_encode($out, JSON_ENCODE_OPTIONS);
    }
    #endregion

    #region public function add(...)
    /**
     * @param array $json_req
     * @param user $session_user
     * @return string
     * @throws command_exception
     * @throws db_exception
     */
    #[authenticate("##ADD_ROLES##")]
    public function add(array $json_req, user $session_user): string {
        $logger = logger::get_instance();

        $name = trim($json_req['name']);
        $description = trim($json_req['description']);
        $organization = intval($json_req['organization']);
        $permissions = $json_req['permissions'];
        $visible_below = boolval($json_req['visible_below']);


        $db_roles = \security\roles::get_instance();
        $all_orgs = \security\organizations::get_instance()->tree_by_parent_id($session_user->organization_id);

        if (!key_exists($organization, $all_orgs)) {
            throw new command_exception('##ERROR_ORGANIZATION_NOT_FOUND##');
        }
        if ($name === '' || $description === '') {
            throw new command_exception('##ERROR_BOTH_NAME_AND_DESCRIPTION_ARE_REQUIRED##');
        }
        $allowed_permissions = [];
        foreach ($permissions as $permission)
            if ($session_user->has_permission($permission))
                $allowed_permissions[] = $permission;
        $result = $db_roles->add($name, $organization, $description, $visible_below, implode(';', $allowed_permissions), $session_user->username);
        if ($result < 1) {
            throw new command_exception('##ERROR_ADDING_RECORD_FAILED##');
        }
        $logger->info('User ' . $session_user->username . ' added ' . $name . ' role');

        return $this->list([], $session_user);
    }
    #endregion

    #region public function edit(...)
    /**
     * @param array $json_req
     * @param user $session_user
     * @return string
     * @throws command_exception
     * @throws db_exception
     */
    #[authenticate("##EDIT_ROLES##")]
    public function edit(array $json_req, user $session_user): string {
        $logger = logger::get_instance();

        $name = trim($json_req['name']);
        $description = trim($json_req['description']);
        $permissions = $json_req['permissions'];
        $visible_below = boolval($json_req['visible_below']);

        $db_roles = \security\roles::get_instance();
        $all_orgs = \security\organizations::get_instance()->tree_by_parent_id($session_user->organization_id);
        $prev_record = $db_roles->get($name);
        if (is_null($prev_record)) {
            throw new command_exception('##ERROR_RECORD_NOT_FOUND##');
        }
        if (!key_exists($prev_record->organization_id, $all_orgs)) {
            throw new command_exception('##ERROR_EDIT_NOT_ALLOWED##');
        }
        if ($name === '' || $description === '') {
            throw new command_exception('##ERROR_BOTH_NAME_AND_DESCRIPTION_ARE_REQUIRED##');
        }
        $allowed_permissions = [];
        foreach ($permissions as $permission)
            if ($session_user->has_permission($permission))
                $allowed_permissions[] = $permission;
        $result = $db_roles->edit($name, $prev_record->organization_id, $description, $visible_below, implode(';', $allowed_permissions), $session_user->username);
        if ($result !== 1) {
            throw new command_exception('##ERROR_UPDATING_RECORD_FAILED##');
        }

        $logger->info('User ' . $session_user->username . ' added ' . $name . ' role');

        return $this->list([], $session_user);
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
    #[authenticate("##DELETE_ROLES##")]
    public function delete(array $json_req, user $session_user): string {
        $logger = logger::get_instance();

        $name = trim($json_req['name']);

        $db_roles = \security\roles::get_instance();
        $prev_record = $db_roles->get($name);
        // make sure role record exits
        if (is_null($prev_record)) {
            throw new command_exception('##ERROR_RECORD_NOT_FOUND##');
        }
        // make sure role is under an organization under user's control
        $all_orgs = \security\organizations::get_instance()->tree_by_parent_id($session_user->organization_id);
        if (!key_exists($prev_record->organization_id, $all_orgs)) {
            throw new command_exception('##ERROR_EDIT_NOT_ALLOWED##');
        }
        $result = $db_roles->delete($name);
        if ($result !== 1) {
            throw new command_exception('##ERROR_UPDATING_RECORD_FAILED##');
        }
        $logger->info('User ' . $session_user->username . ' deleted ' . $name . ' role');

        return $this->list([], $session_user);
    }
    #endregion

}
