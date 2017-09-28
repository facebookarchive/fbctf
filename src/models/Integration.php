<?hh // strict

use Facebook\GraphNodes\GraphNode\Collection as GraphCollection;

class Integration extends Model {

  private function __construct(private string $type) {}

  public function getType(): string {
    return $this->type;
  }

  public static async function facebookOAuthEnabled(): Awaitable<bool> {
    $oauth = Configuration::genFacebookOAuthSettingsExists();

    if ($oauth) {
      return true;
    } else {
      return false;
    }
  }

  public static async function googleOAuthEnabled(): Awaitable<bool> {
    $oauth = Configuration::genGoogleOAuthFileExists();

    if ($oauth) {
      return true;
    } else {
      return false;
    }
  }

  public static async function facebookLoginEnabled(): Awaitable<bool> {
    $login_facebook = await Configuration::gen('login_facebook');
    $oauth = Configuration::genFacebookOAuthSettingsExists();

    $login_facebook_enabled =
      $login_facebook->getValue() === '1' ? true : false;

    if (($oauth) && ($login_facebook_enabled)) {
      return true;
    } else {
      return false;
    }
  }

  public static async function googleLoginEnabled(): Awaitable<bool> {
    $login_google = await Configuration::gen('login_google');
    $oauth = Configuration::genGoogleOAuthFileExists();

    $login_google_enabled = $login_google->getValue() === '1' ? true : false;

    if (($oauth) && ($login_google_enabled)) {
      return true;
    } else {
      return false;
    }
  }

  public static async function genFacebookAuthURL(
    string $redirect,
  ): Awaitable<(Facebook, string)> {
    $host = strval(idx(Utils::getSERVER(), 'HTTP_HOST'));
    $app_id = Configuration::genFacebookOAuthSettingsAppId();
    $app_secret = Configuration::genFacebookOAuthSettingsAppSecret();
    $client = new Facebook\Facebook(
      [
        'app_id' => $app_id, // Replace {app-id} with your app id
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

    return tuple($client, $auth_url);
  }

  public static async function genGoogleAuthURL(
    string $redirect,
  ): Awaitable<(Google_Client, string)> {
    $host = strval(idx(Utils::getSERVER(), 'HTTP_HOST'));
    $google_oauth_file = Configuration::genGoogleOAuthFile();
    $client = new Google_Client();
    $client->setAuthConfig($google_oauth_file);
    $client->setAccessType('offline');
    $client->setScopes(['profile email']);
    $client->setRedirectUri(
      'https://'.$host.'/data/integration_'.$redirect.'.php?type=google',
    );

    $auth_url = $client->createAuthUrl();

    return tuple($client, $auth_url);
  }

  public static async function genFacebookLogin(): Awaitable<string> {
    list($client, $url) = await self::genFacebookAuthURL("login");
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

      $url = '/index.php?page=login';
      if ($graph_error !== true) {
        $response =
          $client->get('/me?fields=id,third_party_id,email', $accessToken);
        $profile = $response->getGraphUser();
        $email = $profile['email'];
        $id = $profile['third_party_id'];

        $oauth_token_exists =
          await Team::genAuthTokenExists("facebook_oauth", strval($email));

        $registration_facebook =
          await Configuration::gen('registration_facebook');
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

    $code = idx(Utils::getGET(), 'code');
    $error = idx(Utils::getGET(), 'error');

    if (!is_string($code)) {
      $code = false;
    }

    if (!is_string($error)) {
      $error = false;
    }

    if ($code !== false) {
      $url = '/index.php?page=login';
      $client->authenticate($code);
      $access_token = $client->getAccessToken();
      $oauth_client = new Google_Service_Oauth2($client);
      $profile = $oauth_client->userinfo->get();
      $email = $profile->email;
      $id = $profile->id;

      $oauth_token_exists =
        await Team::genAuthTokenExists('google_oauth', strval($email));

      $registration_google = await Configuration::gen('registration_google');
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
        (string) gmp_strval(
          gmp_init(bin2hex(openssl_random_pseudo_bytes(16)), 16),
          62,
        ),
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

    $code = idx(Utils::getGET(), 'code');
    $error = idx(Utils::getGET(), 'error');

    if (!is_string($code)) {
      $code = false;
    }

    if (!is_string($error)) {
      $error = false;
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
    $livesync_password_update =
      await Team::genSetLiveSyncPassword($team_id, $type, $email, $id);

    $oauth_token_update =
      await Team::genSetOAuthToken($team_id, $type, $email);

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
  ): Awaitable<int> {
    $registration_prefix = await Configuration::gen('registration_prefix');
    $logo_name = await Logo::genRandomLogo();
    $team_password = Team::generateHash(random_bytes(100));
    $team_name = substr(
      substr($registration_prefix->getValue(), 0 , 14)."-".bin2hex(random_bytes(12)),
      0,
      20,
    );
    $team_id = await Team::genCreate($team_name, $team_password, $logo_name);
    return $team_id;
  }
}
