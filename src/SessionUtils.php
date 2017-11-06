<?hh // strict

class SessionUtils {
  private static string $s_name = 'FBCTF';
  private static int $s_lifetime = 3600;
  private static bool $s_secure = true;
  private static bool $s_httponly = true;
  private static string $s_path = '/';

  private function __construct() {}

  private function __clone(): void {}

  public static function sessionStart(): void {
    \HH\Asio\join(Session::genCleanup(self::$s_lifetime));
    session_set_save_handler(
      array(__CLASS__, 'open'),
      array(__CLASS__, 'close'),
      array(__CLASS__, 'read'),
      array(__CLASS__, 'write'),
      array(__CLASS__, 'destroy'),
      array(__CLASS__, 'gc'),
    );
    session_name(self::$s_name);
    session_set_cookie_params(
      self::$s_lifetime,
      self::$s_path,
      must_have_string(Utils::getSERVER(), 'SERVER_NAME'),
      self::$s_secure,
      self::$s_httponly,
    );
    session_start();
    setcookie(
      self::$s_name,
      session_id(),
      time() + self::$s_lifetime,
      self::$s_path,
      must_have_string(Utils::getSERVER(), 'SERVER_NAME'),
      self::$s_secure,
      self::$s_httponly,
    );
  }

  public static function sessionRefresh(): void {
    session_regenerate_id(true);
  }

  public function open(string $path, string $name): bool {
    return true;
  }

  public function close(): bool {
    return true;
  }

  public function read(string $cookie): string {
    $session = \HH\Asio\join(Session::genSessionDataIfExist($cookie));
    return $session;
  }

  public function write(string $cookie, string $data): bool {
    $session_exists = \HH\Asio\join(Session::genSessionExist($cookie));
    if ($session_exists) {
      \HH\Asio\join(Session::genUpdate($cookie, $data));
      if (strpos($data, 'team_id') !== false) {
        \HH\Asio\join(Session::genSetTeamId($cookie, $data));
      }
    } else {
      \HH\Asio\join(Session::genCreate($cookie, $data));
    }
    return true;
  }

  public function destroy(string $cookie): bool {
    \HH\Asio\join(Session::genDelete($cookie));
    return true;
  }

  public function gc(int $maxlifetime): bool {
    \HH\Asio\join(Session::genCleanup($maxlifetime));
    return true;
  }

  public static function sessionSet(string $name, string $value): void {
    /* HH_IGNORE_ERROR[2050] */
    $_SESSION[$name] = $value;
  }

  public static function sessionLogout(): void {
    $params = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $params["path"],
      $params["domain"],
      $params["secure"],
      $params["httponly"],
    );
    session_destroy();

    throw new IndexRedirectException();
  }

  public static function sessionActive(): bool {
    /* HH_IGNORE_ERROR[2050] */
    return (bool) (array_key_exists('team_id', $_SESSION));
  }

  public static function enforceLogin(): void {
    /* HH_IGNORE_ERROR[2050] */
    if (!self::sessionActive()) {
      throw new LoginRedirectException();
    }
  }

  public static function enforceAdmin(): void {
    /* HH_IGNORE_ERROR[2050] */
    if (!array_key_exists('admin', $_SESSION)) {
      throw new LoginRedirectException();
    }
  }

  public static function sessionAdmin(): bool {
    /* HH_IGNORE_ERROR[2050] */
    return array_key_exists('admin', $_SESSION);
  }

  public static function sessionTeam(): int {
    /* HH_IGNORE_ERROR[2050] */
    return intval(must_have_string($_SESSION, 'team_id'));
  }

  public static function sessionTeamName(): string {
    /* HH_IGNORE_ERROR[2050] */
    return must_have_string($_SESSION, 'name');
  }

  public static function CSRFToken(): string {
    /* HH_IGNORE_ERROR[2050] */
    return must_have_string($_SESSION, 'csrf_token');
  }
}
