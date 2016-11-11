<?hh // strict

class Attachment extends Model {
  // TODO: Configure this
  const string attachmentsDir = '/data/attachments/';

  protected static string $MC_KEY = 'attachments:';

  protected static Map<string, string>
    $MC_KEYS = Map {
      'LEVELS_COUNT' => 'attachment_levels_count',
      'LEVEL_ATTACHMENTS' => 'attachment_levels',
      'ATTACHMENTS' => 'attachments_by_id',
    };

  private function __construct(
    private int $id,
    private int $levelId,
    private string $filename,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getFilename(): string {
    return $this->filename;
  }

  public function getLevelId(): int {
    return $this->levelId;
  }

  // Create attachment for a given level.
  public static async function genCreate(
    string $file_param,
    string $filename,
    int $level_id,
  ): Awaitable<bool> {
    $db = await self::genDb();
    $type = '';
    $local_filename = self::attachmentsDir;

    $files = Utils::getFILES();
    $server = Utils::getSERVER();
    // First we put the file in its place
    if ($files->contains($file_param)) {
      $tmp_name = $files[$file_param]['tmp_name'];
      $type = $files[$file_param]['type'];
      $md5_str = md5_file($tmp_name);

      // Extract extension and name
      $parts = explode('.', $filename, 2);
      $local_filename .= firstx($parts).'_'.$md5_str;

      $extension = idx($parts, 1);
      if ($extension !== null) {
        $local_filename .= '.'.$extension;
      }

      // Avoid php shells
      if (ends_with($local_filename, '.php')) {
        $local_filename .= 's'; // Make the extension 'phps'
      }
      move_uploaded_file(
        $tmp_name,
        must_have_string($server, 'DOCUMENT_ROOT').$local_filename,
      );
    } else {
      return false;
    }

    // Then database shenanigans
    await $db->queryf(
      'INSERT INTO attachments (filename, type, level_id, created_ts) VALUES (%s, %s, %d, NOW())',
      $local_filename,
      (string) $type,
      $level_id,
    );

    self::invalidateMCRecords(); // Invalidate Memcached Attachment data.

    return true;
  }

  // Modify existing attachment.
  public static async function genUpdate(
    int $id,
    int $level_id,
    string $filename,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE attachments SET filename = %s, level_id = %d WHERE id = %d LIMIT 1',
      $filename,
      $level_id,
      $id,
    );
    self::invalidateMCRecords(); // Invalidate Memcached Attachment data.
  }

  // Delete existing attachment.
  public static async function genDelete(int $attachment_id): Awaitable<void> {
    $db = await self::genDb();
    $server = Utils::getSERVER();

    // Copy file to deleted folder
    $attachment = await self::gen($attachment_id);
    $filename = $attachment->getFilename();
    $parts = pathinfo($filename);
    error_log(
      'Copying from '.
      $filename.
      ' to '.
      $parts['dirname'].
      '/deleted/'.
      $parts['basename'],
    );
    $root = strval($server['DOCUMENT_ROOT']);
    $origin = $root.$filename;
    $dest = $root.$parts['dirname'].'/deleted/'.$parts['basename'];
    copy($origin, $dest);

    // Delete file.
    unlink($origin);

    // Delete from table.
    await $db->queryf(
      'DELETE FROM attachments WHERE id = %d LIMIT 1',
      $attachment_id,
    );
    self::invalidateMCRecords(); // Invalidate Memcached Attachment data.
  }

  // Get all attachments for a given level.
  public static async function genAllAttachments(
    int $level_id,
    bool $refresh = false,
  ): Awaitable<array<Attachment>> {
    $mc_result = self::getMCRecords('LEVEL_ATTACHMENTS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $attachments = array();
      $result = await $db->queryf('SELECT * FROM attachments');
      foreach ($result->mapRows() as $row) {
        $attachments[$row->get('level_id')][] = self::attachmentFromRow($row);
      }
      self::setMCRecords('LEVEL_ATTACHMENTS', new Map($attachments));
      $attachments = new Map($attachments);
      if ($attachments->contains($level_id)) {
        $attachment = $attachments->get($level_id);
        invariant(
          is_array($attachment),
          'attachment should be an array of Attachment',
        );
        return $attachment;
      } else {
        return array();
      }
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should be of type Map',
      );
      if ($mc_result->contains($level_id)) {
        $attachment = $mc_result->get($level_id);
        invariant(
          is_array($attachment),
          'attachment should be an array of Attachment',
        );
        return $attachment;
      } else {
        return array();
      }
    }
  }

  // Get a single attachment.
  /* HH_IGNORE_ERROR[4110]: Claims - It is incompatible with void because this async function implicitly returns Awaitable<void>, yet this returns Awaitable<Attachment> and the type is checked on line 185 */
  public static async function gen(
    int $attachment_id,
    bool $refresh = false,
  ): Awaitable<Attachment> {
    $mc_result = self::getMCRecords('ATTACHMENTS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $attachments = Map {};
      $result = await $db->queryf('SELECT * FROM attachments');
      foreach ($result->mapRows() as $row) {
        $attachments->add(
          Pair {intval($row->get('id')), self::attachmentFromRow($row)},
        );
      }
      self::setMCRecords('ATTACHMENTS', $attachments);
      if ($attachments->contains($attachment_id)) {
        $attachment = $attachments->get($attachment_id);
        invariant(
          $attachment instanceof Attachment,
          'attachment should be of type Attachment',
        );
        return $attachment;
      }
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should be of type Map',
      );
      if ($mc_result->contains($attachment_id)) {
        $attachment = $mc_result->get($attachment_id);
        invariant(
          $attachment instanceof Attachment,
          'attachment should be of type Attachment',
        );
        return $attachment;
      }
    }
  }

  // Check if a level has attachments.
  public static async function genHasAttachments(
    int $level_id,
    bool $refresh = false,
  ): Awaitable<bool> {
    $mc_result = self::getMCRecords('LEVELS_COUNT');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $attachment_count = Map {};
      $result =
        await $db->queryf(
          'SELECT levels.id as level_id, COUNT(attachments.id) as count FROM levels LEFT JOIN attachments ON levels.id = attachments.level_id GROUP BY levels.id',
        );
      foreach ($result->mapRows() as $row) {
        $attachment_count->add(
          Pair {intval($row->get('level_id')), intval($row->get('count'))},
        );
      }
      self::setMCRecords('LEVELS_COUNT', $attachment_count);
      if ($attachment_count->contains($level_id)) {
        $level_attachment_count = $attachment_count->get($level_id);
        return intval($level_attachment_count) > 0;
      } else {
        return false;
      }
    } else {
      invariant(
        $mc_result instanceof Map,
        'attachments should be of type Map',
      );
      if ($mc_result->contains($level_id)) {
        $level_attachment_count = $mc_result->get($level_id);
        return intval($level_attachment_count) > 0;
      } else {
        return false;
      }
    }
  }

  private static function attachmentFromRow(
    Map<string, string> $row,
  ): Attachment {
    return new Attachment(
      intval(must_have_idx($row, 'id')),
      intval(must_have_idx($row, 'level_id')),
      must_have_idx($row, 'filename'),
    );
  }
}
