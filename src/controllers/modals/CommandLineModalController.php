<?hh // strict

class CommandLineModalController extends ModalController {
  <<__Override>>
  public async function genRender(string $_): Awaitable<:xhp> {
    return
      <div class="fb-modal-content fb-command-line fb-column-container">
        <div class="command-list">
          <div class="command-prompt">
            <input type="text" id="command-prompt--input" spellcheck="false" />
            <span class="autocomplete"></span>
          </div>
          <ul></ul>
        </div>
        <div class="command-results fb-row-container">
          <div class="results-filter row-fixed">
            <input type="text" id="command-prompt--filter-results" spellcheck="false" />
            <span class="autocomplete"></span>
          </div>
          <ul class="row-fluid"></ul>
        </div>
        <a href="#" class="js-close-modal">
          <svg class="icon icon--close">
            <use href="#icon--close"/>
          </svg>
        </a>
      </div>;
  }
}
