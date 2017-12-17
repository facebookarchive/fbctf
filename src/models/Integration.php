<?hh // strict

use Facebook\GraphNodes\GraphNode\Collection as GraphCollection;

class Integration extends Model {

  private function __construct(private string $type) {}

  protected static Cache $INTEGRATION_CACHE = MUST_MODIFY;

  public function getType(): string {
    return $this->type;
  }

  public static function getIntegrationCacheObject(): Cache {
    if (self::$INTEGRATION_CACHE === MUST_MODIFY) {
      self::$INTEGRATION_CACHE = new Cache();
    }
    invariant(
      self::$INTEGRATION_CACHE instanceof Cache,
      'Integration::$INTEGRATION_CACHE should of type Map and not null',
    );
    return self::$INTEGRATION_CACHE;
  }

  public static async function facebookOAuthEnabled(): Awaitable<bool> {
    return Configuration::getFacebookOAuthSettingsExists();
  }

  public static async function googleOAuthEnabled(): Awaitable<bool> {
    return Configuration::getGoogleOAuthFileExists();
  }

  public static async function facebookLoginEnabled(): Awaitable<bool> {
    $login_facebook = await Configuration::gen('login_facebook');
    $oauth = Configuration::getFacebookOAuthSettingsExists();

    $login_facebook_enabled =
      $login_facebook->getValue() === '1' ? true : false;

    if ($oauth && $login_facebook_enabled) {
      return true;
    } else {
      return false;
    }
  }

  public static async function googleLoginEnabled(): Awaitable<bool> {
    $login_google = await Configuration::gen('login_google');
    $oauth = Configuration::getGoogleOAuthFileExists();

    $login_google_enabled = $login_google->getValue() === '1' ? true : false;

    if (($oauth) && ($login_google_enabled)) {
      return true;
    } else {
      return false;
    }
  }

  public static async function genFacebookAuthURL(
    string $redirect,
    bool $rerequest = false,
  ): Awaitable<(Facebook, string)> {
    $host = strval(idx(Utils::getSERVER(), 'HTTP_HOST'));
    $app_id = Configuration::getFacebookOAuthSettingsAppId();
    $app_secret = Configuration::getFacebookOAuthSettingsAppSecret();
    $client = new Facebook\Facebook(
      [
        'app_id' => $app_id,
        'app_secret' => $app_secret,
        'default_graph_version' => 'v2.2',
      ],
    );

    $helper = $client->getRedirectLoginHelper();

    $permissions = ['email'];
    $auth_url = $helper->getLoginUrl(
      'https://'.$host.'/data/integration_'.$redirect.'.php?type=facebook',
      $permissions,
    );

    if ($rerequest === true) {
      $auth_url .= '&auth_type=rerequest';
    }
    return tuple($client, $auth_url);
  }

  public static async function genFacebookAPIClient(
  ): Awaitable<(Facebook, string)> {
    $host = strval(idx(Utils::getSERVER(), 'HTTP_HOST'));
    $app_id = Configuration::getFacebookOAuthSettingsAppId();
    $app_secret = Configuration::getFacebookOAuthSettingsAppSecret();
    $client = new Facebook\Facebook(
      [
        'app_id' => $app_id,
        'app_secret' => $app_secret,
        'default_graph_version' => 'v2.2',
      ],
    );

    $access_token = $app_id.'|'.$app_secret;

    return tuple($client, $access_token);
  }

  public static async function genGoogleAuthURL(
    string $redirect,
  ): Awaitable<(Google_Client, string)> {
    $host = strval(idx(Utils::getSERVER(), 'HTTP_HOST'));
    $google_oauth_file = Configuration::getGoogleOAuthFile();
    $client = new Google_Client();
    $client->setAuthConfig($google_oauth_file);
    $client->setAccessType('offline');
    $client->setScopes(['profile email']);
    $client->setRedirectUri(
      'https://'.$host.'/data/integration_'.$redirect.'.php?type=google',
    );

    $integration_csrf_token = bin2hex(random_bytes(100));
    setcookie(
      'integration_csrf_token',
      strval($integration_csrf_token),
      0,
      '/data/',
      must_have_string(Utils::getSERVER(), 'SERVER_NAME'),
      true,
      true,
    );
    $client->setState(strval($integration_csrf_token));

    $auth_url = $client->createAuthUrl();

    return tuple($client, $auth_url);
  }

