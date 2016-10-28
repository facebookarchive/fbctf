<?hh // strict

interface Exportable {
  public static function exportAll(
  ): Awaitable<array<string, array<string, mixed>>>;
}
