<?hh // strict

class JSONImporterController {
  public static function readJSON(string $file_name): mixed {
    $files = Utils::getFILES();
    if ($files->contains($file_name)) {
      $input_filename = $files[$file_name]['tmp_name'];
      $data_raw = json_decode(file_get_contents($input_filename), true);
      if (json_last_error() !== JSON_ERROR_NONE ||
          !($data_raw instanceof KeyedContainer)) {
        error_log('Failed to decode json file '.$file_name);
        return false;
      }
      return $data_raw;
    }
    return false;
  }
}
