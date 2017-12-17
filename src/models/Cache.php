<?hh // strict

class Cache {

  private Map<string, mixed> $CACHE = Map {};

  public function __construct() {}

  public function setCache(string $key, mixed $value): void {
    $this->CACHE->add(Pair {strval($key), $value});
  }

  public function getCache(string $key): mixed {
    if ($this->CACHE->contains($key)) {
      return $this->CACHE->get($key);
    } else {
      return false;
    }
  }

  public function deleteCache(string $key): void {
    if ($this->CACHE->contains($key)) {
      $this->CACHE->remove($key);
    }
  }

  public function flushCache(): void {
    $this->CACHE = Map {};
  }

}
