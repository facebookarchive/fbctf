<?hh // strict

abstract class Controller {
  abstract protected function getTitle(): string;
  abstract protected function getFilters(): array<string, mixed>;
  abstract protected function getPages(): array<string>;

  abstract protected function genRenderBody(string $page): Awaitable<:xhp>;

  public async function genRenderBranding(): Awaitable<:xhp> {
    list($custom_branding, $custom_byline, $custom_logo_image) =
      await \HH\Asio\va(
        Configuration::gen('custom_logo'),
        Configuration::gen('custom_byline'),
        Configuration::gen('custom_logo_image'),
      );

    if ($custom_branding->getValue() === '0') {
      $branding_xhp =
        <fbbranding brandingText={tr(strval($custom_byline->getValue()))} />;
    } else {
      $branding_xhp =
        <custombranding
          brandingText={strval($custom_byline->getValue())}
          brandingLogo={strval($custom_logo_image->getValue())}
        />;
    }
    return $branding_xhp;
  }

  public async function genRender(): Awaitable<:xhp> {
    $page = $this->processRequest();
    list($body, $config) = await \HH\Asio\va(
      $this->genRenderBody($page),
      Configuration::gen('language'),
    );
    $language = $config->getValue();
    if (!preg_match('/^\w{2}$/', $language)) {
      $language = 'en';
    }
    // TODO: Potential LFI - Review how to do internationalization better
    $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
    $language_style = '';
    if (file_exists(
          $document_root.'/static/css/locals/'.$language.'/style.css',
        )) {
      $language_style = 'static/css/locals/'.$language.'/style.css';
    }
    return
      <x:doctype>
        <html lang={$language}>
          <head>
            <meta http-equiv="Cache-control" content="no-cache" />
            <meta http-equiv="Expires" content="-1" />
            <meta charset="UTF-8" />
            <meta
              name="viewport"
              content="width=device-width, initial-scale=1"
            />
            <title>{$this->getTitle()}</title>
            <link
              rel="icon"
              type="image/png"
              href="static/img/favicon.png"
            />
            <link rel="stylesheet" href="static/css/fb-ctf.css" />
            <link rel="stylesheet" href={$language_style} />
          </head>
          {$body}
        </html>
      </x:doctype>;
  }

  private function processRequest(): string {
    $input_methods = array('POST' => INPUT_POST, 'GET' => INPUT_GET);
    $method = must_have_string(Utils::getSERVER(), 'REQUEST_METHOD');

    $filter = idx($this->getFilters(), $method);
    if ($filter === null) {
      // Method not supported
      return 'none';
    }

    $input_method = must_have_idx($input_methods, $method);
    $page = 'main';

    $parameters = filter_input_array($input_method, $filter);

    $page = idx($parameters, 'page', 'main');
    if (!in_array($page, $this->getPages())) {
      $page = 'main';
    }

    return $page;
  }
}
