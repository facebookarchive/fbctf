<?hh // strict

abstract class ModalController {
  public abstract function genRender(string $modal): Awaitable<:xhp>;
}
