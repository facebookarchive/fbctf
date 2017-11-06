<?hh // strict

class Logo extends Model implements Importable, Exportable {

  protected static string $MC_KEY = 'logo:';

  protected static Map<string, string>
    $MC_KEYS = Map {
      'ALL_LOGOS' => 'all_logos',
      'ALL_ENABLED_LOGOS' => 'all_enabled_logos',
    };

  const string CUSTOM_LOGO_DIR = '/data/customlogos/';
  const int MAX_CUSTOM_LOGO_SIZE_BYTES = 1000000;
  const int DEFAULT_LOGO_WIDTH = 80;
  const int DEFAULT_LOGO_HEIGHT = 62;
  const string DEFAULT_LOGO_TYPE = "png";
  private static array<int, string>
    $CUSTOM_LOGO_TYPES = [
      IMAGETYPE_JPEG => 'jpg',
      IMAGETYPE_PNG => 'png',
      IMAGETYPE_GIF => 'gif',
    ];
  // each base64 character (8 bits) encodes 6 bits
  const float BASE64_BYTES_PER_CHAR = 0.75;

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
    if ($all_logos->contains($name) === true) {
      invariant($all_logos->contains($name) !== false, 'logo not found');
      $logo = $all_logos->get($name);
      invariant($logo instanceof Logo, 'logo should be of type Logo');
      if ($logo->getEnabled() === true) {
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
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
    self::invalidateMCRecords();
  }

  // Set logo as used or unused by passing 1 or 0.
  public static async function genSetUsed(
    string $logo_name,
    bool $used,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE logos SET used = %d WHERE name = %s LIMIT 1',
      (int) $used,
      $logo_name,
    );
    self::invalidateMCRecords();
  }

  // Retrieve a random logo from the table.
  public static async function genRandomLogo(): Awaitable<string> {
    $all_logos = await self::genAllLogos();
    $all_logos = $all_logos->toArray();
    $random_logo = array_rand($all_logos);
    return $random_logo;
  }

  // Logo model by name
  public static async function genByName(
    string $name,
    bool $refresh = false,
  ): Awaitable<Logo> {
    $all_logos = await self::genAllLogos();
    invariant($all_logos->contains($name) !== false, 'logo not found');
    $logo = $all_logos->get($name);
    invariant($logo instanceof Logo, 'logo should be of type Logo');
    return $logo;
  }

  // All the logos.
  public static async function genAllLogos(
    bool $refresh = false,
  ): Awaitable<Map<string, Logo>> {
    $mc_result = self::getMCRecords('ALL_LOGOS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $all_logos = Map {};
      $result = await $db->queryf('SELECT * FROM logos ORDER BY id');
      foreach ($result->mapRows() as $row) {
        $all_logos->add(
          Pair {strval($row->get('name')), self::logoFromRow($row)},
        );
      }
      self::setMCRecords('ALL_LOGOS', $all_logos);
      return $all_logos;
    } else {
      invariant(
        $mc_result instanceof Map,
        'cached return should be of type Map',
      );
      return $mc_result;
    }
  }

