<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

/* HH_IGNORE_ERROR[1002] */
SessionUtils::sessionStart();
SessionUtils::enforceLogin();

if (Configuration::genGoogleOAuthFileExists()) {

  $code = idx(Utils::getGET(), 'code', false);
  $error = idx(Utils::getGET(), 'error', false);
  $state = idx(Utils::getGET(), 'state', false);

  $google_oauth_file = Configuration::genGoogleOAuthFile();
  $client = new Google_Client();
  $client->setAuthConfig($google_oauth_file);
  $client->setAccessType('offline');
  $client->setScopes(['profile email']);
  $client->setRedirectUri(
    'https://'.$_SERVER['HTTP_HOST'].'/data/google_oauth.php',
  );

  $integration_csrf_token = base64_encode(random_bytes(100));
  // Cookie is sent with headers, and therefore not set until after the PHP code executes - this allows us to reset the cookie on each request without clobbering the state
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

  if ($code !== false) {
    $integration_csrf_token = /* HH_IGNORE_ERROR[2050] */
      idx($_COOKIE, 'integration_csrf_token', false);
    if (strval($integration_csrf_token) === '' ||
        strval($state) === '' ||
        strval($integration_csrf_token) != strval($state)) {
      $code = false;
      $error = false;
    }
  }

  if ($code !== false) {
    $client->authenticate($code);
    $access_token = $client->getAccessToken();
    $oauth_client = new Google_Service_Oauth2($client);
    $profile = $oauth_client->userinfo->get();
    $livesync_password_update = \HH\Asio\join(
      Team::genSetLiveSyncPassword(
        SessionUtils::sessionTeam(),
        "google_oauth",
        $profile->email,
        $profile->id,
      ),
    );
    if ($livesync_password_update === true) {
      $message =
        tr('Your FBCTF account was successfully linked with Google.');
      $javascript_status =
        'window.opener.document.getElementsByClassName("google-link-response")[0].innerHTML = "'.
        tr('Your FBCTF account was successfully linked with Google.').
        '"';
    } else {
      $message =
        tr(
          'There was an error connecting your account to Google, please try again later.',
        );
      $javascript_status =
        'window.opener.document.getElementsByClassName("google-link-response")[0].innerHTML = "'.
        tr(
          'There was an error connecting your account to Google, please try again later.',
        ).
        '"';
    }
    $javascript_close = "window.open('', '_self', ''); window.close();";
  } else if ($error === true) {
    $message =
      tr(
        'There was an error connecting your account to Google, please try again later.',
      );
    $javascript_status =
      'window.opener.document.getElementsByClassName("google-link-response")[0].innerHTML = "'.
      tr(
        'There was an error connecting your account to Google, please try again later.',
      ).
      '"';
    $javascript_close = "window.open('', '_self', ''); window.close();";
  } else {
    $auth_url = $client->createAuthUrl();
    header('Location: '.filter_var($auth_url, FILTER_SANITIZE_URL));
    exit;
  }
} else {
  $message = tr('Google OAuth is disabled.');
  $javascript_status =
    'window.opener.document.getElementsByClassName("google-link-response")[0].innerHTML = "'.
    tr('Google OAuth is disabled.').
    '"';
  $javascript_close = "window.open('', '_self', ''); window.close();";
}

$output =
  <div class="fb-modal-content">
    <script>{$javascript_status}</script>
    <script>{$javascript_close}</script>
    <header class="modal-title">
      {tr('Google OAuth')}
      <a href="#" class="js-close-modal">
        <svg class="icon icon--close">
          <use href="#icon--close" />
        </svg>
      </a>
    </header>
    <span>{$message}</span>
    <br />
    <span>
      <button onclick={"window.open('', '_self', ''); window.close();"}>
        Close Window
      </button>
    </span>
  </div>;

print $output;
