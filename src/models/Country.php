<?hh // strict

class Country extends Model {

  protected static string $MC_KEY = 'country:';

  protected static Map<string, string>
    $MC_KEYS = Map {
      "ALL_COUNTRIES" => "all_countries",
      "ALL_COUNTRIES_BY_ID" => "all_countries_by_id",
      "ALL_COUNTRIES_FOR_MAP" => "all_countries_for_map",
      "ALL_ENABLED_COUNTRIES" => "all_enabled_countries",
      "ALL_ENABLED_COUNTRIES_FOR_MAP" => "all_enabled_countries_for_map",
      "ALL_AVAILABLE_COUNTRIES" => "ALL_AVAILABLE_COUNTRIES",
    };

  private function __construct(
    private int $id,
    private string $iso_code,
    private string $name,
    private int $used,
    private int $enabled,
    private string $d,
    private string $transform,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getIsoCode(): string {
    return $this->iso_code;
  }

  public function getName(): string {
    return $this->name;
  }

  public function getUsed(): bool {
    return $this->used === 1;
  }

  public function getEnabled(): bool {
    return $this->enabled === 1;
  }

  public function getD(): string {
    return $this->d;
  }

  public function getTransform(): string {
    return $this->transform;
  }

  // Make sure all the countries used field is good
  public static async function genUsedAdjust(): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE countries SET used = 1 WHERE id IN (SELECT entity_id FROM levels)',
    );
    await $db->queryf(
      'UPDATE countries SET used = 0 WHERE id NOT IN (SELECT entity_id FROM levels)',
    );

