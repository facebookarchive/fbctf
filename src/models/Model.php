<?hh // strict

abstract class Model {

  protected static Db $db = MUST_MODIFY;
  protected static Memcached $mc = MUST_MODIFY;
  protected static Memcached $mc_write = MUST_MODIFY;
  protected static string $MC_KEY = MUST_MODIFY;
  protected static int $MC_EXPIRE = 0; // Defaults to indefinite cache life

  // Used to temporarily store data (like, results from the DB/MC) locally in memory per request
  protected static Cache $CACHE = MUST_MODIFY;

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
      $cluster = must_have_idx($config, 'MC_HOST');
      $port = must_have_idx($config, 'MC_PORT');
      $host = $cluster[array_rand($cluster)];
      self::$mc = new Memcached();
      self::$mc->addServer($host, $port);
    }
    return self::$mc;
  }

  public static function getMemcachedStats(): mixed {
    $stats = array();
    $mc = self::getMcWrite();
    foreach ($mc->getServerList() as $node) {
      $mc_node = new Memcached();
      $mc_node->addServer($node['host'], $node['port']);
      $stats[$node['host']] = $mc_node->getStats();
    }
    return $stats;
  }

  /**
   * @codeCoverageIgnore
   */
  protected static function getMcWrite(): Memcached {
    if (self::$mc_write === MUST_MODIFY) {
      $config = parse_ini_file('../../settings.ini');
      $cluster = must_have_idx($config, 'MC_HOST');
      $port = must_have_idx($config, 'MC_PORT');
      self::$mc_write = new Memcached();
      foreach ($cluster as $node) {
        self::$mc_write->addServer($node, $port);
      }
    }
    return self::$mc_write;
  }

  protected static function setMCRecords(string $key, mixed $records): void {
    self::getCacheClassObject();
    $cache_key = static::$MC_KEY.static::$MC_KEYS->get($key);

    self::writeMCCluster($cache_key, $records);
    self::$CACHE->setCache($cache_key, $records);
  }

  protected static function getMCRecords(string $key): mixed {
    self::getCacheClassObject();
    $cache_key = static::$MC_KEY.static::$MC_KEYS->get($key);

    $local_cache_result = self::$CACHE->getCache($cache_key);
    if ($local_cache_result !== false) {
      return $local_cache_result;
    } else {
      $mc = self::getMc();
      $mc_result = $mc->get($cache_key);
      if ($mc_result !== false) {
        self::$CACHE->setCache($cache_key, $mc_result);
      }

      return $mc_result;
    }
  }

  public static function invalidateMCRecords(?string $key = null): void {
    self::getCacheClassObject();

    if ($key === null) {
      foreach (static::$MC_KEYS as $key_name => $mc_key) {
        $cache_key = static::$MC_KEY.static::$MC_KEYS->get($key_name);
        self::invalidateMCCluster($cache_key);
        self::$CACHE->deleteCache($cache_key);
      }
    } else {
      $cache_key = static::$MC_KEY.static::$MC_KEYS->get($key);
      self::invalidateMCCluster($cache_key);
      self::$CACHE->deleteCache($cache_key);
    }
  }

  public static function flushMCCluster(): bool {
    $mc = self::getMcWrite();
    $status = false;
    foreach ($mc->getServerList() as $node) {
      $mc_node = new Memcached();
      $mc_node->addServer($node['host'], $node['port']);
      $flush_status = $mc_node->flush(0);
      if ($flush_status === true) {
        $status = true;
      }
    }
    return $status;
  }

  protected static function writeMCCluster(
    string $cache_key,
    mixed $records,
  ): void {
    $mc = self::getMcWrite();
    foreach ($mc->getServerList() as $node) {
      $mc_node = new Memcached();
      $mc_node->addServer($node['host'], $node['port']);
      $mc_node->set($cache_key, $records, static::$MC_EXPIRE);
    }
  }

  protected static function invalidateMCCluster(string $cache_key): void {
    $mc = self::getMcWrite();
    foreach ($mc->getServerList() as $node) {
      $mc_node = new Memcached();
      $mc_node->addServer($node['host'], $node['port']);
      $mc_node->delete($cache_key);
    }
  }

  public static function getCacheClassObject(): Cache {
    if (self::$CACHE === MUST_MODIFY) {
      self::$CACHE = new Cache();
    }
    invariant(
      self::$CACHE instanceof Cache,
      'Model::$CACHE should of type Map and not null',
    );
    return self::$CACHE;
  }

  public static function deleteLocalCache(?string $key = null): void {
    self::getCacheClassObject();

    if (get_called_class() === 'Model') {
      self::$CACHE->flushCache();
    } else if ($key === null) {
      foreach (static::$MC_KEYS as $key_name => $mc_key) {
        $cache_key = static::$MC_KEY.static::$MC_KEYS->get($key_name);
        self::$CACHE->deleteCache($cache_key);
      }
    } else {
      $cache_key = static::$MC_KEY.static::$MC_KEYS->get($key);
      self::$CACHE->deleteCache($cache_key);
    }
  }
}
