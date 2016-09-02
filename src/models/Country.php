<?hh // strict

class Country extends Model {

  const string MC_KEY_ALL_COUNTRIES = 'all_countries';
  const string MC_KEY_ALL_COUNTRIES_FOR_MAP = 'all_countries_for_map';
  const string
    MC_KEY_ALL_ENABLED_COUNTRIES = 'all_enabled_countries_for_map';
  const string
    MC_KEY_ALL_ENABLED_COUNTRIES_FOR_MAP = 'all_enabled_countries';

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

    await self::genDeleteAllMemcacheKeys();
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

    await self::genDeleteAllMemcacheKeys();
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

    await self::genDeleteAllMemcacheKeys();
  }

  private static async function genAll(
    string $sql,
    string $mcKey,
  ): Awaitable<array<Country>> {
    $mc = self::getMc();
    $mc_result = $mc->get($mcKey);

    if ($mc_result) {
      $rows = $mc_result;
    } else {
      $db = await self::genDb();
      /* HH_IGNORE_ERROR[4110] */
      /* HH_IGNORE_ERROR[4027] This is safe not being a literal string */
      $db_result = await $db->queryf($sql);
      $rows = array_map($map ==> $map->toArray(), $db_result->mapRows());
      $mc->set($mcKey, $rows);
    }

    $countries = array();
    foreach ($rows as $row) {
      $countries[] = await self::countryFromRow($row);
    }

    usort(
      $countries,
      function($a, $b) {
        return strcmp($a->name, $b->name);
      },
    );

    return $countries;
  }

  public static async function genAllCountries(): Awaitable<array<Country>> {
    return await self::genAll(
      'SELECT * FROM countries ORDER BY iso_code',
      self::MC_KEY_ALL_COUNTRIES,
    );
  }

  public static async function genAllCountriesForMap(
  ): Awaitable<array<Country>> {
    return await self::genAll(
      'SELECT * FROM countries ORDER BY CHAR_LENGTH(d)',
      self::MC_KEY_ALL_COUNTRIES_FOR_MAP,
    );
  }

  public static async function genAllEnabledCountries(
  ): Awaitable<array<Country>> {
    return await self::genAll(
      'SELECT * FROM countries WHERE enabled = 1',
      self::MC_KEY_ALL_ENABLED_COUNTRIES,
    );
  }

  // All enabled countries. The weird sorting is because SVG lack of z-index
  // and things looking like shit in the map. See issue #20.
  public static async function genAllEnabledCountriesForMap(
  ): Awaitable<array<Country>> {
    return await self::genAll(
      'SELECT * FROM countries WHERE enabled = 1 ORDER BY CHAR_LENGTH(d)',
      self::MC_KEY_ALL_ENABLED_COUNTRIES_FOR_MAP,
    );
  }

  // All enabled and unused countries
  public static async function genAllAvailableCountries(
  ): Awaitable<array<Country>> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM countries WHERE enabled = 1 AND used = 0',
    );

    $countries = array();
    foreach ($result->mapRows() as $row) {
      $countries[] = await self::countryFromRow($row->toArray());
    }

    usort(
      $countries,
      function($a, $b) {
        return strcmp($a->name, $b->name);
      },
    );

    return $countries;
  }

  // Check if country is in an active level
  public static async function genIsActiveLevel(
    int $country_id,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT COUNT(*) FROM levels WHERE entity_id = %d AND active = 1',
      $country_id,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return intval(firstx($result->mapRows())['COUNT(*)']) > 0;
  }

  // Get a country by id.
  public static async function gen(int $country_id): Awaitable<Country> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM countries WHERE id = %d LIMIT 1',
      $country_id,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    $new_country =
      await self::countryFromRow(firstx($result->mapRows())->toArray());
    return $new_country;
  }

  // Get a country by name.
  public static async function genCountry(
    string $country,
  ): Awaitable<Country> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM countries WHERE name = %s LIMIT 1',
      $country,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return await self::countryFromRow(firstx($result->mapRows())->toArray());
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

  private static async function countryFromRow(
    array<string, string> $row,
  ): Awaitable<Country> {
    $config = await Configuration::gen('language');
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

  // Check if a country already exists, by name.
  public static async function genCheckExists(
    string $country,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT COUNT(*) FROM countries WHERE name = %s',
      $country,
    );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return (intval(idx($result->mapRows()[0], 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  private static async function genDeleteAllMemcacheKeys(): Awaitable<void> {
    $mc = self::getMc();
    $mc->delete(self::MC_KEY_ALL_COUNTRIES);
    $mc->delete(self::MC_KEY_ALL_COUNTRIES_FOR_MAP);
    $mc->delete(self::MC_KEY_ALL_ENABLED_COUNTRIES);
    $mc->delete(self::MC_KEY_ALL_ENABLED_COUNTRIES_FOR_MAP);
  }
}
