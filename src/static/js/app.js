var Utils = require('./utils');
var FB_CTF = require('./fb-ctf');
var Admin = require('./admin');

var $ = require('jquery');
require('./plugins');

// Add the transitionend event to a global var
(function(window) {
  var transitions = {
    'transition': 'transitionend',
    'WebkitTransition': 'webkitTransitionEnd',
    'MozTransition': 'transitionend',
    'OTransition': 'otransitionend'
  },
      elem = document.createElement('div');

  for (var t in transitions) {
    if (typeof elem.style[t] !== 'undefined') {
      window.transitionEnd = transitions[t];
      break;
    }
  }
})(window);

function enableNavActiveState() {
  var page = Utils.getURLParameter('page');

  $('.fb-main-nav a').removeClass('active').filter(function() {
    var href = $(this).data('active');

    if (href === undefined || !href.indexOf || page === '') {
      return false;
    }
    return href.indexOf(page) > -1;
  }).addClass('active');
}

function enableAdminActiveState() {
  var page = Utils.getURLParameter('page');

  $('#fb-admin-nav li').removeClass('active').filter(function() {
    var href = $('a', this).attr('href').replace('#', '');

    if (href === undefined || !href.indexOf || page === '') {
      return false;
    }
    return href.indexOf(page) > -1;
  }).addClass('active');
}

$(document).ready(function() {
  var page_location = window.location.pathname + window.location.search;
  if (window.innerWidth < 960 && page_location != '/index.php?page=mobile') {
  window.location = '/index.php?page=mobile';
  } else if (window.innerWidth < 960 && page_location == '/index.php?page=mobile') {
    setTimeout(function() {
      window.location = '/index.php';
    }, 2000);
  } else if (window.innerWidth >= 960 && page_location === '/index.php?page=mobile') {
    window.location = '/index.php';
  }

  FB_CTF.init();

  var section = $('body').data('section');
  if (section === 'pages') {
    enableNavActiveState();
  } else if (section === 'gameboard' || section === 'viewer-mode') {
    FB_CTF.gameboard.init();
  } else if (section === 'admin') {
    Admin.init();
    enableAdminActiveState();
  }

  $('body').trigger('content-loaded', {
    page: section
  });
});
