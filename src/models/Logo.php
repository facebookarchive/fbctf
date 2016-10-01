<?hh // strict

class Logo extends Model implements Importable, Exportable {

  const string CUSTOM_LOGO_DIR = '/data/customlogos/';
  const int MAX_CUSTOM_LOGO_SIZE_BYTES = 500000;
  private static array<int, string> $CUSTOM_LOGO_TYPES = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG => 'png',
    IMAGETYPE_GIF => 'gif',
  ];
  // each base64 character (8 bits) encodes 6 bits
  const double BASE64_BYTES_PER_CHAR = 0.75;

  private function __construct(
    private int $id,
    private int $used,
    private int $enabled,
    private int $protected,
    private int $custom,
    private string $name,
    private string $logo,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getName(): string {
    return $this->name;
  }

  public function getLogo(): string {
    return $this->logo;
  }

  public function getUsed(): bool {
    return $this->used === 1;
  }

  public function getEnabled(): bool {
    return $this->enabled === 1;
  }

  public function getProtected(): bool {
    return $this->protected === 1;
  }

  public function getCustom(): bool {
    return $this->custom === 1;
  }

  // Check to see if the logo exists.
  public static async function genCheckExists(string $name): Awaitable<bool> {
    $all_logos = await self::genAllLogos();
    foreach ($all_logos as $l) {
      if ($name === $l->getName() && $l->getEnabled()) {
        return true;
      }
    }
    return false;
  }

  // Enable or disable logo by passing 1 or 0.
  public static async function genSetEnabled(
    int $logo_id,
    bool $enabled,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE logos SET enabled = %d WHERE id = %d LIMIT 1',
      (int) $enabled,
      $logo_id,
    );
  }

  // Retrieve a random logo from the table.
  public static async function genRandomLogo(): Awaitable<string> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT name FROM logos WHERE enabled = 1 ORDER BY RAND() LIMIT 1',
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');

    return $result->mapRows()[0]['name'];
  }

  // Logo model by name
  public static async function genByName(
    string $name,
  ): Awaitable<Logo> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM logos WHERE name = %s',
      $name,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');

    return self::logoFromRow($result->mapRows()[0]);
  }

  // All the logos.
  public static async function genAllLogos(): Awaitable<array<Logo>> {
    $db = await self::genDb();

    $result = await $db->queryf('SELECT * FROM logos');

    $logos = array();
    foreach ($result->mapRows() as $row) {
      $logos[] = self::logoFromRow($row);
    }

    return $logos;
  }

  // All the enabled logos.
  public static async function genAllEnabledLogos(): Awaitable<array<Logo>> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM logos WHERE enabled = 1 AND protected = 0',
    );

    $logos = array();
    foreach ($result->mapRows() as $row) {
      $logos[] = self::logoFromRow($row);
    }

    return $logos;
  }

  private static function logoFromRow(Map<string, string> $row): Logo {
    return new Logo(
      intval(must_have_idx($row, 'id')),
      intval(must_have_idx($row, 'used')),
      intval(must_have_idx($row, 'enabled')),
      intval(must_have_idx($row, 'protected')),
      intval(must_have_idx($row, 'custom')),
      must_have_idx($row, 'name'),
      must_have_idx($row, 'logo'),
    );
  }

  // Import logos.
  public static async function importAll(
    array<string, array<string, mixed>> $elements,
  ): Awaitable<bool> {
    foreach ($elements as $logo) {
      $name = must_have_string($logo, 'name');
      $exist = await self::genCheckExists($name);
      if (!$exist) {
        await self::genCreate(
          (bool) must_have_idx($logo, 'used'),
          (bool) must_have_idx($logo, 'enabled'),
          (bool) must_have_idx($logo, 'protected'),
          $name,
          must_have_string($logo, 'logo'),
        );
      }
    }
    return true;
  }

  // Export logos.
  public static async function exportAll(
  ): Awaitable<array<string, array<string, mixed>>> {
    $all_logos_data = array();
    $all_logos = await self::genAllLogos();

    foreach ($all_logos as $logo) {
      $one_logo = array(
        'name' => $logo->getName(),
        'logo' => $logo->getLogo(),
        'used' => $logo->getUsed(),
        'enabled' => $logo->getEnabled(),
        'protected' => $logo->getProtected(),
        'custom' => $logo->getCustom(),
      );
      array_push($all_logos_data, $one_logo);
    }
    return array('logos' => $all_logos_data);
  }

  // Create logo.
  public static async function genCreate(
    bool $used,
    bool $enabled,
    bool $protected,
    bool $custom,
    string $name,
    string $logo,
  ): Awaitable<Logo> {
    $db = await self::genDb();

    // Create category
    await $db->queryf(
      'INSERT INTO logos (used, enabled, protected, custom, name, logo) VALUES (%d, %d, %d, %d, %s, %s)',
      $used ? 1 : 0,
      $enabled ? 1 : 0,
      $protected ? 1 : 0,
      $custom ? 1 : 0,
      $name,
      $logo,
    );

    // Return newly created logo_id
    $result = await $db->queryf(
      'SELECT * FROM logos WHERE logo = %s LIMIT 1',
      $logo,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return self::logoFromRow($result->mapRows()[0]);
  }

  // Create custom logo
  public static async function genCreateCustom(
    string $base64_data,
  ): Awaitable<?Logo> {
    // Check image size
    $image_size_bytes = strlen($base64_data) * self::BASE64_BYTES_PER_CHAR;
    if ($image_size_bytes > self::MAX_CUSTOM_LOGO_SIZE_BYTES) {
      error_log('Logo file base64 not less than '.(self::MAX_CUSTOM_LOGO_SIZE_BYTES/1000).' kB, was '.($image_size_bytes/1000).' kB');
      return null;
    }
    //invariant(
      //$image_size_bytes < self::MAX_CUSTOM_LOGO_SIZE_BYTES,
      //'Logo file base64 not less than '.(self::MAX_CUSTOM_LOGO_SIZE_BYTES/1000).' kB, was '.($image_size_bytes/1000).' kB'
    //);

    // Get image properties and verify mimetype
    $base64_data = str_replace(' ', '+', $base64_data);
    $binary_data = base64_decode(str_replace(' ', '+', $base64_data));
    $image_info = getimagesizefromstring($binary_data);

    $mimetype = $image_info[2];

    if (!array_key_exists($mimetype, self::$CUSTOM_LOGO_TYPES)) {
      error_log("Image type '$mimetype' not allowed");
      return null;
    }

    $type_extension = self::$CUSTOM_LOGO_TYPES[$mimetype];

    $filename = 'custom-'.time().'-'.md5($base64_data).'.'.$type_extension;
    $filepath = self::CUSTOM_LOGO_DIR.$filename;
    $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
    $full_filepath = $document_root.$filepath;

    error_log($full_filepath);
    file_put_contents($full_filepath, $binary_data);
    if (!chmod($full_filepath, 0444)) {
      error_log("Could not set permissions on logo image at '$full_filepath'");
    }

    $db = await self::genDb();

    $used = true;
    $enabled = true;
    $protected = false;
    $custom = true;
    $logo = await Logo::genCreate(
      $used,
      $enabled,
      $protected,
      $custom,
      $filename,
      $filepath,
    );

    // Return newly created logo_id
    return $logo;
  }
}
