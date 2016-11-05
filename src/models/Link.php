<?hh // strict

class Link extends Model {

  protected static string $MC_KEY = 'links:';

  protected static Map<string, string>
    $MC_KEYS = Map {"LEVELS_COUNT" => "link_levels_count"};

  private function __construct(
    private int $id,
    private int $levelId,
    private string $link,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getLevelId(): int {
    return $this->levelId;
  }

  public function getLink(): string {
    return $this->link;
  }

  // Create link for a given level.
  public static async function genCreate(
    string $link,
    int $level_id,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'INSERT INTO links (link, level_id, created_ts) VALUES (%s, %d, NOW())',
      $link,
      $level_id,
    );
    self::invalidateMCRecords(); // Invalidate Memcached Links data.
  }

  // Modify existing link.
  public static async function genUpdate(
    string $link,
    int $level_id,
    int $link_id,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE links SET link = %s, level_id = %d WHERE id = %d LIMIT 1',
      $link,
      $level_id,
      $link_id,
    );
    self::invalidateMCRecords(); // Invalidate Memcached Links data.
  }

  // Delete existing link.
  public static async function genDelete(int $link_id): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf('DELETE FROM links WHERE id = %d LIMIT 1', $link_id);
    self::invalidateMCRecords(); // Invalidate Memcached Links data.
  }

  // Get all links for a given level.
  public static async function genAllLinks(
    int $level_id,
  ): Awaitable<array<Link>> {
    $db = await self::genDb();
    $result =
      await $db->queryf('SELECT * FROM links WHERE level_id = %d', $level_id);

    $links = array();
    foreach ($result->mapRows() as $row) {
      $links[] = self::linkFromRow($row);
    }

    return $links;
  }

  // Get a single link.
  public static async function gen(int $link_id): Awaitable<Link> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM links WHERE id = %d LIMIT 1',
      $link_id,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');

    return self::linkFromRow($result->mapRows()[0]);
  }

  // Check if a level has links.
  public static async function genHasLinks(
    int $level_id,
    bool $refresh = false,
  ): Awaitable<bool> {
    $mc_result = self::getMCRecords('LEVELS_COUNT');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $attachment_count = Map {};
      $result =
        await $db->queryf(
          'SELECT levels.id as level_id, COUNT(links.id) as count FROM levels LEFT JOIN links ON levels.id = links.level_id GROUP BY levels.id',
        );
      foreach ($result->mapRows() as $row) {
        $attachment_count->add(
          Pair {intval($row->get("level_id")), intval($row->get("count"))},
        );
      }
      self::setMCRecords('LEVELS_COUNT', $attachment_count);
    }
    $attachment_count = self::getMCRecords('LEVELS_COUNT');

    /* HH_IGNORE_ERROR[4062]: getMCRecords returns a 'mixed' type, HHVM is unsure of the type at this point */
    if ($attachment_count->contains($level_id)) {
      /* HH_IGNORE_ERROR[4062]: */
      return intval($attachment_count->get($level_id)) > 0;
    } else {
      return false;
    }
  }

  private static function linkFromRow(Map<string, string> $row): Link {
    return new Link(
      intval(must_have_idx($row, 'id')),
      intval(must_have_idx($row, 'level_id')),
      must_have_idx($row, 'link'),
    );
  }
}
