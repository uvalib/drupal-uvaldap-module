<?php

namespace Drupal\uvaldap;

class MintTokenService {

  protected static $instance = NULL;
  protected $serviceURL = "";
  private $authToken = NULL;

  protected function __construct() {}

  public static function getInstance() {
    if (!isset(static::$instance)) {
      static::$instance = new static();
    }
    return static::$instance;
  }

  private function getServiceURL() {
    if (!empty($this->serviceURL)) {
      return $this->serviceURL;
    }

    // check environment for URL
    $url = getenv('MINT_TOKEN_WS_URL');

    if (empty($url)) {
      // URL not found in environment; attempt to divine it
      // based on URL structure in mysql host variable
      $host = getenv('MYSQL_HOST');

      if (!empty($host) && str_contains($host, "-staging")) {
        // looks like we're in staging
        $url = "http://mint-token-ws-staging.private.staging:8080";
      } else {
        // default to production to be safe
        $url = "http://mint-token-ws-production.private.production:8080";
      }
    }

    $this->serviceURL = $url;

    return $this->serviceURL;
  }

  private function mintToken() {
    $ch = curl_init();

    $endpoint = $this->getServiceURL() . "/mint";
    $url = $endpoint;

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $res = curl_exec($ch);

    if ($res === false) {
      throw new \Exception("mintToken(): curl_exec() failed: " . curl_error($ch));
    }

    if (($rc = curl_getinfo($ch, CURLINFO_RESPONSE_CODE)) != 200) {
      throw new \Exception("mintToken(): received response code: " . $rc);
    }

    curl_close($ch);

    return $res;
  }

  private function refreshToken() {
    try {
      $json = $this->mintToken();
      $this->authToken = json_decode($json);
    }
    catch (\Exception $e) {
      throw $e;
    }
  }

  private function validateToken() {
    // refresh if we have no token yet
    if (!isset($this->authToken)) {
      $this->refreshToken();
      return;
    }

    // refresh if token is expiring soon
    $exp = strtotime($this->authToken->expires);
    $now = time();
    if ($now > $exp - (60 * 60 * 6)) {
      $this->refreshToken();
      return;
    }
  }

  public function getToken() {
    try {
      $this->validateToken();
      return $this->authToken->token;
    }
    catch (\Exception $e) {
      throw $e;
    }
  }
}
