<?hh // strict

abstract class DataController {

  abstract public function genGenerateData(): Awaitable<void>;

  public function sendData(): void {
    try {
      \HH\Asio\join($this->genGenerateData());
    } catch (RedirectException $e) {
      if (get_class($this) === "SessionController") {
        error_log(
          'RedirectException: ('.get_class($e).') '.$e->getTraceAsString(),
        );
        http_response_code($e->getStatusCode());
        Utils::redirect($e->getPath());
      } else {
        $this->jsonSend(array());
      }
    }
  }

  public function jsonSend(mixed $data): void {
    header('Content-Type: application/json');
    print json_encode($data);
  }

  public function downloadSend(string $name, mixed $data): void {
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header('Content-disposition: attachment; filename="'.$name.'"');
    print $data;
  }
}