    self::invalidateMCRecords(); // Invalidate Memcached Country data.
  }

  // Enable or disable a country
  public static async function genSetStatus(
    int $country_id,
    bool $status,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE countries SET enabled = %d WHERE id = %d',
      $status ? 1 : 0,
      $country_id,
    );

    self::invalidateMCRecords(); // Invalidate Memcached Country data.
  }

  // Set the used flag for a country
  public static async function genSetUsed(
    int $country_id,
    bool $status,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE countries SET used = %d WHERE id = %d LIMIT 1',
      $status ? 1 : 0,
      $country_id,
    );

    self::invalidateMCRecords(); // Invalidate Memcached Country data.
  }

  private static async function genAll(
    string $sql,
  ): Awaitable<array<Country>> {
    $db = await self::genDb();
    $all_countries = Map {};
    /* HH_IGNORE_ERROR[4110] */
    /* HH_IGNORE_ERROR[4027]: This is safe not being a literal string */
    $db_result = await $db->queryf($sql);
    $rows = $db_result->mapRows();

    foreach ($rows as $row) {
      $all_countries->add(
        Pair {intval($row->get("id")), self::countryFromRow($row)},
      );
    }

    $countries = array();
    /* HH_IGNORE_ERROR[4062]: getMCRecords returns a 'mixed' type, HHVM is unsure of the type at this point */
    $countries = $all_countries->toValuesArray();

    usort(
      $countries,
      function($a, $b) {
        return strcmp($a->name, $b->name);
      },
    );

    return $countries;
  }

  public static async function genAllCountries(
    bool $refresh = false,
  ): Awaitable<array<Country>> {
    $mc_result = self::getMCRecords('ALL_COUNTRIES');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $all_countries =
        await self::genAll('SELECT * FROM countries ORDER BY iso_code');
      self::setMCRecords('ALL_COUNTRIES', $all_countries);
    }
    /* HH_IGNORE_ERROR[4110]: getMCRecords returns a 'mixed' type, HHVM is unsure of the type at this point */
    return self::getMCRecords('ALL_COUNTRIES');
  }

  public static async function genAllCountriesForMap(
    bool $refresh = false,
  ): Awaitable<array<Country>> {
    $mc_result = self::getMCRecords('ALL_COUNTRIES_FOR_MAP');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $all_countries =
        await self::genAll('SELECT * FROM countries ORDER BY CHAR_LENGTH(d)');
      self::setMCRecords('ALL_COUNTRIES_FOR_MAP', $all_countries);
    }
    /* HH_IGNORE_ERROR[4110]: getMCRecords returns a 'mixed' type, HHVM is unsure of the type at this point */
    return self::getMCRecords('ALL_COUNTRIES_FOR_MAP');
  }

  public static async function genAllEnabledCountries(
    bool $refresh = false,
  ): Awaitable<array<Country>> {
    $mc_result = self::getMCRecords('ALL_ENABLED_COUNTRIES');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $all_countries =
        await self::genAll('SELECT * FROM countries WHERE enabled = 1');
      self::setMCRecords('ALL_ENABLED_COUNTRIES', $all_countries);
    }
    /* HH_IGNORE_ERROR[4110]: getMCRecords returns a 'mixed' type, HHVM is unsure of the type at this point */
    return self::getMCRecords('ALL_ENABLED_COUNTRIES');
  }

  // All enabled countries. The weird sorting is because SVG lack of z-index
  // and things looking like shit in the map. See issue #20.
  public static async function genAllEnabledCountriesForMap(
    bool $refresh = false,
  ): Awaitable<array<Country>> {
    $mc_result = self::getMCRecords('ALL_ENABLED_COUNTRIES_FOR_MAP');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $all_countries = await self::genAll(
        'SELECT * FROM countries WHERE enabled = 1 ORDER BY CHAR_LENGTH(d)',
      );
      self::setMCRecords('ALL_ENABLED_COUNTRIES_FOR_MAP', $all_countries);
    }
    /* HH_IGNORE_ERROR[4110]: getMCRecords returns a 'mixed' type, HHVM is unsure of the type at this point */
    return self::getMCRecords('ALL_ENABLED_COUNTRIES_FOR_MAP');
  }

  // All enabled and unused countries
  public static async function genAllAvailableCountries(
    bool $refresh = false,
  ): Awaitable<array<Country>> {
    $mc_result = self::getMCRecords('ALL_AVAILABLE_COUNTRIES');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $all_countries = await self::genAll(
        'SELECT * FROM countries WHERE enabled = 1 AND used = 0',
      );
      self::setMCRecords('ALL_AVAILABLE_COUNTRIES', $all_countries);
    }
    /* HH_IGNORE_ERROR[4110]: getMCRecords returns a 'mixed' type, HHVM is unsure of the type at this point */
    return self::getMCRecords('ALL_AVAILABLE_COUNTRIES');
  }

  // Check if country is in an active level
  public static async function genIsActiveLevel(
    int $country_id,
  ): Awaitable<bool> {
    return Level::genWhoUses($country_id) != null;
  }

  // Get a country by id.
  /* HH_IGNORE_ERROR[4110]: HHVM is concerned that the dountry might not be present, this is verified by the caller */
  public static async function gen(
    int $country_id,
    bool $refresh = false,
  ): Awaitable<Country> {
    $mc_result = self::getMCRecords('ALL_COUNTRIES_BY_ID');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $all_countries = Map {};
      $result = await $db->queryf('SELECT * FROM countries ORDER BY id');
      foreach ($result->mapRows() as $row) {
        $all_countries->add(
          Pair {intval($row->get("id")), self::countryFromRow($row)},
        );
      }
      self::setMCRecords('ALL_COUNTRIES_BY_ID', $all_countries);
    }
    $countries = self::getMCRecords('ALL_COUNTRIES_BY_ID');
    /* HH_IGNORE_ERROR[4062]: getMCRecords returns a 'mixed' type, HHVM is unsure of the type at this point */
    if ($countries->contains($country_id)) {
      /* HH_IGNORE_ERROR[4062]: getMCRecords returns a 'mixed' type, HHVM is unsure of the type at this point */
      return $countries->get($country_id);
    }
  }

  // Get a country by iso_code.
  public static async function genCountry(
    string $country,
  ): Awaitable<Country> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM countries WHERE iso_code = %s LIMIT 1',
      $country,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    /* HH_IGNORE_ERROR[4110]: getMCRecords returns a 'mixed' type, HHVM is unsure of the type at this point */
    return self::countryFromRow($result->mapRows());
  }

  // Get a random enabled, unused country ID
  public static async function genRandomAvailableCountryId(): Awaitable<int> {
    $db = await self::genDb();

    $result =
      await $db->queryf(
        'SELECT id FROM countries WHERE enabled = 1 AND used = 0 ORDER BY RAND() LIMIT 1',
      );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return intval(firstx($result->mapRows())['id']);
  }

  private static function countryFromRow(Map<string, string> $row): Country {
    $config = \HH\Asio\join(Configuration::gen('language'));
    $language = $config->getValue();
    $translated_name = locale_get_display_region(
      '-'.must_have_idx($row, 'iso_code'),
      $language,
    );
    return new Country(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'iso_code'),
      $translated_name,
      intval(must_have_idx($row, 'used')),
      intval(must_have_idx($row, 'enabled')),
      must_have_idx($row, 'd'),
      must_have_idx($row, 'transform'),
    );
  }

  // Check if a country already exists, by iso_code
  public static async function genCheckExists(
    string $country,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT COUNT(*) FROM countries WHERE iso_code = %s',
      $country,
    );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return (intval(idx($result->mapRows()[0], 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

}
