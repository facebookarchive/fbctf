<?hh // strict

class JSONImporterController extends ImporterController {
  <<__Override>>
  public function importData(string $input_filename, string $type): array<string, array<string, mixed>> {
    $data_raw = json_decode(file_get_contents($input_filename), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return $data_raw;
    }
    switch ($type) {
      case 'game':
        $result = $data_raw;
        break;
      case 'teams':
        $result = must_have_idx($data_raw, 'teams');
        break;
      case 'logos':
        $result = must_have_idx($data_raw, 'logos');
        break;
      case 'levels':
        $result = must_have_idx($data_raw, 'levels');
        break;
      case 'categories':
        $result = must_have_idx($data_raw, 'categories');
        break;
    }
    return $result;
  }
}