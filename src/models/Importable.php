<?hh // strict

interface Importable {
  public static function import(array<string, array<string, mixed>> $elements): Awaitable<bool>;
}