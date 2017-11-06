// @flow

var Slider = require('./slider');
var Modal = require('./modal');
var Dropkick = require('dropkickjs');
var $ = require('jquery');

// Kicks off a new game
function beginGame() {
  var begin_data = {
    action: 'begin_game'
  };
  sendAdminRequest(begin_data, true);
}

// Finishes the currently running game
function endGame() {
  var end_data = {
    action: 'end_game'
  };
  sendAdminRequest(end_data, true);
}

// Pauses the currently running game
function pauseGame() {
  var pause_data = {
    action: 'pause_game'
  };
  sendAdminRequest(pause_data, true);
}

// Unpauses the currently running game
function unpauseGame() {
  var unpause_data = {
    action: 'unpause_game'
  };
  sendAdminRequest(unpause_data, true);
}

//Confirm team deletion
function deleteTeamPopup(team_id) {
  var delete_team = {
    action: 'delete_team',
    team_id: team_id
  };
  sendAdminRequest(delete_team, true);
}

//Confirm level deletion
function deleteLevelPopup(level_id) {
  var delete_level = {
    action: 'delete_level',
    level_id: level_id
  };
  sendAdminRequest(delete_level, true);
}

// Reset the database
function resetDatabase() {
  var reset_database = {
    action: 'reset_database'
  };
  sendAdminRequest(reset_database, true);
}

/**
 * submits an ajax request to the admin endpoint
 *
 * @param  request_data (request object)
 *   - the parameters for the request.
 * @param  refresh_page (boolean)
 *   - check if page should be refreshed.
 *
 * @return Boolean
 *   - whether or not the request was succesful
 */
function sendAdminRequest(request_data: any, refresh_page) {
  var csrf_token = $('input[name=csrf_token]')[0].value;

  request_data.csrf_token = csrf_token;
  $.post(
    'index.php?p=admin&ajax=true',
    request_data
  ).fail(function() {
    // TODO: Make this a modal
    console.log('ERROR');
  }).done(function(data) {
    var responseData = JSON.parse(data);
    if (responseData.result == 'OK') {
      console.log('OK');
      if (refresh_page) {
        window.location.reload(true);
      }
      return true;
    } else {
      // TODO: Make this a modal
      console.log('Failed');
      return false;
    }
  });
}

var $body = $('body');


/**
 * Check the admin forms for errors
 *
 * @param $clicked (jquery object)
 *   - the clicked element. From this, we'll find the form
 *      elements we're looking to validate
 *
 * @return Boolean
 *   - whether or not the form is valud
 */
function validateAdminForm($clicked) {
  var valid = true,
      $validateForm = $clicked.closest('.validate-form'),
      $required = $('.form-el--required', $validateForm),
      errorClass = 'form-error';

  if ($validateForm.length === 0) {
    $validateForm = $clicked.closest('.fb-admin-main');
  }

  $('.error-msg', $validateForm).remove();

  $required.removeClass(errorClass).each(function() {
    var $self = $(this),
        $requiredEl = $('input[type="text"], input[type="password"], textarea', $self),
        $logoName = $('.logo-name', $self);

    // All the conditions that would make this element trigger an error
    if (
      $requiredEl.val() === '' ||
      $logoName.length > 0 && $logoName.text() === ''
    ) {
      $self.addClass(errorClass);
      valid = false;

      if ($('.error-msg', $validateForm).length === 0) {
        $('.admin-box-header h3', $validateForm).after('<span class="error-msg">Please fix the errors in red</span>');
      }

      return;
    }
  });

  return valid;
}

/**
 * Add a new section
 *
 * @param $clicked (jquery object)
 *   - the clicked button
 */
function addNewSection($clicked) {
  var $sectionContainer = $clicked.closest('.admin-buttons').siblings('.admin-sections'),
      $lastSection = $('.admin-box', $sectionContainer).last(),
      $firstSection = $('.admin-box', $sectionContainer).first(),
      $newSection = $firstSection.clone(),

      // +1 for the 0-based index, +1 for the new section being added
      sectionIndex = $lastSection.index();

  // Update some stuff in the cloned section
  var $title = $('.admin-box-header h3', $newSection),
      titleText = $title.text().toLowerCase(),
      switchName = $('input[type="radio"]', $newSection).first().attr('name');

  var newSwitchName;

  if (switchName) {
    newSwitchName = switchName.substr(0, switchName.lastIndexOf('--')) + '--' + sectionIndex;

    $('#' + switchName + '--on', $newSection).attr('id', newSwitchName + '--on');
    $('label[for="' + switchName + '--on"]', $newSection).attr('for', newSwitchName + '--on');
    $('#' + switchName + '--off', $newSection).attr('id', newSwitchName + '--off');
    $('label[for="' + switchName + '--off"]', $newSection).attr('for', newSwitchName + '--off');
    $('input[type="radio"]', $newSection).attr('name', newSwitchName);
  }

  $newSection.removeClass('section-locked');
  $newSection.removeClass('completely-hidden');

  $('.emblem-carousel li.active', $newSection).removeClass('active');
  $('.form-error', $newSection).removeClass('form-error');
  $('.post-avatar, .logo-name', $newSection).removeClass('has-avatar').empty();
  $('.error-msg', $newSection).remove();
  $('input[type="text"], input[type="password"]', $newSection).prop('disabled', false);

  $('.dk-select', $newSection).remove();
  $('select', $newSection).dropkick();
  var entity_select = $('[name=entity_id]', $newSection)[0];
  var category_select = $('[name=category_id]', $newSection)[0];
  if (entity_select !== undefined) {
    Dropkick(entity_select).disable(false);
  }
  if (category_select !== undefined) {
    Dropkick(category_select).disable(false);
  }

  if (titleText.indexOf('team') > -1) {
    $title.text('Team ' + sectionIndex);
  } else if (titleText.indexOf('quiz level') > -1) {
    $title.text('Quiz Level ' + sectionIndex);
  } else if (titleText.indexOf('base level') > -1) {
    $title.text('Base Level ' + sectionIndex);
  } else if (titleText.indexOf('flag level') > -1) {
    $title.text('Flag Level ' + sectionIndex);
  } else if (titleText.indexOf('player') > -1) {
    $title.text('Player ' + sectionIndex);
  }

  $('input[type="text"], input[type="password"]', $newSection).val('');

  $sectionContainer.append($newSection);

  Slider.init(5);
}

