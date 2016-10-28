<?hh // strict

class JSONExporterController {
  public static function genJSON(mixed $data): string {
    return json_encode($data, JSON_PRETTY_PRINT);
  }

  public static function sendJSON(
    mixed $data,
    string $json_file = 'fbctf.json',
  ): void {
    header('Content-Type: application/json;charset=utf-8');
    header('Content-Disposition: attachment; filename='.$json_file);
    echo self::genJSON($data);
  }
}
