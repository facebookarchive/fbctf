<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

/* HH_IGNORE_ERROR[1002] */
SessionUtils::sessionStart();
SessionUtils::enforceLogin();

if (Configuration::genGoogleOAuthFileExists()) {
  $google_oauth_file = Configuration::genGoogleOAuthFile();
  $client = new Google_Client();
  $client->setAuthConfig($google_oauth_file);
  $client->setAccessType('offline');
  $client->setScopes(['profile email']);

  if (isset($_GET['code'])) {
    $client->authenticate($_GET['code']);
    $access_token = $client->getAccessToken();
    $oauth_client = new Google_Service_Oauth2($client);
    $profile = $oauth_client->userinfo->get();
    $livesync_key = base64_encode($profile->email);
    $livesync_password_update = \HH\Asio\join(
      Team::genSetLiveSyncPassword(
        SessionUtils::sessionTeam(),
        "google_oauth",
        $profile->email,
        $livesync_key,
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
  } else if (isset($_GET['error'])) {
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
      {$title}
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
