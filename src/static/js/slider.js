window.jQuery = require('jquery');
var $ = window.jQuery;
require('bxslider');

module.exports = {
  init: function() {
    var selector = '.fb-slider';
    var itemWidth = $(selector).closest('#fb-modal').length > 0 ? 90 : 120;

    window.jQuery(selector).bxSlider({
      slideWidth: itemWidth,
      slideMargin: 20,
      pager: false,
      minSlides: 2,
      maxSlides: 5,
      moveSlides: 1,
      touchEnabled: false
    });
  }
};
