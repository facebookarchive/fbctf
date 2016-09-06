<?hh // strict

interface Exportable {
  public static function export(): Awaitable<array<string, array<string, mixed>>>;
}