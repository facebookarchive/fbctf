<?hh // strict

class Configuration extends Model {

  protected static string $MC_KEY = 'configuration:';

  protected static Map<string, string>
    $MC_KEYS = Map {
      'CONFIGURATION' => 'config_field',
      'FACEBOOK_INTEGRATION_APP_ID' => 'integration_facebook_app_id',
      'FACEBOOK_INTEGRATION_APP_SECRET' =>
        'integration_facebook_app_secret',
      'GOOGLE_INTEGRATION_FILE' => 'integration_google_file',
    };

  private function __construct(
    private int $id,
    private string $field,
    private string $value,
    private string $description,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getField(): string {
    return $this->field;
  }

  public function getValue(): string {
    return $this->value;
  }

  public function getDescription(): string {
    return $this->description;
  }

  // Get configuration entry.
  public static async function gen(
    string $field,
    bool $refresh = false,
  ): Awaitable<Configuration> {
    $mc_result = self::getMCRecords('CONFIGURATION');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $config_values = Map {};
      $result = await $db->queryf('SELECT * FROM configuration');
      foreach ($result->mapRows() as $row) {
        $config_values->add(
          Pair {
            strval($row->get('field')),
            self::configurationFromRow($row->toArray()),
          },
        );
      }
      self::setMCRecords('CONFIGURATION', $config_values);
      invariant(
        $config_values->contains($field) !== false,
        'config value not found (db): %s',
        $field,
      );
      $config = $config_values->get($field);
      invariant(
        $config instanceof Configuration,
        'config cache value should of type Configuration and not null',
      );
      return $config;
    } else {
      invariant(
        $mc_result instanceof Map,
        'config cache return should be of type Map and not null',
      );
      invariant(
        $mc_result->contains($field) !== false,
        'config value not found (cache): %s',
        $field,
      );
      $config = $mc_result->get($field);
      invariant(
        $config instanceof Configuration,
        'config cache value should of type Configuration and not null',
      );
      return $config;
    }
  }

  // Change configuration field.
  public static async function genUpdate(
    string $field,
    string $value,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE configuration SET value = %s WHERE field = %s LIMIT 1',
      $value,
      $field,
    );
    if ($field === 'login' && intval($value) === 0) {
      await Session::genDeleteAllUnprotected();
    }

    self::invalidateMCRecords(); // Invalidate Configuration data.
  }

  // Check if field is valid.
  public static async function genValidField(string $field): Awaitable<bool> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT COUNT(*) FROM configuration WHERE field = %s',
      $field,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');

    return intval(idx(firstx($result->mapRows()), 'COUNT(*)')) > 0;
  }

  // All the password types.
  public static async function genAllPasswordTypes(
  ): Awaitable<array<Configuration>> {
    $db = await self::genDb();
    $result = await $db->queryf('SELECT * FROM password_types');

    $types = array();
    foreach ($result->mapRows() as $row) {
      $types[] = self::configurationFromRow($row->toArray());
    }

    return $types;
  }

  // Current password type.
  public static async function genCurrentPasswordType(
  ): Awaitable<Configuration> {
    $db = await self::genDb();
    $db_result =
      await $db->queryf(
        'SELECT * FROM password_types WHERE field = (SELECT value FROM configuration WHERE field = %s) LIMIT 1',
        'password_type',
      );

    invariant($db_result->numRows() === 1, 'Expected exactly one result');
    $result = firstx($db_result->mapRows())->toArray();

    return self::configurationFromRow($result);
  }

  // All the configuration.
  public static async function genAllConfiguration(
  ): Awaitable<array<Configuration>> {
    $db = await self::genDb();
    $result = await $db->queryf('SELECT * FROM configuration');

    $configuration = array();
    foreach ($result->mapRows() as $row) {
      $configuration[] = self::configurationFromRow($row->toArray());
    }

    return $configuration;
  }

  private static function configurationFromRow(
    array<string, string> $row,
  ): Configuration {
    return new Configuration(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'field'),
      must_have_idx($row, 'value'),
      must_have_idx($row, 'description'),
    );
  }

  public static function getFacebookOAuthSettingsExists(
    bool $refresh = false,
  ): bool {
    return (self::getFacebookOAuthSettingsAppId($refresh) !== '' &&
            self::getFacebookOAuthSettingsAppSecret($refresh) !== '');
  }

  public static function getFacebookOAuthSettingsAppId(
    bool $refresh = false,
  ): string {
    $mc_result = self::getMCRecords('FACEBOOK_INTEGRATION_APP_ID');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $settings_file = '../../settings.ini';
      $config = parse_ini_file($settings_file);
      $app_id = '';
      if (array_key_exists('FACEBOOK_OAUTH_APP_ID', $config) === true) {
        $app_id = strval($config['FACEBOOK_OAUTH_APP_ID']);
      }
      self::setMCRecords('FACEBOOK_INTEGRATION_APP_ID', $app_id);
      return $app_id;
    } else {
      return strval($mc_result);
    }
  }

  public static function getFacebookOAuthSettingsAppSecret(
    bool $refresh = false,
  ): string {
    $mc_result = self::getMCRecords('FACEBOOK_INTEGRATION_APP_SECRET');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $settings_file = '../../settings.ini';
      $config = parse_ini_file($settings_file);
      $app_secret = '';
      if (array_key_exists('FACEBOOK_OAUTH_APP_SECRET', $config) === true) {
        $app_secret = strval($config['FACEBOOK_OAUTH_APP_SECRET']);
      }
      self::setMCRecords('FACEBOOK_INTEGRATION_APP_SECRET', $app_secret);
      return $app_secret;
    } else {
      return strval($mc_result);
    }
  }

  public static function getGoogleOAuthFileExists(
    bool $refresh = false,
  ): bool {
    return (self::getGoogleOAuthFile($refresh) !== '');
  }

  public static function getGoogleOAuthFile(bool $refresh = false): string {
    $mc_result = self::getMCRecords('GOOGLE_INTEGRATION_FILE');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $settings_file = '../../settings.ini';
      $config = parse_ini_file($settings_file);
      $oauth_file = '';
      if ((array_key_exists('GOOGLE_OAUTH_FILE', $config) === true) &&
          (file_exists($config['GOOGLE_OAUTH_FILE']) === true)) {
        $oauth_file = strval($config['GOOGLE_OAUTH_FILE']);
      }
      self::setMCRecords('GOOGLE_INTEGRATION_FILE', $oauth_file);
      return $oauth_file;
    } else {
      return strval($mc_result);
    }
  }
}