/**
 * Add a new attachment
 *
 * @param $clicked (jquery object)
 *   - the clicked button
 */
function addNewAttachment($clicked) {
  var $attachments = $('.attachments', $clicked),
      $newAttachment = $('.new-attachment-hidden', $clicked),
      $addedAttachment = $newAttachment.clone();

  $addedAttachment.removeClass('completely-hidden');
  $addedAttachment.removeClass('new-attachment-hidden');

  $('input[type=file]', $addedAttachment).change(function(e) {
    var fileName = e.target.files[0].name;
    $('input[name=filename]', $addedAttachment)[0].value = fileName;
  });

  $attachments.append($addedAttachment);
}

/**
 * Add a new link
 *
 * @param $clicked (jquery object)
 *   - the clicked button
 */
function addNewLink($clicked) {
  var $links = $('.links', $clicked),
      $newLink = $('.new-link-hidden', $clicked),
      $addedLink = $newLink.clone();

  $addedLink.removeClass('completely-hidden');
  $addedLink.removeClass('new-link-hidden');

  $links.append($addedLink);
}

// Create new attachment
function createAttachment(section) {
  var level_id = $('.attachment_form input[name=level_id]', section)[0].value;
  var filename = $('.attachment_form input[name=filename]', section)[0].value;
  var attachment_file = $('.attachment_form input[name=attachment_file]', section)[0].files[0];
  var csrf_token = $('input[name=csrf_token]')[0].value;

  if (!validateFilename(filename)) {
    Modal.loadPopup('p=action&modal=error', 'action-error', function() {
      $('.error-text').html('<p>Filename can only contain letters, numbers, underscores, hyphens, and periods</p>');
    });
    return;
  }
  if (level_id && filename && attachment_file) {
    var formData = new FormData();
    formData.append('attachment_file', attachment_file);
    formData.append('action', 'create_attachment');
    formData.append('level_id', level_id);
    formData.append('filename', filename);
    formData.append('csrf_token', csrf_token);

    $.ajax({
      url: 'index.php?p=admin&ajax=true',
      type: 'POST',
      data: formData,
      enctype: 'multipart/form-data',
      processData: false,
      contentType: false
    }).done(function(data) {
      var responseData = JSON.parse(data);
      if (responseData.result == 'OK') {
        console.log('OK');
        $('.attachment_form label', section).html('Created!');
        $('.attachment_form input[type=file]', section)[0].remove();
        $('.admin-buttons', section.closest('.new-attachment')).remove();
      } else {
        // TODO: Make this a modal
        console.log('Failed');
      }
    });
  }
}

function validateFilename(filename) {
  return filename.match(new RegExp('^[\\w\\-\\.]+$')) !== null;
}

// Create new link
function createLink(section) {
  var level_id = $('.link_form input[name=level_id]', section)[0].value;
  var link = $('.link_form input[name=link]', section)[0].value;
  var csrf_token = $('input[name=csrf_token]')[0].value;
  var create_data = {
    action: 'create_link',
    link: link,
    level_id: level_id,
    csrf_token: csrf_token
  };

  if ((level_id) && (link)) {
    $.post(
      'index.php?p=admin&ajax=true',
      create_data
    ).fail(function() {
      // TODO: Make this a modal
      console.log('ERROR');
    }).done(function(data) {
      //console.log(data);
      var responseData = JSON.parse(data);
      if (responseData.result == 'OK') {
        console.log('OK');
        $('.link_form label', section).html('Created!');
        $('.admin-buttons', section.closest('.new-link')).remove();
      } else {
        // TODO: Make this a modal
        console.log('Failed');
        return false;
      }
    });
  }
}

// Delete link
function deleteLink(section) {
  var link_id = $('.link_form input[name=link_id]', section)[0].value;
  var delete_data = {
    action: 'delete_link',
    link_id: link_id
  };

  if (link_id) {
    sendAdminRequest(delete_data, false);
  }
}

// Delete attachment
function deleteAttachment(section) {
  var attachment_id = $('.attachment_form input[name=attachment_id]', section)[0].value;
  var delete_data = {
    action: 'delete_attachment',
    attachment_id: attachment_id
  };

  if (attachment_id) {
    sendAdminRequest(delete_data, false);
  }
}

// Generic deletion
function deleteElement(section) {
  var elementSection = $('form', section)[0].classList[0];
  if (elementSection === 'session_form') {
    deleteSession(section);
  } else if (elementSection === 'team_form') {
    deleteTeam(section);
  } else if (elementSection === 'level_form') {
    deleteLevel(section);
  } else if (elementSection === 'categories_form') {
    deleteCategory(section);
  } else if (elementSection === 'announcements_form') {
    deleteAnnouncement(section);
  }
}

// Generic update
function updateElement(section) {
  var elementSection = $('form', section)[0].classList[0];
  if (elementSection === 'team_form') {
    updateTeam(section);
  } else if (elementSection === 'level_form') {
    updateLevel(section);
  }
}

// Generic create
function createElement(section) {
  var elementSection = $('form', section)[0].classList[0];
  if (elementSection === 'team_form') {
    createTeam(section);
  } else if (elementSection === 'level_form') {
    createLevel(section);
  } else if (elementSection === 'categories_form') {
    createCategory(section);
  }
}