  public static async function genFacebookLogin(): Awaitable<string> {
    list($client, $url) = await self::genFacebookAuthURL("login");
    $helper = $client->getRedirectLoginHelper();

    $code = idx(Utils::getGET(), 'code', false);
    $error = idx(Utils::getGET(), 'error', false);

    $accessToken = '';

    if ($code !== false) {
      $graph_error = false;
      try {
        $accessToken = $helper->getAccessToken();
      } catch (Facebook\Exceptions\FacebookResponseException $e) {
        $graph_error = true;
      } catch (Facebook\Exceptions\FacebookSDKException $e) {
        $graph_error = true;
      }

      $url = '/index.php?page=login';
      if ($graph_error !== true) {

        $response = false;
        try {
          $response =
            $client->get('/me?fields=id,third_party_id,email', $accessToken);
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
          error_log("Facebook OAuth Failed - Missing fields");
          $url = '/index.php?page=error';
          return $url;
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
          error_log("Facebook OAuth Failed - Missing fields");
          $url = '/index.php?page=error';
          return $url;
        }
        $profile = $response->getGraphUser();
        $email = $profile['email'];
        $id = $profile['third_party_id'];

        if ($id === null) {
          error_log("Facebook OAuth Failed - Missing id Field");
          list($client, $url) = await self::genFacebookAuthURL("login", true);
          return $url;
        }

        if ($email === null) {
          error_log("Facebook OAuth Failed - Missing email Field - $id");
          list($client, $url) = await self::genFacebookAuthURL("login", true);
          return $url;
        }

        list($oauth_token_exists, $registration_facebook) =
          await \HH\Asio\va(
            Team::genAuthTokenExists('facebook_oauth', strval($email)),
            Configuration::gen('registration_facebook'),
          );

        if ($oauth_token_exists === true) {
          $url = await self::genLoginURL("facebook_oauth", $email);
        } else if ($registration_facebook->getValue() === '1') {
          $team_id = await self::genRegisterTeam($email, $id);

          if (is_int($team_id) === true) {
            $set_integrations = await self::genSetTeamIntegrations(
              $team_id,
              'facebook_oauth',
              $email,
              $id,
            );
            if ($set_integrations === true) {
              $url = await self::genLoginURL('facebook_oauth', $email);
            }
          }
        }
      }
    } else if ($error !== false) {
      $url = '/index.php?page=login';
    }

    return $url;
  }

  public static async function genGoogleLogin(): Awaitable<string> {
    list($client, $url) = await self::genGoogleAuthURL("login");

    $code = idx(Utils::getGET(), 'code', false);
    $error = idx(Utils::getGET(), 'error', false);
    $state = idx(Utils::getGET(), 'state', false);

    if ($code !== false) {
      $integration_csrf_token = /* HH_IGNORE_ERROR[2050] */
        idx($_COOKIE, 'integration_csrf_token', false);
      if (strval($integration_csrf_token) === '' ||
          strval($state) === '' ||
          strval($integration_csrf_token) !== strval($state)) {
        $code = false;
        $error = true;
      }
    }

    if ($code !== false) {
      $url = '/index.php?page=login';
      $client->authenticate($code);
      $access_token = $client->getAccessToken();
      $oauth_client = new Google_Service_Oauth2($client);
      $profile = $oauth_client->userinfo->get();
      $email = $profile->email;
      $id = $profile->id;

      list($oauth_token_exists, $registration_google) = await \HH\Asio\va(
        Team::genAuthTokenExists('google_oauth', strval($email)),
        Configuration::gen('registration_google'),
      );

      if ($oauth_token_exists === true) {
        $url = await self::genLoginURL('google_oauth', $email);
      } else if ($registration_google->getValue() === '1') {
        $team_id = await self::genRegisterTeam($email, $id);
        if (is_int($team_id) === true) {
          $set_integrations = await self::genSetTeamIntegrations(
            $team_id,
            'google_oauth',
            $email,
            $id,
          );
          if ($set_integrations === true) {
            $url = await self::genLoginURL('google_oauth', $email);
          }
        }
      }
    } else if ($error !== false) {
      $url = '/index.php?page=login';
    }

    return $url;
  }