  // All the enabled logos.
  public static async function genAllEnabledLogos(
    bool $refresh = false,
  ): Awaitable<array<Logo>> {
    $mc_result = self::getMCRecords('ALL_ENABLED_LOGOS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $all_enabled_logos = array();
      $result =
        await $db->queryf(
          'SELECT * FROM logos WHERE enabled = 1 AND used = 0 AND protected = 0 AND custom = 0',
        );
      //  If all logos are used, provide all logos with no restriction on unused.
      if ($result->numRows() === 0) {
        $all_logos = await self::genAllLogos();
        $all_enabled_logos = $all_logos->toArray();
      } else {
        foreach ($result->mapRows() as $row) {
          $all_enabled_logos[] = self::logoFromRow($row);
        }
      }
      self::setMCRecords('ALL_ENABLED_LOGOS', $all_enabled_logos);
      invariant(
        is_array($all_enabled_logos),
        'all_enabled_logos should be an array of Logo',
      );
      return $all_enabled_logos;
    } else {
      invariant(
        is_array($mc_result),
        'cache return should be an array of Logo',
      );
      return $mc_result;
    }
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
      $exist = await self::genCheckExists($name); // TODO: Combine Awaits
      if (!$exist) {
        await self::genCreate(
          (bool) must_have_idx($logo, 'used'),
          (bool) must_have_idx($logo, 'enabled'),
          (bool) must_have_idx($logo, 'protected'),
          (bool) must_have_idx($logo, 'custom'),
          $name,
          must_have_string($logo, 'logo'),
        ); // TODO: Combine Awaits
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
    $result =
      await $db->queryf('SELECT * FROM logos WHERE logo = %s LIMIT 1', $logo);

    invariant($result->numRows() === 1, 'Expected exactly one result');
    self::invalidateMCRecords();
    return self::logoFromRow($result->mapRows()[0]);
  }

  // Create custom logo
  public static async function genCreateCustom(
    string $base64_data,
    bool $branding = false,
  ): Awaitable<?Logo> {
    // Check image size
    $image_size_bytes = strlen($base64_data) * self::BASE64_BYTES_PER_CHAR;
    if ($image_size_bytes > self::MAX_CUSTOM_LOGO_SIZE_BYTES) {
      error_log(
        'Logo file base64 not less than '.
        (self::MAX_CUSTOM_LOGO_SIZE_BYTES / 1000).
        ' kB, was '.
        ($image_size_bytes / 1000).
        ' kB',
      );
      return null;
    }

    // Get image properties and verify mimetype
    $base64_data = str_replace(' ', '+', $base64_data);
    $binary_data = base64_decode(str_replace(' ', '+', $base64_data));
    /* HH_IGNORE_ERROR[2049] */
    /* HH_IGNORE_ERROR[4107] */
    $image_info = getimagesizefromstring($binary_data);

    $mimetype = $image_info[2];

    if (!array_key_exists($mimetype, self::$CUSTOM_LOGO_TYPES)) {
      error_log("Image type '$mimetype' not allowed");
      return null;
    }

    $binary_data =
      self::resizeCustomLogo($binary_data, $image_info[1], $image_info[0]);

    $type_extension = self::DEFAULT_LOGO_TYPE;

    $filename = 'custom-'.time().'-'.md5($base64_data).'.'.$type_extension;
    $filepath = self::CUSTOM_LOGO_DIR.$filename;
    $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
    $full_filepath = $document_root.$filepath;

    file_put_contents($full_filepath, $binary_data);
    if (!chmod($full_filepath, 0444)) {
      error_log(
        "Could not set permissions on logo image at '$full_filepath'",
      );
    }

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

    // If created logo is for branding, set configuration value
    if ($branding) {
      await Configuration::genUpdate('custom_logo_image', $logo->getLogo());
    }

    // Return newly created logo_id
    return $logo;
  }

  public static function resizeCustomLogo(
    string $binary_data,
    int $original_width,
    int $original_height,
  ): string {
    $source_image = imagecreatefromstring($binary_data);
    $resized_image = imagecreatetruecolor(
      self::DEFAULT_LOGO_WIDTH,
      self::DEFAULT_LOGO_HEIGHT,
    );
    imagealphablending($resized_image, false);
    imagesavealpha($resized_image, true);
    imagecopyresampled(
      $resized_image,
      $source_image,
      0,
      0,
      0,
      0,
      self::DEFAULT_LOGO_WIDTH,
      self::DEFAULT_LOGO_HEIGHT,
      $original_width,
      $original_height,
    );

    $binary_data = "";
    switch (self::DEFAULT_LOGO_TYPE) {
      case "png":
        ob_start();
        imagepng($resized_image);
        $binary_data = ob_get_contents();
        ob_end_clean();
        break;
      case "jpg":
        ob_start();
        imagejpeg($resized_image);
        $binary_data = ob_get_contents();
        ob_end_clean();
        break;
      case "gif":
        ob_start();
        imagegif($resized_image);
        $binary_data = ob_get_contents();
        ob_end_clean();
        break;
      case "bmp":
        ob_start();
        imagewbmp($resized_image);
        $binary_data = ob_get_contents();
        ob_end_clean();
        break;
      default:
        ob_start();
        imagepng($resized_image);
        $binary_data = ob_get_contents();
        ob_end_clean();
        break;
    }

    return $binary_data;
  }
}
