<?hh // strict

abstract class Model {
  protected static Db $db = MUST_MODIFY;
  protected static Memcached $mc = MUST_MODIFY;
  protected static string $MC_KEY = MUST_MODIFY;
  protected static int $MC_EXPIRE = 0; // Defaults to indefinite cache life

  protected static Map<string, string> $MC_KEYS = Map {};

  protected static async function genDb(): Awaitable<AsyncMysqlConnection> {
    if (self::$db === MUST_MODIFY) {
      self::$db = Db::getInstance();
    }
    return await self::$db->genConnection();
  }

  /**
   * @codeCoverageIgnore
   */
  protected static function getMc(): Memcached {
    if (self::$mc === MUST_MODIFY) {
      $config = parse_ini_file('../../settings.ini');
      $host = must_have_idx($config, 'MC_HOST');
      $port = must_have_idx($config, 'MC_PORT');
      self::$mc = new Memcached();
      self::$mc->addServer($host, $port);
    }
    return self::$mc;
  }

  protected static function setMCRecords(string $key, mixed $records): void {
    $mc = self::getMc();
    $mc->set(
      static::$MC_KEY.static::$MC_KEYS->get($key),
      $records,
      static::$MC_EXPIRE,
    );
  }

  protected static function getMCRecords(string $key): mixed {
    $mc = self::getMc();
    $mc_result = $mc->get(static::$MC_KEY.static::$MC_KEYS->get($key));
    return $mc_result;
  }

  public static function invalidateMCRecords(?string $key = null): void {
    $mc = self::getMc();
    if ($key === null) {
      foreach (static::$MC_KEYS as $key_name => $mc_key) {
        $mc->delete(static::$MC_KEY.static::$MC_KEYS->get($key_name));
      }
    } else {
      $mc->delete(static::$MC_KEY.static::$MC_KEYS->get($key));
    }
  }
}
