<?hh // strict

interface Importable {
  public static function importAll(
    array<string, array<string, mixed>> $elements,
  ): Awaitable<bool>;
}
