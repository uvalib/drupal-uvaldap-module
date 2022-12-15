<?php

namespace Drupal\uvaldap;

class UserInformationService {

  protected static $instance = NULL;
  protected $serviceURL = "";
  private $tokenService = NULL;

  protected function __construct() {
    $this->serviceURL = getenv('USER_WS_URL') ?: "http://user-ws-production.private.production:8080";

    $this->tokenService = \Drupal\uvaldap\MintTokenService::getInstance();
  }

  public static function getInstance() {
    if (!isset(static::$instance)) {
      static::$instance = new static();
    }
    return static::$instance;
  }

  private function getUserInfo($computingID) {
    $ch = curl_init();

    $endpoint = $this->serviceURL . "/user/" . $computingID;
    $params = array('auth' => $this->tokenService->getToken());
    $url = $endpoint . '?' . http_build_query($params);

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $res = curl_exec($ch);

    if ($res === false) {
      throw new \Exception("getUserInfo(): curl_exec() failed: " . curl_error($ch));
    }

    if (($rc = curl_getinfo($ch, CURLINFO_RESPONSE_CODE)) != 200) {
      throw new \Exception("getUserInfo(): received response code: " . $rc);
    }

    curl_close($ch);

    return $res;
  }

  public function updateEntity($entity, $computingID) {
    try {
      $json = $this->getUserInfo($computingID);

      $obj = json_decode($json);
      $user = $obj->user;

      $entity->field_uva_ldap_response = $json;

      //$entity->field_uva_ldap_computing_id = isset($user->cid) ? $user->cid : "";
      $entity->field_uva_ldap_id = isset($user->uva_id) ? $user->uva_id : "";
      $entity->field_uva_ldap_display_name = isset($user->display_name) ? $user->display_name : "";
      $entity->field_uva_ldap_first_name = isset($user->first_name) ? $user->first_name : "";
      $entity->field_uva_ldap_initials = isset($user->initials) ? $user->initials : "";
      $entity->field_uva_ldap_last_name = isset($user->last_name) ? $user->last_name : "";
      $entity->field_uva_ldap_email = isset($user->email) ? $user->email : "";
      $entity->field_uva_ldap_private = filter_var((isset($user->private) ? $user->private : "false"), FILTER_VALIDATE_BOOLEAN);

      $entity->field_uva_ldap_description = isset($user->description) ? $user->description : array();
      $entity->field_uva_ldap_department = isset($user->department) ? $user->department : array();
      $entity->field_uva_ldap_title = isset($user->title) ? $user->title : array();
      $entity->field_uva_ldap_office = isset($user->office) ? $user->office : array();
      $entity->field_uva_ldap_phone = isset($user->phone) ? $user->phone : array();
      $entity->field_uva_ldap_affiliation = isset($user->affiliation) ? $user->affiliation : array();

      if ($entity->field_uva_ldap_display_name != "") {
        $entity->setTitle($entity->get('field_uva_ldap_display_name')->value);
      }
    }
    catch (\Exception $e) {
      throw $e;
    }
  }

}
