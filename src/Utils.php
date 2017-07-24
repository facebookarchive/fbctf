<?hh // strict

/* HH_IGNORE_ERROR[2001] */
/* HH_IGNORE_ERROR[2035] */
const MUST_MODIFY = /* UNSAFE_EXPR */ "<<must-modify:\xEE\xFF\xFF>";

function must_have_idx<Tk, Tv>(?KeyedContainer<Tk, Tv> $arr, Tk $idx): Tv {
  invariant($arr !== null, 'Container is null');
  $result = idx($arr, $idx);
  invariant($result !== null, 'Index %s not found in container', $idx);
  return $result;
}

function must_have_string<Tk as string, Tv>(
  ?KeyedContainer<Tk, Tv> $arr,
  Tk $idx,
): string {
  $result = must_have_idx($arr, $idx);
  invariant(is_string($result), 'Expected %s to be a string', strval($idx));
  return $result;
}

function must_have_int<Tk as string, Tv>(
  ?KeyedContainer<Tk, Tv> $arr,
  Tk $idx,
): int {
  $result = must_have_idx($arr, $idx);
  invariant(is_int($result), 'Expected %s to be an int', strval($idx));
  return $result;
}

function must_have_bool<Tk as string, Tv>(
  ?KeyedContainer<Tk, Tv> $arr,
  Tk $idx,
): bool {
  $result = must_have_idx($arr, $idx);
  invariant(is_bool($result), 'Expected %s to be a bool', strval($idx));
  return $result;
}

function firstx<T>(Traversable<T> $t): T {
  foreach ($t as $v) {
    return $v;
  }
  invariant_violation('Expected non-empty collection');
}

function starts_with(string $haystack, string $needle): bool {
  return substr($haystack, 0, strlen($needle)) === $needle;
}

function ends_with(string $haystack, string $needle): bool {
  return substr($haystack, -strlen($needle)) === $needle;
}

function time_ago(string $ts): string {
  $ts_epoc = strtotime($ts);
  $elapsed = time() - $ts_epoc;

  if ($elapsed < 1) {
    return tr('just now');
  }

  $w = array(
    24 * 60 * 60 => tr('d'),
    60 * 60 => tr('hr'),
    60 => tr('min'),
    1 => tr('sec'),
  );
  $w_s = array(
    tr('d') => tr('ds'),
    tr('hr') => tr('hrs'),
    tr('min') => tr('mins'),
    tr('sec') => tr('secs'),
  );
  foreach ($w as $secs => $str) {
    $d = $elapsed / $secs;
    if ($d >= 1) {
      $r = round($d);
      return $r.' '.($r > 1 ? $w_s[$str] : $str).' '.tr('ago');
    }
  }
  return '';
}

class Utils {
  private function __construct() {}

  private function __clone(): void {}

  public static function getGET(): Map<string, mixed> {
    /* HH_IGNORE_ERROR[2050] */
    return new Map($_GET);
  }

  public static function getPOST(): Map<string, mixed> {
    /* HH_IGNORE_ERROR[2050] */
    return new Map($_POST);
  }

  public static function getSERVER(): Map<string, mixed> {
    /* HH_IGNORE_ERROR[2050] */
    return new Map($_SERVER);
  }

  public static function getFILES(): Map<string, array<string, mixed>> {
    /* HH_IGNORE_ERROR[2050] */
    return new Map($_FILES);
  }

  public static function redirect(string $location): void {
    header('Location: '.$location);
  }

  public static function request_response(
    string $result,
    string $msg,
    string $redirect,
  ): string {
    $response_data = array(
      'result' => $result,
      'message' => $msg,
      'redirect' => $redirect,
    );
    return json_encode($response_data);
  }

  public static function hint_response(string $msg, string $result): string {
    $response_data = array('hint' => $msg, 'result' => $result);
    return json_encode($response_data);
  }

  public static function ok_response(string $msg, string $redirect): string {
    return self::request_response('OK', $msg, $redirect);
  }

  public static function error_response(
    string $msg,
    string $redirect,
  ): string {
    return self::request_response('ERROR', $msg, $redirect);
  }
}