// Create announcement
function createAnnouncement(section) {
  var announcement = $('input[name=new_announcement]', section)[0].value;
  var create_data = {
    action: 'create_announcement',
    announcement: announcement
  };
  if (announcement) {
    sendAdminRequest(create_data, true);
  }
}

//Create and download attachments backup
function attachmentsExport() {
  var csrf_token = $('input[name=csrf_token]')[0].value;
  var action = 'export_attachments';
  var url = 'index.php?p=admin&ajax=true&action=' + action + '&csrf_token=' + csrf_token;
  window.location.href = url;
}

// Create and download database backup
function databaseBackup() {
  var csrf_token = $('input[name=csrf_token]')[0].value;
  var action = 'backup_db';
  var url = 'index.php?p=admin&ajax=true&action=' + action + '&csrf_token=' + csrf_token;
  window.location.href = url;
}

// Export and download current game
function exportCurrentGame() {
  var csrf_token = $('input[name=csrf_token]')[0].value;
  var action = 'export_game';
  var url = 'index.php?p=admin&ajax=true&action=' + action + '&csrf_token=' + csrf_token;
  window.location.href = url;
}

// Generic function to submit the import file.
function submitImport(type_file, action_file) {
  var import_file = $('input[name=' + type_file + ']')[0].files[0];
  var csrf_token = $('input[name=csrf_token]')[0].value;
  var formData = new FormData();
  formData.append(type_file, import_file);
  formData.append('action', action_file);
  formData.append('csrf_token', csrf_token);

  $.ajax({
    url: 'index.php?p=admin&ajax=true',
   type: 'POST',
   data: formData,
   enctype: 'multipart/form-data',
   processData: false,
   contentType: false
  }).done(function(data) {
    var responseData = JSON.parse(data);
    if (responseData.result == 'OK') {
      console.log('OK');
       Modal.loadPopup('p=action&modal=import-done', 'action-import', function() {
         var ok_button = $("a[class='fb-cta cta--yellow js-close-modal']");
         ok_button.attr('href', '?p=admin&page=controls');
         ok_button.removeClass('js-close-modal');
       });
    } else {
      console.log('Failed');
      Modal.loadPopup('p=action&modal=error', 'action-error', function() {
        $('.error-text').html('<p>Sorry there was a problem importing the items. Please try again.</p>');
        var ok_button = $("a[class='fb-cta cta--yellow js-close-modal']");
        ok_button.attr('href', '?p=admin&page=controls');
        ok_button.removeClass('js-close-modal');
      });
    }
  });
}

//Restore and replace database
function databaseRestore() {
  $('#restore-database_file').trigger('click');
  $('#restore-database_file').change(function() {
    submitImport('database_file', 'restore_db');
  });
}

// Import and replace whole game
function importGame() {
  $('#import-game_file').trigger('click');
  $('#import-game_file').change(function() {
    submitImport('game_file', 'import_game');
  });
}

// Import and replace current teams
function importTeams() {
  $('#import-teams_file').trigger('click');
  $('#import-teams_file').change(function() {
    submitImport('teams_file', 'import_teams');
  });
}

// Import and replace current categories
function importCategories() {
  $('#import-categories_file').trigger('click');
  $('#import-categories_file').change(function() {
    submitImport('categories_file', 'import_categories');
  });
}

// Import and replace current logos
function importLogos() {
  $('#import-logos_file').trigger('click');
  $('#import-logos_file').change(function() {
    submitImport('logos_file', 'import_logos');
  });
}

// Import and replace current levels
function importLevels() {
  $('#import-levels_file').trigger('click');
  $('#import-levels_file').change(function() {
    submitImport('levels_file', 'import_levels');
  });
}

//Import and replace current attachments
function importAttachments() {
  $('#import-attachments_file').trigger('click');
  $('#import-attachments_file').change(function() {
    submitImport('attachments_file', 'import_attachments');
  });
}

// Export and download current teams
function exportCurrentTeams() {
  var csrf_token = $('input[name=csrf_token]')[0].value;
  var action = 'export_teams';
  var url = 'index.php?p=admin&ajax=true&action=' + action + '&csrf_token=' + csrf_token;
  window.location.href = url;
}

// Export and download current logos
function exportCurrentLogos() {
  var csrf_token = $('input[name=csrf_token]')[0].value;
  var action = 'export_logos';
  var url = 'index.php?p=admin&ajax=true&action=' + action + '&csrf_token=' + csrf_token;
  window.location.href = url;
}

// Export and download current levels
function exportCurrentLevels() {
  var csrf_token = $('input[name=csrf_token]')[0].value;
  var action = 'export_levels';
  var url = 'index.php?p=admin&ajax=true&action=' + action + '&csrf_token=' + csrf_token;
  window.location.href = url;
}

// Export and download current categories
function exportCurrentCategories() {
  var csrf_token = $('input[name=csrf_token]')[0].value;
  var action = 'export_categories';
  var url = 'index.php?p=admin&ajax=true&action=' + action + '&csrf_token=' + csrf_token;
  window.location.href = url;
}

// Flush Memcached
function flushMemcached() {
  var flush_memcached = {
    action: 'flush_memcached'
  };
  sendAdminRequest(flush_memcached, true);
}

//Reset Game Schedule
function resetGameSchedule() {
  var reset_game_schedule = {
    action: 'reset_game_schedule'
  };
  sendAdminRequest(reset_game_schedule, true);
}

// Create tokens
function createTokens() {
  var create_data = {
    action: 'create_tokens'
  };
  sendAdminRequest(create_data, true);
}

// Create tokens
function exportTokens() {
  var csrf_token = $('input[name=csrf_token]')[0].value;
  var action = 'export_tokens';
  var url = 'index.php?p=admin&ajax=true&action=' + action + '&csrf_token=' + csrf_token;
  window.location.href = url;
}

