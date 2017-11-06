<?hh // strict

class Announcement extends Model {

  protected static string $MC_KEY = 'announcement:';

  protected static Map<string, string>
    $MC_KEYS = Map {'ALL_ANNOUNCEMENTS' => 'all_announcements'};

  private function __construct(
    private int $id,
    private string $announcement,
    private string $ts,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getAnnouncement(): string {
    return mb_convert_encoding($this->announcement, 'UTF-8');
  }

  public function getTs(): string {
    return $this->ts;
  }

  private static function announcementFromRow(
    array<string, string> $row,
  ): Announcement {
    return new Announcement(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'announcement'),
      must_have_idx($row, 'ts'),
    );
  }

  public static async function genCreate(
    string $announcement,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'INSERT INTO announcements_log (ts, announcement) (SELECT NOW(), %s) LIMIT 1',
      $announcement,
    );

    self::invalidateMCRecords(); // Invalidate Memcached Announcement data.
  }

  public static async function genCreateAuto(
    string $announcement,
  ): Awaitable<void> {
    list($config_game, $config_pause) = await \HH\Asio\va(
      Configuration::gen('game'),
      Configuration::gen('game_paused'),
    );
    if ((intval($config_game->getValue()) === 1) &&
        (intval($config_pause->getValue()) === 0)) {
      $auto_announce = await Configuration::gen('auto_announce');
      if ($auto_announce->getValue() === '1') {
        $db = await self::genDb();
        await $db->queryf(
          'INSERT INTO announcements_log (ts, announcement) (SELECT NOW(), %s) LIMIT 1',
          $announcement,
        );
        self::invalidateMCRecords(); // Invalidate Memcached Announcement data.
      }
    }
  }

  public static async function genDelete(
    int $announcement_id,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'DELETE FROM announcements_log WHERE id = %d LIMIT 1',
      $announcement_id,
    );

    self::invalidateMCRecords(); // Invalidate Memcached Announcement data.
  }

  public static async function genDeleteAll(): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf('TRUNCATE TABLE announcements_log');

    self::invalidateMCRecords(); // Invalidate Memcached Announcement data.
  }

  // Get all tokens.
  public static async function genAllAnnouncements(
    bool $refresh = false,
  ): Awaitable<array<Announcement>> {
    $mc_result = self::getMCRecords('ALL_ANNOUNCEMENTS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $announcements = array();
      $db_result =
        await $db->queryf('SELECT * FROM announcements_log ORDER BY ts DESC');
      $rows = array_map($map ==> $map->toArray(), $db_result->mapRows());
      foreach ($rows as $row) {
        $announcements[] = self::announcementFromRow($row);
      }
      self::setMCRecords('ALL_ANNOUNCEMENTS', $announcements);
      return $announcements;
    }
    invariant(
      is_array($mc_result),
      'cached return should be an array of Announcement',
    );
    return $mc_result;
  }
}
