<?hh // strict

class ChooseLogoModalController extends ModalController {
  <<__Override>>
  public async function genRender(string $_): Awaitable<:xhp> {
    return
      <div class="fb-modal-content">
        <header class="modal-title">
          <h4>{tr('choose_logo')}</h4>
          <a href="#" class="js-close-modal"><svg class="icon icon--close"><use href="#icon--close"/></svg></a>
        </header>
        <div class="choose-logo-modal">
          <div class="fb-choose-emblem">
            <h6>{tr('Choose an Emblem')}</h6>
            <div class="emblem-carousel"><emblem-carousel /></div>
          </div>
          <div class="action-actionable">
            <a href="#" class="fb-cta cta--yellow js-close-modal js-store-logo">{tr('Save')}</a>
          </div>
        </div>
      </div>;
  }
}