// Delete announcement
function deleteAnnouncement(section) {
  var announcement_id = $('.announcements_form input[name=announcement_id]', section)[0].value;
  var delete_data = {
    action: 'delete_announcement',
    announcement_id: announcement_id
  };
  if (announcement_id) {
    sendAdminRequest(delete_data, false);
  }
}

// Delete level
function deleteLevel(section) {
  var level_id = $('.level_form input[name=level_id]', section)[0].value;
  var delete_data = {
    action: 'delete_level',
    level_id: level_id
  };
  if (level_id) {
    sendAdminRequest(delete_data, false);
  }
}

// Create category
function createCategory(section) {
  var category = $('.categories_form input[name=category]', section)[0].value;
  var create_data = {
    action: 'create_category',
    category: category
  };
  if (category) {
    sendAdminRequest(create_data, true);
  }
}

// Delete category
function deleteCategory(section) {
  var category_id = $('.categories_form input[name=category_id]', section)[0].value;
  var delete_data = {
    action: 'delete_category',
    category_id: category_id
  };
  if (category_id) {
    sendAdminRequest(delete_data, false);
  }
}

// Update category
function updateCategory(section) {
  var category_id = $('.categories_form input[name=category_id]', section)[0].value;
  var category = $('.categories_form input[name=category]', section)[0].value;
  var update_data = {
    action: 'update_category',
    category_id: category_id,
    category: category
  };
  if (category_id) {
    sendAdminRequest(update_data, false);
  }
}

// Create generic level
function createLevel(section) {
  var level_type = $('.level_form input[name=level_type]', section)[0].value;
  switch (level_type) {
  case 'quiz':
    createQuizLevel(section);
    break;
  case 'flag':
    createFlagLevel(section);
    break;
  case 'base':
    createBaseLevel(section);
    break;
  }
}

// Create quiz level
function createQuizLevel(section) {
  var title = $('.level_form input[name=title]', section)[0].value;
  var question = $('.level_form textarea[name=question]', section)[0].value;
  var answer = $('.level_form input[name=answer]', section)[0].value;
  var entity_id = $('.level_form select[name=entity_id] option:selected', section)[0].value;
  var points = $('.level_form input[name=points]', section)[0].value;
  var hint = $('.level_form input[name=hint]', section)[0].value;
  var penalty = $('.level_form input[name=penalty]', section)[0].value;

  var create_data = {
    action: 'create_quiz',
    title: title,
    question: question,
    answer: answer,
    entity_id: entity_id,
    points: points,
    hint: hint,
    penalty: penalty
  };
  sendAdminRequest(create_data, true);
}

// Create flag level
function createFlagLevel(section) {
  var title = $('.level_form input[name=title]', section)[0].value;
  var description = $('.level_form textarea[name=description]', section)[0].value;
  var flag = $('.level_form input[name=flag]', section)[0].value;
  var entity_id = $('.level_form select[name=entity_id] option:selected', section)[0].value;
  var category_id = $('.level_form select[name=category_id] option:selected', section)[0].value;
  var points = $('.level_form input[name=points]', section)[0].value;
  var hint = $('.level_form input[name=hint]', section)[0].value;
  var penalty = $('.level_form input[name=penalty]', section)[0].value;

  var create_data = {
    action: 'create_flag',
    title: title,
    description: description,
    flag: flag,
    entity_id: entity_id,
    category_id: category_id,
    points: points,
    hint: hint,
    penalty: penalty
  };
  sendAdminRequest(create_data, true);
}

// Create base level
function createBaseLevel(section) {
  var title = $('.level_form input[name=title]', section)[0].value;
  var description = $('.level_form textarea[name=description]', section)[0].value;
  var entity_id = $('.level_form select[name=entity_id] option:selected', section)[0].value;
  var category_id = $('.level_form select[name=category_id] option:selected', section)[0].value;
  var points = $('.level_form input[name=points]', section)[0].value;
  var bonus = $('.level_form input[name=bonus]', section)[0].value;
  var hint = $('.level_form input[name=hint]', section)[0].value;
  var penalty = $('.level_form input[name=penalty]', section)[0].value;

  var create_data = {
    action: 'create_base',
    title: title,
    description: description,
    entity_id: entity_id,
    category_id: category_id,
    points: points,
    bonus: bonus,
    hint: hint,
    penalty: penalty
  };
  sendAdminRequest(create_data, true);
}

// Update generic level
function updateLevel(section) {
  var level_type = $('.level_form input[name=level_type]', section)[0].value;
  switch (level_type) {
  case 'quiz':
    updateQuizLevel(section);
    break;
  case 'flag':
    updateFlagLevel(section);
    break;
  case 'base':
    updateBaseLevel(section);
    break;
  }
}

// Update quiz level
function updateQuizLevel(section) {
  var title = $('.level_form input[name=title]', section)[0].value;
  var question = $('.level_form textarea[name=question]', section)[0].value;
  var answer = $('.level_form input[name=answer]', section)[0].value;
  var entity_id = $('.level_form select[name=entity_id] option:selected', section)[0].value;
  var points = $('.level_form input[name=points]', section)[0].value;
  var bonus = $('.level_form input[name=bonus]', section)[0].value;
  var bonus_dec = $('.level_form input[name=bonus_dec]', section)[0].value;
  var hint = $('.level_form input[name=hint]', section)[0].value;
  var penalty = $('.level_form input[name=penalty]', section)[0].value;
  var level_id = $('.level_form input[name=level_id]', section)[0].value;

  var update_data = {
    action: 'update_quiz',
    title: title,
    question: question,
    answer: answer,
    entity_id: entity_id,
    points: points,
    bonus: bonus,
    bonus_dec: bonus_dec,
    hint: hint,
    penalty: penalty,
    level_id: level_id
  };
  sendAdminRequest(update_data, false);
}

