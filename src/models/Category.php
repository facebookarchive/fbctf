<?hh // strict

class Category extends Model implements Importable, Exportable {

  protected static string $MC_KEY = 'categories:';

  protected static Map<string, string>
    $MC_KEYS = Map {
      'ALL_CATEGORIES' => 'categories',
      'CATEGORIES' => 'categories_id',
    };

  private function __construct(
    private int $id,
    private string $category,
    private int $protected,
    private string $created_ts,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getCategory(): string {
    return $this->category;
  }

  public function getProtected(): bool {
    return $this->protected === 1;
  }

  public function getCreatedTs(): string {
    return $this->created_ts;
  }

  private static function categoryFromRow(Map<string, string> $row): Category {
    return new Category(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'category'),
      intval(must_have_idx($row, 'protected')),
      must_have_idx($row, 'created_ts'),
    );
  }

  // Import levels.
  public static async function importAll(
    array<string, array<string, mixed>> $elements,
  ): Awaitable<bool> {
    foreach ($elements as $category) {
      $c = must_have_string($category, 'category');
      $exist = await self::genCheckExists($c);
      if (!$exist) {
        await self::genCreate(
          $c,
          (bool) must_have_idx($category, 'protected'),
        );
      }
    }
    return true;
  }

  // Export levels.
  public static async function exportAll(
  ): Awaitable<array<string, array<string, mixed>>> {
    $all_categories_data = array();
    $all_categories = await self::genAllCategories();

    foreach ($all_categories as $category) {
      $one_category = array(
        'category' => $category->getCategory(),
        'protected' => $category->getProtected(),
      );
      array_push($all_categories_data, $one_category);
    }
    return array('categories' => $all_categories_data);
  }

  // All categories.
  public static async function genAllCategories(
    bool $refresh = false,
  ): Awaitable<array<Category>> {
    $mc_result = self::getMCRecords('ALL_CATEGORIES');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $categories = array();
      $result =
        await $db->queryf('SELECT * FROM categories ORDER BY category ASC');
      foreach ($result->mapRows() as $row) {
        $categories[] = self::categoryFromRow($row);
      }
      self::setMCRecords('ALL_CATEGORIES', $categories);
      return $categories;
      return $categories;
    } else {
      invariant(
        is_array($mc_result),
        'cache return should be an array of Category',
      );
      return $mc_result;
    }
  }

  // Check if category is used.
  public static async function genIsUsed(int $category_id): Awaitable<bool> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT COUNT(*) FROM levels WHERE category_id = %d',
      $category_id,
    );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return intval($result->mapRows()[0]['COUNT(*)']) > 0;
    } else {
      return false;
    }
  }

  // Delete category.
  public static async function genDelete(int $category_id): Awaitable<void> {
    $db = await self::genDb();

    await $db->queryf(
      'DELETE FROM categories WHERE id = %d AND id NOT IN (SELECT category_id FROM levels) AND protected = 0 LIMIT 1',
      $category_id,
    );
    self::invalidateMCRecords(); // Invalidate Memcached Category data.
  }

  // Create category.
  public static async function genCreate(
    string $category,
    bool $protected,
  ): Awaitable<int> {
    $db = await self::genDb();

    // Create category
    await $db->queryf(
      'INSERT INTO categories (category, protected, created_ts) VALUES (%s, %d, NOW())',
      $category,
      (int) $protected,
    );

    // Return newly created category_id
    $result = await $db->queryf(
      'SELECT id FROM categories WHERE category = %s LIMIT 1',
      $category,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    self::invalidateMCRecords(); // Invalidate Memcached Category data.
    return intval($result->mapRows()[0]['id']);
  }

  // Update category.
  public static async function genUpdate(
    string $category,
    int $category_id,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE categories SET category = %s WHERE id = %d LIMIT 1',
      $category,
      $category_id,
    );
    self::invalidateMCRecords(); // Invalidate Memcached Category data.
  }

  // Get category by id.
  /* HH_IGNORE_ERROR[4110]: Claims - It is incompatible with void because this async function implicitly returns Awaitable<void>, yet this returns Awaitable<Category> and the type is checked on line 188 */
  public static async function genSingleCategory(
    int $category_id,
    bool $refresh = false,
  ): Awaitable<Category> {
    $mc_result = self::getMCRecords('CATEGORIES');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $categories = Map {};
      $result = await $db->queryf('SELECT * FROM categories');
      foreach ($result->mapRows() as $row) {
        $categories->add(
          Pair {intval($row->get('id')), self::categoryFromRow($row)},
        );
      }
      self::setMCRecords('CATEGORIES', $categories);
      invariant(
        $categories->contains($category_id) !== false,
        'category not found',
      );
      $category = $categories->get($category_id);
      invariant(
        $category instanceof Category,
        'category should be type of Category',
      );
      return $category;
    } else {
      invariant(
        $mc_result instanceof Map,
        'categories should be type of Map',
      );
      invariant(
        $mc_result->contains($category_id) !== false,
        'category not found',
      );
      $category = $mc_result->get($category_id);
      invariant(
        $category instanceof Category,
        'category should be type of Category',
      );
      return $category;
    }
  }

  // Get category by name.
  public static async function genSingleCategoryByName(
    string $category,
  ): Awaitable<Category> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM categories WHERE category = %s LIMIT 1',
      $category,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    $category = self::categoryFromRow($result->mapRows()[0]);

    return $category;
  }

  // Check if a category is already created.
  public static async function genCheckExists(
    string $category,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT COUNT(*) FROM categories WHERE category = %s',
      $category,
    );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return (intval(idx($result->mapRows()[0], 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }
}
