<?php

namespace services\admin;

use allowed_chars;
use attributes\authenticate;
use exceptions\command_exception;
use IService;
use logger\logger;
use security\organization;
use security\user;
use function sanitize;

class organizations implements IService {

    #region private function is_a_parent(...): bool
    private function is_user_controlled_organization(int $user_org, int $organization_id): organization|null {
        $db_org = \security\organizations::get_instance();
        $user_organizations = $db_org->tree_by_parent_id($user_org);
        foreach ($user_organizations as $organization) {
            if ($organization->id == $organization_id) {
                return $organization;
            }
        }
        return null;
    }
    #endregion

    #region public function list(...)
    #[authenticate("List Organizations")]
    public function list(array $json_req, user $session_user): string {
        $db_org = \security\organizations::get_instance();
        $logger = logger::get_instance();

        $user_org = $session_user->organization_id;
        $orgs = $db_org->tree_by_parent_id($user_org);
        $out = [];
        if (count($orgs) < 1) {
            $out[] = [
                'k' => -1,
                'p' => 0,
                'n' => '##NOT_FOUND##',
                'd' => '##NOT_FOUND##'
            ];

        } else {
            $root_name = $db_org->full_name($user_org);
            foreach ($orgs as $org) {
                if ($org->id == $user_org) {
                    $out[] = [
                        'k' => $org->id,
                        'p' => null,
                        'n' => $root_name,
                        'd' => $root_name
                    ];
                } else {
                    $out[] = [
                        'k' => $org->id,
                        'p' => $org->parent_id,
                        'n' => $org->name,
                        'd' => $org->description
                    ];
                }
            }
        }
        $logger->info("Listing organizations for '" . $session_user->username);
        return json_encode($out, JSON_ENCODE_OPTIONS);
    }
    #endregion

    #region public string update(...): string
    #[authenticate("Add/Edit Organization")]
    public function update(array $json_req, user $session_user): string {
        $db_org = \security\organizations::get_instance();
        $logger = logger::get_instance();

        $id = intval($json_req["id"]);
        $parent_id = intval($json_req["parent_id"]);
        $name = trim($json_req["name"]);
        $description = trim($json_req["description"]);

        // check if node name and description are valid (not empty)
        if ($name == "" || $description == "") {
            throw new command_exception("##ERROR_NAME_AND_DESCRIPTION_ARE_REQUIRED##");
        }
        if ($name != sanitize($name, [allowed_chars::uppercase, allowed_chars::lowercase, allowed_chars::digits, allowed_chars::dash, allowed_chars::underline])) {
            throw new command_exception("##ERROR_NAME_CONTAINS_INVALID_CHARACTERS##");
        }
        $result = -1;
        if ($id > 0) {
            // this is an edit request

            // make sure user has control over this organization
            $org = $this->is_user_controlled_organization($session_user->organization_id, $id);
            if ($org == null) {
                throw new command_exception("##ERROR_RECORD_WAS_NOT_FOUND##");
            }
            // check if node data will be changed (if not, return)
            if ($description == $org->description && $name == $org->name) {
                throw new command_exception("##ERROR_RECORD_IS_NOT_CHANGED##");
            }

            $result = $db_org->edit($id, $org->parent_id, $name, $description, $session_user->username);
            $logger->info("Editing organization node '" . $org->name . "'(" . $org->description . ") to '" . $name . "'(" . $description . ") " . ($result == 1 ? "completed" : "failed") . " (row_count:" . $result . ")");


        } else if ($parent_id > 0) {
            // this is an add request
            // make sure user has control over parent organization
            $parent_org = $this->is_user_controlled_organization($session_user->organization_id, $parent_id);
            if ($parent_org == null) {
                throw new command_exception("##ERROR_PARENT_ORGANIZATION_WAS_NOT_FOUND##");
            }
            $result = $db_org->add($parent_id, $name, $description, $session_user->username);
            $logger->info("Adding organization node '" . $name . "' to organization '" . $parent_org->name . "'" . ($result > 0 ? "completed" : "failed") . " (record_id:" . $result . ")");
        }

        if ($result < 1) {
            throw new command_exception("##ERROR_UPDATING_RECORD_FAILED##");
        }
        return $this->list($json_req, $session_user);
    }
    #endregion

    #region public string delete(...)
    #[authenticate("Delete Organizations")]
    public function delete(array $json_req, user $session_user): string {
        $db_org = \security\organizations::get_instance();
        $logger = logger::get_instance();

        $id = intval($json_req["id"]);

        // make sure user has control over this organization
        $org = $this->is_user_controlled_organization($session_user->organization_id, $id);
        if ($org == null) {
            throw new command_exception("##ERROR_RECORD_WAS_NOT_FOUND##");
        }

        $result = $db_org->delete($id);
        $logger->info("Deleting organization node '" . $org->name . "'(" . $org->description . ") " . ($result == 1 ? "completed" : "failed") . " (" . $result . ")");
        if ($result != 1) {
            throw new command_exception("##ERROR_DELETING_RECORD_FAILED##");
        }
        return $this->list($json_req, $session_user);
    }
    #endregion

    #region public string move(...)
    #[authenticate("Move Organizations")]
    public function move(array $json_req, user $session_user): string {
        $db_org = \security\organizations::get_instance();
        $logger = logger::get_instance();

        $target_org_id = intval($json_req["target"]);
        $dest_org_id = intval($json_req["dest"]);

        $target_org = $this->is_user_controlled_organization($session_user->organization_id, $target_org_id);
        $dest_org = $this->is_user_controlled_organization($session_user->organization_id, $dest_org_id);

        // make sure user has control over both organization
        if ($target_org == null) {
            throw new command_exception("##ERROR_RECORD_WAS_NOT_FOUND##");
        }
        if ($dest_org == null) {
            throw new command_exception("##ERROR_PARENT_RECORD_WAS_NOT_FOUND##");
        }

        // check if move will cause cyclic reference (parent moved to child)(do not allow move)
        if ($this->is_user_controlled_organization($target_org_id, $dest_org_id) !== null) {
            throw new command_exception("##ERROR_MOVING_PARENT_UNDER_ITS_CHILD_IS_NOT_POSSIBLE##");
        }

        $result = $db_org->move($target_org_id, $dest_org_id, $session_user->username);
        $logger->info("Moving organization '" . $target_org->name . "' under '" . $dest_org->name . "' " . ($result == 1 ? "completed" : "failed") . " (" . $result . ")");
        if ($result < 1) {
            throw new command_exception("##ERROR_UPDATING_RECORD_FAILED##");
        }
        return $this->list($json_req, $session_user);
    }
    #endregion

}