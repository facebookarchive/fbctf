<?hh // strict

abstract class ModuleController {

  abstract public function genRender(): Awaitable<:xhp>;

  public function sendRender(): void {
    try {
      echo \HH\Asio\join($this->genRender());
    } catch (RedirectException $e) {
      echo '';
    }
  }
}
