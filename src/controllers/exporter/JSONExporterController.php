<?hh // strict

class JSONExporterController extends ExporterController {
  <<__Override>>
  public async function genData(string $type): Awaitable<mixed> {
    $data = array();
    
    switch ($type) {
      case 'game':
        $data['teams'] = await $this->genTeams();
        $data['logos'] = await $this->genLogos();
        $data['levels'] = await $this->genLevels();
        $data['categories'] = await $this->genCategories();
        break;
      case 'teams':
        $data[$type] = await $this->genTeams();
        break;
      case 'logos':
        $data[$type] = await $this->genLogos();
        break;
      case 'levels':
        $data[$type] = await $this->genLevels();
        break;
      case 'categories':
        $data[$type] = await $this->genCategories();
        break;
    }
    return $data;
  }

  public static function genJSON(mixed $data): string {	
    return json_encode($data, JSON_PRETTY_PRINT);   
  }

  public static function sendJSON(mixed $data, string $json_file='fbctf.json'): void {
    header('Content-Type: application/json;charset=utf-8');
    header('Content-Disposition: attachment; filename='.$json_file);
    echo self::genJSON($data);
  }
}
