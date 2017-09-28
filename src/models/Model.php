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
    $cache_key = static::$MC_KEY.static::$MC_KEYS->get($key);

    /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */
    if (is_null($GLOBALS['GLOBAL_CACHE']) === true) {
      /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */
      $GLOBALS['GLOBAL_CACHE'] = new Cache();
    }

    invariant(
      /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */ $GLOBALS['GLOBAL_CACHE'] instanceof Cache,
      'GLOBAL_CACHE should of type Map and not null',
    );

    $mc = self::getMc();
    $mc->set($cache_key, $records, static::$MC_EXPIRE);

    /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */
    $GLOBALS['GLOBAL_CACHE']->setCache($cache_key, $records);
  }

  protected static function getMCRecords(string $key): mixed {
    $cache_key = static::$MC_KEY.static::$MC_KEYS->get($key);

    /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */
    if (is_null($GLOBALS['GLOBAL_CACHE']) === true) {
      /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */
      $GLOBALS['GLOBAL_CACHE'] = new Cache();
    }

    invariant(
      /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */ $GLOBALS['GLOBAL_CACHE'] instanceof Cache,
      'GLOBAL_CACHE should of type Map and not null',
    );

    /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */
    $global_cache_result = $GLOBALS['GLOBAL_CACHE']->getCache($cache_key);
    if ($global_cache_result !== false) {
      return $global_cache_result;
    } else {
      $mc = self::getMc();
      $mc_result = $mc->get($cache_key);
      if ($mc_result !== false) {
        /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */
        $GLOBALS['GLOBAL_CACHE']->setCache($cache_key, $mc_result);
      }
      return $mc_result;
    }
  }

  public static function invalidateMCRecords(?string $key = null): void {
    /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */
    if (is_null($GLOBALS['GLOBAL_CACHE']) === true) {
      /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */
      $GLOBALS['GLOBAL_CACHE'] = new Cache();
    }

    invariant(
      /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */ $GLOBALS['GLOBAL_CACHE'] instanceof Cache,
      'GLOBAL_CACHE should of type Map and not null',
    );

    $mc = self::getMc();
    if ($key === null) {
      foreach (static::$MC_KEYS as $key_name => $mc_key) {
        $cache_key = static::$MC_KEY.static::$MC_KEYS->get($key_name);
        $mc->delete($cache_key);
        /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */
        $GLOBALS['GLOBAL_CACHE']->deleteCache($cache_key);
      }
    } else {
      $cache_key = static::$MC_KEY.static::$MC_KEYS->get($key);
      $mc->delete($cache_key);
      /* HH_IGNORE_ERROR[2050]:  Usage of `global` was causing HH_ERROR 1002 and parsing errors, using the $GLOBALS superglobal instead. */
      $GLOBALS['GLOBAL_CACHE']->deleteCache($cache_key);
    }
  }
}
