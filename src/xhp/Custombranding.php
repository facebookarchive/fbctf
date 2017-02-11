<?hh // strict

class :custombranding extends :x:element {
  category %flow;

  protected string $tagName = 'custombranding';

  protected function render(): XHPRoot {
    $custom_text = \HH\Asio\join(Configuration::gen('custom_text'));
    $custom_image = \HH\Asio\join(Configuration::gen('custom_logo_image'));
    return
      <span class="branding-el">
        <img class="icon-badge" src={strval($custom_image->getValue())}/>
        <br/>
        <span class="icon-text">{tr(strval($custom_text->getValue()))}</span>
      </span>;
  }
}