  public static async function genLoginURL(
    string $type,
    string $token,
  ): Awaitable<string> {
    $team = await Team::genTeamFromOAuthToken($type, $token);

    SessionUtils::sessionRefresh();
    if (!SessionUtils::sessionActive()) {
      SessionUtils::sessionSet('team_id', strval($team->getId()));
      SessionUtils::sessionSet('name', $team->getName());
      SessionUtils::sessionSet(
        'csrf_token',
        (string) strval(bin2hex(random_bytes(100))),
      );
      SessionUtils::sessionSet(
        'IP',
        must_have_string(Utils::getSERVER(), 'REMOTE_ADDR'),
      );
      if ($team->getAdmin()) {
        SessionUtils::sessionSet('admin', strval($team->getAdmin()));
      }
    }
    if ($team->getAdmin()) {
      $redirect = 'admin';
    } else {
      $redirect = 'game';
    }

    $login_url = '/index.php?p='.$redirect;

    return $login_url;
  }

  public static async function genFacebookOAuth(): Awaitable<bool> {
    list($client, $url) = await self::genFacebookAuthURL("oauth");
    $helper = $client->getRedirectLoginHelper();

    $code = idx(Utils::getGET(), 'code');
    $error = idx(Utils::getGET(), 'error');

    if (!is_string($code)) {
      $code = false;
    }

    if (!is_string($error)) {
      $error = false;
    }

    $accessToken = '';

    if ($code !== false) {
      $graph_error = false;
      try {
        $accessToken = $helper->getAccessToken();
      } catch (Facebook\Exceptions\FacebookResponseException $e) {
        $graph_error = true;
      } catch (Facebook\Exceptions\FacebookSDKException $e) {
        $graph_error = true;
      }

      if ($graph_error === true) {
        return false;
      } else {
        $response =
          $client->get('/me?fields=id,third_party_id,email', $accessToken);
        $profile = $response->getGraphUser();
        $email = $profile['email'];
        $id = $profile['third_party_id'];

        if ($email === null) {
          list($client, $url) = await self::genFacebookAuthURL("oauth", true);
          header('Location: '.filter_var($url, FILTER_SANITIZE_URL));
          exit;
        }

        $set_integrations = await self::genSetTeamIntegrations(
          SessionUtils::sessionTeam(),
          'facebook_oauth',
          $email,
          $id,
        );
        return $set_integrations;
      }
    } else if ($error !== false) {
      return false;
    }

    header('Location: '.filter_var($url, FILTER_SANITIZE_URL));
    exit;
    return false;
  }

  public static async function genGoogleOAuth(): Awaitable<bool> {
    list($client, $url) = await self::genGoogleAuthURL("oauth");

    $code = idx(Utils::getGET(), 'code', false);
    $error = idx(Utils::getGET(), 'error', false);
    $state = idx(Utils::getGET(), 'state', false);

    if ($code !== false) {
      $integration_csrf_token = /* HH_IGNORE_ERROR[2050] */
        idx($_COOKIE, 'integration_csrf_token', false);
      if (strval($integration_csrf_token) === '' ||
          strval($state) === '' ||
          strval($integration_csrf_token) !== strval($state)) {
        $code = false;
        $error = true;
      }
    }

    if ($code !== false) {
      $client->authenticate($code);
      $access_token = $client->getAccessToken();
      $oauth_client = new Google_Service_Oauth2($client);
      $profile = $oauth_client->userinfo->get();
      $email = $profile->email;
      $id = $profile->id;

      $set_integrations = await self::genSetTeamIntegrations(
        SessionUtils::sessionTeam(),
        'google_oauth',
        $email,
        $id,
      );
      return $set_integrations;
    } else if ($error !== false) {
      return false;
    }

    header('Location: '.filter_var($url, FILTER_SANITIZE_URL));
    exit;
    return false;
  }

