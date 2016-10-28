<?hh // strict

abstract class DataController {

  abstract public function genGenerateData(): Awaitable<void>;

  public function jsonSend(mixed $data): void {
    header('Content-Type: application/json');
    print json_encode($data);
  }
}
