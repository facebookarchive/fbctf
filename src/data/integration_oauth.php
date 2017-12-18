<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class IntegrationOAuth {
  public static async function genProcessOAuth(): Awaitable<void> {

    try {
      /* HH_IGNORE_ERROR[1002] */
      SessionUtils::sessionStart();
      SessionUtils::enforceLogin();
    } catch (RedirectException $e) {
      error_log(
        'RedirectException: ('.get_class($e).') '.$e->getTraceAsString(),
      );
      http_response_code($e->getStatusCode());
      Utils::redirect($e->getPath());
    }

    $type = idx(Utils::getGET(), 'type');

    if (!is_string($type)) {
      $type = "none";
    }

    $status = false;

    switch ($type) {
      case "facebook":
        $status = await self::genProcessFacebookOAuth();
        $provider = "Facebook";
        $container = "facebook-link-response";
        $button = "facebook-oauth-button";
        break;
      case "google":
        $status = await self::genProcessGoogleOAuth();
        $provider = "Google";
        $container = "google-link-response";
        $button = "google-oauth-button";
        break;
        // FALLTHROUGH
      default:
        $provider = '';
        $container = '';
        $button = '';
        break;
    }

    await self::genOutput($status, $provider, $container, $button);
  }

  public static async function genOutput(
    bool $status,
    string $provider,
    string $container,
    string $button,
  ): Awaitable<void> {
    await tr_start();
    if ($status === true) { //facebook-link-response
      $message =
        tr('Your FBCTF account was successfully linked with '.$provider.'.');
      $javascript_status =
        'window.opener.document.getElementsByClassName("'.
        $container.
        '")[0].innerHTML = "'.
        tr('Your FBCTF account was successfully linked with '.$provider.'.').
        '"';
      $javascript_button =
        'window.opener.document.getElementsByName("'.
        $button.
        '")[0].style.backgroundColor="#1f7a1f"';
    } else {
      $message = tr(
        'There was an error connecting your account to '.
        $provider.
        ', please try again later.',
      );
      $javascript_status =
        'window.opener.document.getElementsByClassName("'.
        $container.
        '")[0].innerHTML = "'.
        tr(
          'There was an error connecting your account to '.
          $provider.
          ', please try again later.',
        ).
        '"';
      $javascript_button =
        'window.opener.document.getElementsByName("'.
        $button.
        '")[0].style.backgroundColor="#800000"';
    }

    $javascript_close = "window.open('', '_self', ''); window.close();";

    $output =
      <div class="fb-modal-content">
        <script>{$javascript_status}</script>
        <script>{$javascript_button}</script>
        <script>{$javascript_close}</script>
        <header class="modal-title">
          {tr('Facebook OAuth')}
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
  }

  public static async function genProcessFacebookOAuth(): Awaitable<bool> {
    $enabled = await Integration::facebookOAuthEnabled();
    if ($enabled === true) {
      $status = await Integration::genFacebookOAuth();
      return $status;
    }
    return false;
  }

  public static async function genProcessGoogleOAuth(): Awaitable<bool> {
    $enabled = await Integration::googleOAuthEnabled();
    if ($enabled === true) {
      $status = await Integration::genGoogleOAuth();
      return $status;
    }
    return false;
  }

}

/* HH_IGNORE_ERROR[1002] */
$integration_oauth = new IntegrationOAuth();
\HH\Asio\join($integration_oauth->genProcessOAuth());
