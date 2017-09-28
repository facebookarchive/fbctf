<?hh // strict

class Cache {

  private Map<string, mixed> $GLOBAL_CACHE = Map {};

  public function __construct() {}

  public function setCache(string $key, mixed $value): void {
    $this->GLOBAL_CACHE->add(Pair {strval($key), $value});
  }

  public function getCache(string $key): mixed {
    if ($this->GLOBAL_CACHE->contains($key)) {
      return $this->GLOBAL_CACHE->get($key);
    } else {
      return false;
    }
  }

  public function deleteCache(string $key): void {
    if ($this->GLOBAL_CACHE->contains($key)) {
      $this->GLOBAL_CACHE->remove($key);
    }
  }
}
