<?hh // strict

class Db {
  private string $settings_file = '../settings.ini';
  private ?array<string, string> $config = null;
  private static Db $instance = MUST_MODIFY;
  private AsyncMysqlConnectionPool $pool = MUST_MODIFY;
  private ?AsyncMysqlConnection $conn = null;

  public static function getInstance(): Db {
    if (self::$instance === MUST_MODIFY) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct() {
    $this->config = parse_ini_file($this->settings_file);
    $options = array(
      'idle_timeout_micros' => 200000,
      'expiration_policy' => 'IdleTime',
    );
    $this->pool = new AsyncMysqlConnectionPool($options);
  }

  private function __clone(): void {}

  public static function getDatabaseStats(): array<string, mixed> {
    $db = self::getInstance();
    return $db->pool->getPoolStats();
  }

  public function getBackupCmd(): string {
    $usr = must_have_idx($this->config, 'DB_USERNAME');
    $pwd = must_have_idx($this->config, 'DB_PASSWORD');
    $db = must_have_idx($this->config, 'DB_NAME');
    $backup_cmd =
      'mysqldump --add-drop-database -u '.
      escapeshellarg($usr).
      ' --password='.
      escapeshellarg($pwd).
      ' '.
      escapeshellarg($db);
    return $backup_cmd;
  }

  public function getRestoreCmd(): string {
    $usr = must_have_idx($this->config, 'DB_USERNAME');
    $pwd = must_have_idx($this->config, 'DB_PASSWORD');
    $db = must_have_idx($this->config, 'DB_NAME');
    $restore_cmd =
      'mysql -u '.
      escapeshellarg($usr).
      ' --password='.
      escapeshellarg($pwd).
      ' '.
      escapeshellarg($db);
    return $restore_cmd;
  }

  public async function genConnection(): Awaitable<AsyncMysqlConnection> {
    await $this->genConnect();
    invariant($this->conn !== null, 'Connection cant be null.');
    return $this->conn;
  }

  public function disconnect(): void {
    $this->conn = null;
  }

  public function isConnected(): bool {
    return $this->conn !== null;
  }

  private async function genConnect(): Awaitable<void> {
    $host = must_have_idx($this->config, 'DB_HOST');
    $port = must_have_idx($this->config, 'DB_PORT');
    $db_name = must_have_idx($this->config, 'DB_NAME');
    $username = must_have_idx($this->config, 'DB_USERNAME');
    $password = must_have_idx($this->config, 'DB_PASSWORD');

    $this->conn = await $this->pool
      ->connect($host, (int) $port, $db_name, $username, $password);
  }
}
