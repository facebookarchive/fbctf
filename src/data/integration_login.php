<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

/* HH_IGNORE_ERROR[1002] */
SessionUtils::sessionStart();

class IntegrationLogin {
  public static async function genProcessLogin(): Awaitable<void> {
    $type = idx(Utils::getGET(), 'type');

    if (!is_string($type)) {
      $type = "none";
    }

    switch ($type) {
      case "facebook":
        await self::genProcessFacebookLogin();
        break;
      case "google":
        await self::genProcessGoogleLogin();
        break;
        // FALLTHROUGH
      default:
        header('Location: /index.php?page=login');
        exit;
        break;
    }
  }

  public static async function genProcessFacebookLogin(): Awaitable<void> {
    $enabled = await Integration::facebookLoginEnabled();
    if ($enabled === true) {
      $url = await Integration::genFacebookLogin();
      header('Location: '.filter_var($url, FILTER_SANITIZE_URL));
      exit;
    } else {
      header('Location: /index.php?page=login');
      exit;
    }
  }

  public static async function genProcessGoogleLogin(): Awaitable<void> {
    $enabled = await Integration::googleLoginEnabled();
    if ($enabled === true) {
      $url = await Integration::genGoogleLogin();
      header('Location: '.filter_var($url, FILTER_SANITIZE_URL));
      exit;
    } else {
      header('Location: /index.php?page=login');
      exit;
    }
  }
}

$integration_login = new IntegrationLogin();
\HH\Asio\join($integration_login->genProcessLogin());
