<?hh // strict

class :custombranding extends :x:element {
  category %flow;
  attribute string brandingText, string brandingLogo;

  protected string $tagName = 'custombranding';

  protected function render(): XHPRoot {
    return
      <span class="branding-el">
        <img class="icon-badge" src={$this->:brandingLogo} />
        <br />
        <span class="icon-text">{$this->:brandingText}</span>
      </span>;
  }
}
