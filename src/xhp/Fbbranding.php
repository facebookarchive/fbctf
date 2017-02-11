<?hh // strict

class :fbbranding extends :x:element {
  category %flow;

  protected string $tagName = 'fbbranding';

  protected function render(): XHPRoot {
    $custom_text = \HH\Asio\join(Configuration::gen('custom_text'));
    return
      <span class="branding-el">
        <svg class="icon icon--social-facebook">
          <use href="#icon--social-facebook" />
        </svg>
        <span class="has-icon">{' '}{tr(strval($custom_text->getValue()))}</span>
      </span>;
  }
}