  public static async function genSetTeamIntegrations(
    int $team_id,
    string $type,
    string $email,
    string $id,
  ): Awaitable<bool> {
    list($livesync_password_update, $oauth_token_update) = await \HH\Asio\va(
      Team::genSetLiveSyncPassword($team_id, $type, $email, $id),
      Team::genSetOAuthToken($team_id, $type, $email),
    );

    if (($livesync_password_update === true) &&
        ($oauth_token_update === true)) {
      return true;
    } else {
      return false;
    }
  }

  public static async function genRegisterTeam(
    string $email,
    string $id,
    string $name = '',
  ): Awaitable<int> {
    list($registration_prefix, $logo_name) = await \HH\Asio\va(
      Configuration::gen('registration_prefix'),
      Logo::genRandomLogo(),
    );

    $team_password = Team::generateHash(random_bytes(100));
    $team_name = substr(
      substr($registration_prefix->getValue(), 0, 14).
      "-".
      bin2hex(random_bytes(12)),
      0,
      20,
    );
    if ($name !== '') {
      $team_name = substr(strval($name), 0, 20);
    }
    $team_id = await Team::genCreate($team_name, $team_password, $logo_name);
    return $team_id;
  }

  public static async function genFacebookThirdPartyExists(
    string $third_party_id,
  ): Awaitable<bool> {
    self::getIntegrationCacheObject();
    if (self::$INTEGRATION_CACHE->getCache(
          'facebook_exists:'.$third_party_id,
        ) ===
        true) {
      return true;
    } else {
      list($client, $access_token) = await self::genFacebookAPIClient();

      try {
        $response =
          $client->get('/'.$third_party_id.'?fields=email', $access_token);
      } catch (FacebookExceptionsFacebookResponseException $e) {
        print "error 1\n\n\n";
        return false;
      } catch (FacebookExceptionsFacebookSDKException $e) {
        print "error 2\n\n\n";
        return false;
      }
      $graphNode = $response->getGraphNode();
      $graphNode->getField('email');
      $profile = $response->getGraphUser();
      $email = strval($profile->getEmail());
      if ($email !== null) {
        self::$INTEGRATION_CACHE->setCache(
          'facebook_exists:'.$third_party_id,
          true,
        );
        self::$INTEGRATION_CACHE->setCache(
          'facebook_email:'.$third_party_id,
          $email,
        );
        return true;
      } else {
        return false;
      }
    }
  }

  public static async function genFacebookThirdPartyEmail(
    string $third_party_id,
  ): Awaitable<string> {
    self::getIntegrationCacheObject();
    $integration_local_cache =
      self::$INTEGRATION_CACHE->getCache('facebook_email:'.$third_party_id);
    if ($integration_local_cache !== false) {
      return strval($integration_local_cache);
    } else {
      list($client, $access_token) = await self::genFacebookAPIClient();

      try {
        $response =
          $client->get('/'.$third_party_id.'?fields=email', $access_token);
      } catch (FacebookExceptionsFacebookResponseException $e) {
        return '';
      } catch (FacebookExceptionsFacebookSDKException $e) {
        return '';
      }
      $graphNode = $response->getGraphNode();
      $graphNode->getField('email');
      $profile = $response->getGraphUser();
      $email = $profile->getEmail();
      if ($email !== null) {
        self::$INTEGRATION_CACHE->setCache(
          'facebook_exists:'.$third_party_id,
          true,
        );
        self::$INTEGRATION_CACHE->setCache(
          'facebook_email:'.$third_party_id,
          $email,
        );
        return $email;
      } else {
        return '';
      }
    }
  }

}