// Update flag level
function updateFlagLevel(section) {
  var title = $('.level_form input[name=title]', section)[0].value;
  var description = $('.level_form textarea[name=description]', section)[0].value;
  var flag = $('.level_form input[name=flag]', section)[0].value;
  var entity_id = $('.level_form select[name=entity_id] option:selected', section)[0].value;
  var category_id = $('.level_form select[name=category_id] option:selected', section)[0].value;
  var points = $('.level_form input[name=points]', section)[0].value;
  var bonus = $('.level_form input[name=bonus]', section)[0].value;
  var bonus_dec = $('.level_form input[name=bonus_dec]', section)[0].value;
  var hint = $('.level_form input[name=hint]', section)[0].value;
  var penalty = $('.level_form input[name=penalty]', section)[0].value;
  var level_id = $('.level_form input[name=level_id]', section)[0].value;

  var update_data = {
    action: 'update_flag',
    title: title,
    description: description,
    flag: flag,
    entity_id: entity_id,
    category_id: category_id,
    points: points,
    bonus: bonus,
    bonus_dec: bonus_dec,
    hint: hint,
    penalty: penalty,
    level_id: level_id
  };
  sendAdminRequest(update_data, false);
}

// Update base level
function updateBaseLevel(section) {
  var title = $('.level_form input[name=title]', section)[0].value;
  var description = $('.level_form textarea[name=description]', section)[0].value;
  var entity_id = $('.level_form select[name=entity_id] option:selected', section)[0].value;
  var category_id = $('.level_form select[name=category_id] option:selected', section)[0].value;
  var points = $('.level_form input[name=points]', section)[0].value;
  var bonus = $('.level_form input[name=bonus]', section)[0].value;
  var hint = $('.level_form input[name=hint]', section)[0].value;
  var penalty = $('.level_form input[name=penalty]', section)[0].value;
  var level_id = $('.level_form input[name=level_id]', section)[0].value;

  var update_data = {
    action: 'update_base',
    title: title,
    description: description,
    entity_id: entity_id,
    category_id: category_id,
    points: points,
    bonus: bonus,
    hint: hint,
    penalty: penalty,
    level_id: level_id
  };
  sendAdminRequest(update_data, false);
}

// Delete team
function deleteTeam(section) {
  var team_id = $('.team_form input[name=team_id]', section)[0].value;
  var delete_data = {
    action: 'delete_team',
    team_id: team_id
  };
  if (team_id) {
    sendAdminRequest(delete_data, false);
  }
}

// Create team
function createTeam(section) {
  var team_name = $('.team_form input[name=team_name]', section)[0].value;
  var team_password = $('.team_form input[name=password]', section)[0].value;
  var team_logo = $('.logo-name', section)[0].textContent;
  var create_data = {
    action: 'create_team',
    name: team_name,
    password: team_password,
    logo: team_logo
  };
  if (team_name && team_password && team_logo) {
    sendAdminRequest(create_data, true);
  }
}

// Update team
function updateTeam(section) {
  var team_id = $('.team_form input[name=team_id]', section)[0].value;
  var team_name = $('.team_form input[name=team_name]', section)[0].value;
  var team_points = $('.team_form input[name=points]', section)[0].value;
  var team_password = $('.team_form input[name=password]', section)[0].value;
  var team_logo = $('.logo-name', section)[0].textContent;
  var update_data = {
    action: 'update_team',
    team_id: team_id,
    name: team_name,
    points: team_points,
    password: team_password,
    logo: team_logo
  };
  sendAdminRequest(update_data, false);
}

// Toggle team option
function toggleTeam(radio_id) {
  var team_id = radio_id.split('--')[2].split('-')[1];
  var radio_action = radio_id.split('--')[2].split('-')[2];
  var action_value = (radio_id.split('--')[3] === 'on') ? 1 : 0;
  var toggle_data = {
    action: 'toggle_' + radio_action + '_team',
    team_id: team_id
  };
  toggle_data[radio_action] = action_value;
  if (team_id && radio_action) {
    sendAdminRequest(toggle_data, false);
  }
}

// Toggle All
function toggleAll(radio_id) {
  var action_type = radio_id.split('--')[2].split('_')[1];
  var action_value = (radio_id.split('--')[3] === 'on') ? 1 : 0;
  var toggle_data = {
    action: 'toggle_status_all',
    all_type: action_type,
    status: action_value
  };
  if (action_type) {
    sendAdminRequest(toggle_data, false);
    if (action_value) {
      $('input[type=radio][id*=status--on]').prop('checked', true);
      $('input[type=radio][id*=status--off]').prop('checked', false);
    } else {
      $('input[type=radio][id*=status--on]').prop('checked', false);
      $('input[type=radio][id*=status--off]').prop('checked', true);
    }
  }
}

// Toggle level option
function toggleLevel(radio_id) {
  var level_id = radio_id.split('--')[2].split('-')[1];
  var radio_action = radio_id.split('--')[2].split('-')[2];
  var action_value = (radio_id.split('--')[3] === 'on') ? 1 : 0;
  var toggle_data = {
    action: 'toggle_' + radio_action + '_level',
    level_id: level_id
  };
  toggle_data[radio_action] = action_value;
  if (level_id && radio_action) {
    sendAdminRequest(toggle_data, false);
  }
}

function toggleConfiguration(radio_id) {
  var radio_action = radio_id.split('--')[2];
  var action_value = (radio_id.split('--')[3] === 'on') ? 1 : 0;
  var toggle_data = {
    action: 'change_configuration',
    field: radio_action,
    value: action_value
  };
  var refresh_fields = ['login_strongpasswords', 'custom_logo'];
  if (refresh_fields.indexOf(radio_action) !== -1) {
    sendAdminRequest(toggle_data, true);
  } else {
    sendAdminRequest(toggle_data, false);
  }
}

