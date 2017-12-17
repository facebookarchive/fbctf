<?hh // strict

class CountryModalController extends ModalController {
  <<__Override>>
  public async function genRender(string $modal): Awaitable<:xhp> {
    switch ($modal) {
      case 'viewmode':
        return
          <div>
            <header class="modal-title">
              <h3 class="capturing-team-name"></h3>
              <span class="points-value fb-numbers"></span>
            </header>
            <ul class="country-capture-stats">
              <li>
                {tr('captured_')}
                <span class="highlighted country-name"></span>
              </li>
              <li>
                {tr('flag_owner_')}
                <span class="highlighted country-owner"></span>
              </li>
            </ul>
          </div>;
      case 'inactive':
        return
          <div>
            <div class="modal-title">
              <h4><span class="country-name"></span></h4>
            </div>
            <div class="country-capture-stats fb-column-container">
              <div class="inactive-country">
                {tr('INACTIVE')}
              </div>
            </div>
          </div>;
      case 'popup':
        return
          <div>
            <div class="modal-title">
              <h4>
                <span class="country-name"></span> -
                <span class="country-title"></span>
              </h4>
            </div>
            <div class="country-capture-stats fb-column-container">
              <div class="points-display">
                <span class="points-number fb-numbers"></span>
                <span class="points-label">{tr('PTS')}</span>
              </div>
              <div class="country-stats">
                <dl>
                  <dt>{tr('type')}</dt>
                  <dd class="country-type"></dd>
                  <dt>{tr('category')}</dt>
                  <dd class="country-category"></dd>
                </dl>
              </div>
            </div>
          </div>;
      case 'capture':
        // <!--
        //       * ALTERNATE HOVERS
        //       *
        //       * To enable a link that has different content on hover, add a
        //       *  "data-hover" addribute to the <a> tag and wrap the default
        //       *  text in a span. On hover, the browser will hide the default
        //       *  text and show the value of the data-hover attribute
        //       -->
        $scoring = await Configuration::gen('scoring');
        if ($scoring->getValue() === '1') {

          return
            <div class="fb-modal-content">
              <div class="modal-title">
                <h4>
                  {tr('capture_')}
                  <span class="country-name highlighted"></span> -
                  <span class="country-title"></span>
                </h4>
                <a href="#" class="js-close-modal">
                  <svg class="icon icon--close">
                    <use href="#icon--close" />
                  </svg>
                </a>
              </div>
              <form class="fb-form country-capture-form">
                <input name="level_id" type="hidden" value="" />
                <textarea rows={4} class="capture-text" disabled={true}>
                </textarea>
                <br />
                <div class="capture-links"></div>
                <br />
                <fieldset class="form-set">
                  <div class="answer_no_bases form-el el--text">
                    <input
                      placeholder={tr('Insert your answer')}
                      name="answer"
                      type="text"
                      autocomplete="off"
                    />
                  </div>
                  <div
                    class=
                      "answer_captured form-el el--text completely-hidden">
                    <input
                      placeholder={tr('Level already captured!')}
                      name="answer"
                      type="text"
                      autocomplete="off"
                      disabled={true}
                    />
                  </div>
                </fieldset>
                <div class="form-el--multiple-actions fb-column-container">
                  <div class="col col-1-2">
                    <a class="fb-cta cta--blue js-trigger-hint">
                      <span>{tr('Request Hint')}</span>
                    </a>
                  </div>
                  <div class="answer_no_bases col col-1-2 actions--right ">
                    <a class="fb-cta cta--yellow js-trigger-score">
                      {tr('Submit')}
                    </a>
                  </div>
                  <div
                    class=
                      "answer_captured col col-1-2 actions--right completely-hidden">
                    <a class="fb-cta cta--yellowe">
                      {tr('Captured!')}
                    </a>
                  </div>
                </div>
              </form>
              <div class="capture-hints-and-help">
                <div class="capture-hint">
                  <h4>{tr('hint_')}</h4>
                  <div></div>
                </div>
              </div>
              <footer class="modal-footer fb-column-container">
                <div
                  class=
                    "col col-1-2 country-capture-stats fb-column-container">
                  <div class="points-display">
                    <span class="points-number fb-numbers"></span>
                    <span class="points-label">{tr('PTS')}</span>
                  </div>
                  <div class="country-stats">
                    <dl>
                      <dt>{tr('type')}</dt>
                      <dd class="country-type"></dd>

                      <dt>{tr('category')}</dt>
                      <dd class="country-category"></dd>

                      <dt>{tr('first_capture')}</dt>
                      <dd class="opponent-name country-owner"></dd>
                    </dl>
                  </div>
                </div>
                <div
                  class=
                    "col col-1-2 country-capture-completed fb-column-container">
                  <span>{tr('completed_by')} &gt;</span>
                  <ul class="completed-list"></ul>
                </div>
              </footer>
            </div>;

        } else {
          return
            <div class="fb-modal-content">
              <div class="modal-title">
                <h4>
                  {tr('capture_')}
                  <span class="country-name highlighted"></span> -
                  <span class="country-title"></span>
                </h4>
                <a href="#" class="js-close-modal">
                  <svg class="icon icon--close">
                    <use href="#icon--close" />
                  </svg>
                </a>
              </div>
              <form class="fb-form country-capture-form">
                <input name="level_id" type="hidden" value="" />
                <textarea rows={4} class="capture-text" disabled={true}>
                </textarea>
                <br />
                <div class="capture-links"></div>
                <br />
                <fieldset class="form-set">
                  <div class="answer_no_bases form-el el--text">
                    <input
                      placeholder={tr('Scoring is Currently Disabled!')}
                      name="answer"
                      type="text"
                      autocomplete="off"
                      disabled={true}
                    />
                  </div>
                  <div
                    class=
                      "answer_captured form-el el--text completely-hidden">
                    <input
                      placeholder={tr('Level already captured!')}
                      name="answer"
                      type="text"
                      autocomplete="off"
                      disabled={true}
                    />
                  </div>
                </fieldset>
                <div class="form-el--multiple-actions fb-column-container">
                  <div class="col col-1-2"></div>
                  <div class="answer_no_bases col col-1-2 actions--right">
                    <a class="fb-cta cta--yellowe">
                      {tr('Scoring Off')}
                    </a>
                  </div>
                  <div
                    class=
                      "answer_captured col col-1-2 actions--right completely-hidden">
                    <a class="fb-cta cta--yellowe">
                      {tr('Captured!')}
                    </a>
                  </div>
                </div>
              </form>
              <div class="capture-hints-and-help">
                <div class="capture-hint">
                  <h4>{tr('hint_')}</h4>
                  <div></div>
                </div>
              </div>
              <footer class="modal-footer fb-column-container">
                <div
                  class=
                    "col col-1-2 country-capture-stats fb-column-container">
                  <div class="points-display">
                    <span class="points-number fb-numbers"></span>
                    <span class="points-label">{tr('PTS')}</span>
                  </div>
                  <div class="country-stats">
                    <dl>
                      <dt>{tr('type')}</dt>
                      <dd class="country-type"></dd>

                      <dt>{tr('category')}</dt>
                      <dd class="country-category"></dd>

                      <dt>{tr('first_capture')}</dt>
                      <dd class="opponent-name country-owner"></dd>
                    </dl>
                  </div>
                </div>
                <div
                  class=
                    "col col-1-2 country-capture-completed fb-column-container">
                  <span>{tr('completed_by')} &gt;</span>
                  <ul class="completed-list"></ul>
                </div>
              </footer>
            </div>;
        }
      default:
        invariant(false, 'Invalid modal name');
    }
  }
}
