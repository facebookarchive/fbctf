<?hh // strict

class BinaryImporterController {
  public static function getFilename(string $file_name): mixed {
    $file = Utils::getFILES();
    if ($file->contains($file_name)) {
      $input_filename = $file[$file_name]['tmp_name'];
      return $input_filename;
    }
    return false;
  }
}