function changeConfiguration(field, value) {
  var conf_data = {
    action: 'change_configuration',
    field: field,
    value: value
  };
  var refresh_fields = ['registration_type', 'language'];
  if (refresh_fields.indexOf(field) !== -1) {
    sendAdminRequest(conf_data, true);
  } else {
    sendAdminRequest(conf_data, false);
  }
}

function toggleLogo(section) {
  // Toggle logo status
  var logo_id = $('.logo_form input[name=logo_id]', section)[0].value;
  var action_value = $('.logo_form input[name=status_action]', section)[0].value;
  var toggle_data = {
    action: action_value + '_logo',
    logo_id: logo_id
  };
  if (logo_id && action_value) {
    sendAdminRequest(toggle_data, true);
  }
}

function toggleCountry(section) {
  // Toggle country status
  var country_id = $('.country_form input[name=country_id]', section)[0].value;
  var action_value = $('.country_form input[name=status_action]', section)[0].value;
  var toggle_data = {
    action: action_value,
    country_id: country_id
  };
  if (country_id && action_value) {
    sendAdminRequest(toggle_data, true);
  }
}

// Delete session
function deleteSession(section) {
  var session_cookie = $('.session_form input[name=cookie]', section)[0].value;
  var delete_data = {
    action: 'delete_session',
    cookie: session_cookie
  };

  if (session_cookie) {
    sendAdminRequest(delete_data, true);
  }
}

function saveLevel($section: any, lockClass) {
  updateElement($section);
  $section.addClass(lockClass);
  $('input[type="text"], input[type="password"], textarea', $section).prop('disabled', true);
  var entity_select = $('[name=entity_id]', $section)[0];
  var category_select = $('[name=category_id]', $section)[0];
  if (entity_select !== undefined) {
    Dropkick(entity_select).close();
    Dropkick(entity_select).disable();
  }
  if (category_select !== undefined) {
    Dropkick(category_select).close();
    Dropkick(category_select).disable();
  }
}

