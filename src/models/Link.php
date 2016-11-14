<?hh // strict

class Link extends Model {

  protected static string $MC_KEY = 'links:';

  protected static Map<string, string>
    $MC_KEYS = Map {
      'LEVELS_COUNT' => 'link_levels_count',
      'LEVEL_LINKS' => 'link_levels',
      'LINKS' => 'link_by_id',
    };

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
    bool $refresh = false,
  ): Awaitable<array<Link>> {
    $mc_result = self::getMCRecords('LEVEL_LINKS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $links = array();
      $result = await $db->queryf('SELECT * FROM links');
      foreach ($result->mapRows() as $row) {
        $links[$row->get('level_id')][] = self::linkFromRow($row);
      }
      self::setMCRecords('LEVEL_LINKS', new Map($links));
      $links = new Map($links);
      if ($links->contains($level_id)) {
        $link_array = $links->get($level_id);
        invariant(
          is_array($link_array),
          '$link_array should be an array of Link',
        );
        return $link_array;
      } else {
        return array();
      }
    } else {
      invariant($mc_result instanceof Map, 'links should be of type Map');
      if ($mc_result->contains($level_id)) {
        $link_array = $mc_result->get($level_id);
        invariant(
          is_array($link_array),
          '$link_array should be an array of Link',
        );
        return $link_array;
      } else {
        return array();
      }
    }
  }

  // Get a single link.
  public static async function gen(
    int $link_id,
    bool $refresh = false,
  ): Awaitable<Link> {
    $mc_result = self::getMCRecords('LINKS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $links = Map {};
      $result = await $db->queryf('SELECT * FROM links');
      foreach ($result->mapRows() as $row) {
        $links->add(Pair {intval($row->get('id')), self::linkFromRow($row)});
      }
      self::setMCRecords('LINKS', $links);
      invariant($links->contains($link_id) !== false, 'link not found');
      $link = $links->get($link_id);
      invariant($link instanceof Link, 'link should be of type Link');
      return $link;
    } else {
      invariant($mc_result instanceof Map, 'links should be of type Map');
      invariant($mc_result->contains($link_id) !== false, 'link not found');
      $link = $mc_result->get($link_id);
      invariant($link instanceof Link, 'link should be of type Link');
      return $link;
    }
  }

  // Check if a level has links.
  public static async function genHasLinks(
    int $level_id,
    bool $refresh = false,
  ): Awaitable<bool> {
    $mc_result = self::getMCRecords('LEVELS_COUNT');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $link_count = Map {};
      $result =
        await $db->queryf(
          'SELECT levels.id as level_id, COUNT(links.id) as count FROM levels LEFT JOIN links ON levels.id = links.level_id GROUP BY levels.id',
        );
      foreach ($result->mapRows() as $row) {
        $link_count->add(
          Pair {intval($row->get('level_id')), intval($row->get('count'))},
        );
      }
      self::setMCRecords('LEVELS_COUNT', $link_count);
      if ($link_count->contains($level_id)) {
        $level_link_count = $link_count->get($level_id);
        return intval($level_link_count) > 0;
      } else {
        return false;
      }
    } else {
      invariant(
        $mc_result instanceof Map,
        'link_count should be of type Map',
      );
      if ($mc_result->contains($level_id)) {
        $level_link_count = $mc_result->get($level_id);
        return intval($level_link_count) > 0;
      } else {
        return false;
      }
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