module.exports = {
  init: function() {
    // Capture enter key presses to avoid unexpected actions
    $(document).on('keypress', 'input', function(e) {
      if (e.keyCode == 13) {
        e.preventDefault();
      }
    });

    // Actionable buttons
    $('.fb-admin-main').off('click').on('click', '[data-action]', function(event) {
      event.preventDefault();
      var $self = $(this),
          $section = $self.closest('.admin-box'),
          action = $self.data('action'),
          actionModal = $self.data('actionModal'),
          lockClass = 'section-locked',
          sectionTitle = $self.closest('#fb-main-content').find('.admin-page-header h3').text().replace(' ', '_');

      var $containingDiv,
          valid;

      // Route the actions
      if (action === 'save') {
        valid = validateAdminForm($self);
        if (valid) {
          saveLevel($section, lockClass);
        }
      } else if (action === 'save-no-validation') {
        saveLevel();
      } else if (action === 'add-new') {
        addNewSection($self);
      } else if (action === 'save-category') {
        updateCategory($section);
      } else if (action === 'create') {
        valid = validateAdminForm($self);
        if (valid) {
          createElement($section);
        }
      } else if (action === 'create-announcement') {
        createAnnouncement($section);
      } else if (action === 'export-attachments') {
        attachmentsExport();
      } else if (action === 'backup-db') {
        databaseBackup();
      } else if (action === 'import-game') {
        importGame();
      } else if (action === 'create-tokens') {
        createTokens($section);
      } else if (action === 'export-tokens') {
        exportTokens($section);
      } else if (action === 'export-game') {
        exportCurrentGame();
      } else if (action === 'import-teams') {
        importTeams();
      } else if (action === 'export-teams') {
        exportCurrentTeams();
      } else if (action === 'import-logos') {
        importLogos();
      } else if (action === 'export-logos') {
        exportCurrentLogos();
      } else if (action === 'import-levels') {
        importLevels();
      } else if (action === 'import-attachments') {
        importAttachments();
      } else if (action === 'export-levels') {
        exportCurrentLevels();
      } else if (action === 'import-categories') {
        importCategories();
      } else if (action === 'export-categories') {
        exportCurrentCategories();
      } else if (action === 'flush-memcached') {
        flushMemcached();
      } else if (action === 'reset-game-schedule') {
        resetGameSchedule();
      } else if (action === 'create-tokens') {
        createTokens();
      } else if (action === 'export-tokens') {
        exportTokens();
      } else if (action === 'edit') {
        $section.removeClass(lockClass);
        $('input[type="text"], input[type="password"], textarea', $section).prop('disabled', false);
        var entity_select = $('[name=entity_id]', $section)[0];
        var category_select = $('[name=category_id]', $section)[0];
        if (entity_select !== undefined) {
          Dropkick(entity_select).disable(false);
        }
        if (category_select !== undefined) {
          Dropkick(category_select).disable(false);
        }
      } else if (action === 'delete') {
        $section.remove();
        deleteElement($section);
        // rename the section boxes
        /*$('.admin-box').each(function(i, el){
         var $titleObj  = $('.admin-box-header h3', el),
         title     = $titleObj.text(),
         newTitle  = title.substring( 0, title.lastIndexOf(" ") + 1 ) + (i + 1);

         $titleObj.text(newTitle);
         });*/
      } else if (action === 'disable-logo') {
        toggleLogo($section);
      } else if (action === 'enable-logo') {
        toggleLogo($section);
      } else if (action === 'disable-country') {
        toggleCountry($section);
      } else if (action === 'enable-country') {
        toggleCountry($section);
      } else if (action === 'add-attachment') {
        addNewAttachment($section);
      } else if (action === 'create-attachment') {
        $containingDiv = $self.closest('.new-attachment');
        createAttachment($containingDiv);
      } else if (action === 'delete-new-attachment') {
        $containingDiv = $self.closest('.new-attachment');
        $containingDiv.remove();
        deleteAttachment($containingDiv);
      } else if (action === 'delete-attachment') {
        $containingDiv = $self.closest('.existing-attachment');
        $containingDiv.remove();
        deleteAttachment($containingDiv);
      } else if (action === 'add-link') {
        addNewLink($section);
      } else if (action === 'create-link') {
        $containingDiv = $self.closest('.new-link');
        createLink($containingDiv);
      } else if (action === 'delete-new-link') {
        $containingDiv = $self.closest('.new-link');
        $containingDiv.remove();
        deleteLink($containingDiv);
      } else if (action === 'delete-link') {
        $containingDiv = $self.closest('.existing-link');
        $containingDiv.remove();
        deleteLink($containingDiv);
      }

      if (actionModal) {
        Modal.loadPopup('p=action&model=' + actionModal, 'action-' + actionModal, function() {
          $('#fb-modal .admin-section-name').text(sectionTitle);
        });
      }
    });

    // Radio buttons
    $('input[type="radio"]').on('change', function() {
      var $this = $(this);
      var radio_name = $this.attr('id');

      if (radio_name.search('fb--teams') === 0) {
        if (radio_name.search('all') > 0) {
          toggleAll(radio_name);
        } else {
          toggleTeam(radio_name);
        }
      } else if (radio_name.search('fb--levels') === 0) {
        if (radio_name.search('all') > 0) {
          toggleAll(radio_name);
        } else {
          toggleLevel(radio_name);
        }
      } else if (radio_name.search('fb--conf') === 0) {
        toggleConfiguration(radio_name);
      }
    });

    // configuration fields
    $('select,input[type="number"][name^="fb--conf"],input[type="text"][name^="fb--conf"]').on('change', function() {
      var $this = $(this);
      var field = $this.attr('name').split('--')[2];
      var value = '';
      if ($this.attr('type') === 'number' || $this.attr('type') === 'text') {
        value = $(this)[0].value;
      } else {
        value = $('option:selected', $this)[0].value;
      }
      if (!$(this).hasClass('not_configuration')) {
        changeConfiguration(field, value);
      }
    });

    // game schedule fields
    $('input[type="number"][name^="fb--schedule"]').on('change', function() {
      var start_year = $('input[type="number"][name="fb--schedule--start_year"]')[0].value;
      var start_month = $('input[type="number"][name="fb--schedule--start_month"]')[0].value;
      var start_day = $('input[type="number"][name="fb--schedule--start_day"]')[0].value;
      var start_hour = $('input[type="number"][name="fb--schedule--start_hour"]')[0].value;
      var start_min = $('input[type="number"][name="fb--schedule--start_min"]')[0].value;
      var start_ts = Date.UTC(start_year, start_month - 1, start_day, start_hour, start_min) / 1000;
      if ($.isNumeric(start_ts)) {
        changeConfiguration("start_ts", start_ts);
        changeConfiguration("next_game", start_ts);
      }
      var end_year = $('input[type="number"][name="fb--schedule--end_year"]')[0].value;
      var end_month = $('input[type="number"][name="fb--schedule--end_month"]')[0].value;
      var end_day = $('input[type="number"][name="fb--schedule--end_day"]')[0].value;
      var end_hour = $('input[type="number"][name="fb--schedule--end_hour"]')[0].value;
      var end_min = $('input[type="number"][name="fb--schedule--end_min"]')[0].value;
      var end_ts = Date.UTC(end_year, end_month - 1, end_day, end_hour, end_min) / 1000;
      if ($.isNumeric(end_ts)) {
        changeConfiguration("end_ts", end_ts);
      }
    });

    // modal actionable
    $body.on('click', '.js-confirm-save', function() {
      var $status = $('.admin-section--status .highlighted');
      $status.text('Saved');

      setTimeout(function() {
        $status.fadeOut(function() {
          $status.text('').removeAttr('style');
        });
      }, 5000);
    });

    function levelsFilterChange() {
      var status_filter = $('select[name="status_filter"] option:selected')[0].value;
      var $category_filter = $('select[name="category_filter"] option:selected');
      var id = '';
      var selector;

      // Quizzes don't have a category filter
      if ($category_filter.length > 0) {
        var category_filter = $category_filter[0].value;

        // Hide all
        $('section[id!=new-element]').each(function() {
          $(this).addClass('completely-hidden');
        });

        if (status_filter === 'all') {
          id = 'status--';
        } else if (status_filter === 'Enabled') {
          id = 'status--on';
        } else if (status_filter === 'Disabled') {
          id = 'status--off';
        }

        selector = 'input[type="radio"][id*="' + id + '"]:checked[class!=filter_option]';

        var category_selector = 'select[name=category_id]';
        if (category_filter !== 'all') {
          category_selector = 'select[name=category_id] option:selected:contains(' + category_filter + ')';
        }

        $(selector).closest('section[id!=new-element]').each(function() {
          if ($(this).find(category_selector).length > 0) {
            $(this).removeClass('completely-hidden');
          }
        });
      } else {
        // Handle quizzes

        // Hide all
        $('section[id!=new-element]').each(function() {
          $(this).addClass('completely-hidden');
        });

        if (status_filter === 'all') {
          id = 'status--';
        } else if (status_filter === 'Enabled') {
          id = 'status--on';
        } else if (status_filter === 'Disabled') {
          id = 'status--off';
        }

        selector = 'input[type="radio"][id*="' + id + '"]:checked[class!=filter_option]';
        $(selector).closest('section[id!=new-element]').removeClass('completely-hidden');
      }
    } // levelsFilterChange

    // category filter select (flags, bases)
    $('select[name="category_filter"]').on('change', levelsFilterChange);

    // status filter select (quiz, flags, bases)
    $('select[name="status_filter"]').on('change', levelsFilterChange);

    // use filter select (countries)
    $('select[name="use_filter"]').on('change', function() {
      var $this = $(this);
      var filter = $('option:selected', $this)[0].value;
      if (filter === 'all') {
        $('section[id!=new-element]').each(function() {
          $(this).removeClass('completely-hidden');
        });
      } else {
        $('section[id!=new-element]').each(function() {
          $(this).addClass('completely-hidden');
        });
        var targets = $('.country-use');
        targets.each(function() {
          if ($(this).text() === filter) {
            var target = $(this).closest('section[id!=new-element]')[0];
            $(target).removeClass('completely-hidden');
          }
        });
      }
    });

    // status filter select (countries)
    $('select[name="country_status_filter"]').on('change', function() {
      var $this = $(this);
      var filter = $('option:selected', $this)[0].value;
      if (filter === 'all') {
        $('section[id!=new-element]').each(function() {
          $(this).removeClass('completely-hidden');
        });
      } else {
        $('section[id!=new-element]').each(function() {
          $(this).addClass('completely-hidden');
        });
        var targets = $('.country-' + filter);
        targets.each(function() {
          var target = $(this).closest('section[id!=new-element]')[0];
          $(target).removeClass('completely-hidden');
        });
      }
    });

    // select a logo
    $body.on('click', '.js-choose-logo', function(event) {
      event.preventDefault();

      var $self = $(this),
          $container = $self.closest('.fb-column-container');

      Modal.loadPopup('p=choose-logo&modal=choose-logo', 'choose-logo', function() {
        var $modal = $('#fb-modal');

        Slider.init(5);

        $('.js-store-logo', $modal).on('click', function(event) {
          event.preventDefault();
          var $active = $('.slides li.active', $modal),
              logo = $active.html(),
              logoName = $('use', $active).attr('xlink:href').replace('#icon--badge-', '');

          $('.post-avatar', $container).addClass('has-avatar').html(logo);
          $('.logo-name', $container).text(logoName);
        });
      });
    });

    // prompt begin game
    $('.js-begin-game').on('click', function(event) {
      event.preventDefault();
      Modal.loadPopup('p=action&modal=begin-game', 'action-begin-game', function() {
        $('#begin_game').click(beginGame);
      });
    });

    // prompt end game
    $('.js-end-game').on('click', function(event) {
      event.preventDefault();
      Modal.loadPopup('p=action&modal=end-game', 'action-end-game', function() {
        $('#end_game').click(endGame);
      });
    });

    // prompt pause game
    $('.js-pause-game').on('click', function(event) {
      event.preventDefault();
      Modal.loadPopup('p=action&modal=pause-game', 'action-pause-game', function() {
        $('#pause_game').click(pauseGame);
      });
    });

    // prompt pause game
    $('.js-unpause-game').on('click', function(event) {
      event.preventDefault();
      Modal.loadPopup('p=action&modal=unpause-game', 'action-unpause-game', function() {
        $('#unpause_game').click(unpauseGame);
      });
    });

    // prompt delete team
    $('.js-delete-team').on('click', function(event) {
      event.preventDefault();
      var team_id = $(this).prev('input').attr('value');
      Modal.loadPopup('p=action&modal=delete-team', 'action-delete-team', function() {
        $('#delete_team').click(function() {
          deleteTeamPopup(team_id);
        });
      });
    });

    // prompt delete level
    $('.js-delete-level').on('click', function(event) {
      event.preventDefault();
      var level_id = $(this).prev('input').attr('value');
      Modal.loadPopup('p=action&modal=delete-level', 'action-delete-level', function() {
        $('#delete_level').click(function() {
          deleteLevelPopup(level_id);
        });
      });
    });
    
    // prompt logout
    $('.js-prompt-logout').on('click', function(event) {
      event.preventDefault();
      Modal.loadPopup('p=action&modal=logout', 'action-logout');
    });

    // show/hide answer
    $('.toggle_answer_visibility').on('click', function(event) {
      event.preventDefault();
      if ($(this).prev('input').attr('type') === 'text') {
        $(this).text('Show Answer');
        $(this).prev('input').attr('type', 'password');
      } else if ($(this).prev('input').attr('type') === 'password') {
        $(this).text('Hide Answer');
        $(this).prev('input').attr('type', 'text');
      }
    });

    // prompt reset database
    $('.js-reset-database').on('click', function(event) {
      event.preventDefault();
      Modal.loadPopup('p=action&modal=reset-database', 'action-reset-database', function() {
        $('#reset_database').click(resetDatabase);
      });
    });

    // prompt restore database
    $('.js-restore-database').on('click', function(event) {
      event.preventDefault();
      Modal.loadPopup('p=action&modal=restore-database', 'action-restore-database', function() {
        $('#restore_database').click(databaseRestore);
      });
    });

    // custom logo file selector
    var $customLogoInput = $('#custom-logo-input');
    var $customLogoImage = $('#custom-logo-image');
    $('#custom-logo-link').on('click', function() {
      $customLogoInput.trigger('click');
    });
    // on file input change, set image
    $customLogoInput.change(function() {
      var input = this;
      if (input.files && input.files[0]) {
        if (input.files[0].size > (1000*1024)) {
          alert('Please upload an image less than 1000KB!');
          return;
        }

        var reader = new FileReader();

        reader.onload = function (e) {
          $customLogoImage.attr('src', e.target.result);
          var rawImageData = e.target.result;
          var filetypeBeginIdx = rawImageData.indexOf('/') + 1;
          var filetypeEndIdx = rawImageData.indexOf(';');
          var filetype = rawImageData.substring(filetypeBeginIdx, filetypeEndIdx);
          var base64 = rawImageData.substring(rawImageData.indexOf(',') + 1);
          var logo_data = {
            action: 'change_custom_logo',
            logoType: filetype,
            logo_b64: base64
          };
          sendAdminRequest(logo_data, true);
        };

        reader.readAsDataURL(input.files[0]);

      }
    });
  }
};
