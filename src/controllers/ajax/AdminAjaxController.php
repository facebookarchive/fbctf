<?hh // strict

class AdminAjaxController extends AjaxController {
  <<__Override>>
  protected function getFilters(): array<string, mixed> {
    return array(
      'POST' => array(
        'level_id' => FILTER_VALIDATE_INT,
        'level_type' => array(
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => array('regexp' => '/^[a-z]{4}$/'),
        ),
        'team_id' => FILTER_VALIDATE_INT,
        'session_id' => FILTER_VALIDATE_INT,
        'cookie' => FILTER_SANITIZE_STRING,
        'data' => FILTER_UNSAFE_RAW,
        'last_page_access' => FILTER_SANITIZE_STRING,
        'name' => FILTER_UNSAFE_RAW,
        'password' => FILTER_UNSAFE_RAW,
        'admin' => FILTER_VALIDATE_INT,
        'status' => FILTER_VALIDATE_INT,
        'visible' => FILTER_VALIDATE_INT,
        'all_type' => array(
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => array('regexp' => '/^[a-z]{4}$/'),
        ),
        'logo_id' => FILTER_VALIDATE_INT,
        'logo' => array(
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => array('regexp' => '/^[\w-.]+$/'),
        ),
        'logo_b64' => array(
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => array('regexp' => '/^[\w+-\/]+={0,2}$/'),
        ),
        'entity_id' => FILTER_VALIDATE_INT,
        'attachment_id' => FILTER_VALIDATE_INT,
        'filename' => array(
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => array('regexp' => '/^[\w\-\.]+$/'),
        ),
        'attachment_file' => FILTER_UNSAFE_RAW,
        'game_file' => FILTER_UNSAFE_RAW,
        'teams_file' => FILTER_UNSAFE_RAW,
        'levels_file' => FILTER_UNSAFE_RAW,
        'categories_file' => FILTER_UNSAFE_RAW,
        'logos_file' => FILTER_UNSAFE_RAW,
        'link_id' => FILTER_VALIDATE_INT,
        'link' => FILTER_UNSAFE_RAW,
        'category_id' => FILTER_VALIDATE_INT,
        'category' => FILTER_SANITIZE_STRING,
        'country_id' => FILTER_VALIDATE_INT,
        'title' => FILTER_UNSAFE_RAW,
        'description' => FILTER_UNSAFE_RAW,
        'question' => FILTER_UNSAFE_RAW,
        'flag' => FILTER_UNSAFE_RAW,
        'answer' => FILTER_UNSAFE_RAW,
        'hint' => FILTER_UNSAFE_RAW,
        'points' => FILTER_VALIDATE_INT,
        'bonus' => FILTER_VALIDATE_INT,
        'bonus_dec' => FILTER_VALIDATE_INT,
        'penalty' => FILTER_VALIDATE_INT,
        'active' => FILTER_VALIDATE_INT,
        'field' => FILTER_UNSAFE_RAW,
        'value' => FILTER_UNSAFE_RAW,
        'announcement' => FILTER_UNSAFE_RAW,
        'announcement_id' => FILTER_VALIDATE_INT,
        'csrf_token' => FILTER_UNSAFE_RAW,
        'action' => array(
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => array('regexp' => '/^[\w-]+$/'),
        ),
        'page' => array(
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => array('regexp' => '/^[\w-]+$/'),
        ),
      ),
      'GET' => array(
        'action' => array(
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => array('regexp' => '/^[\w-]+$/'),
        ),
        'csrf_token' => FILTER_UNSAFE_RAW,
      ),
    );
  }

  <<__Override>>
  protected function getActions(): array<string> {
    return array(
      'create_team',
      'create_quiz',
      'update_quiz',
      'create_flag',
      'update_flag',
      'create_base',
      'update_base',
      'update_team',
      'delete_team',
      'delete_level',
      'delete_all',
      'update_session',
      'delete_session',
      'toggle_status_level',
      'toggle_status_all',
      'toggle_status_team',
      'toggle_admin_team',
      'toggle_visible_team',
      'enable_country',
      'disable_country',
      'create_category',
      'update_category',
      'delete_category',
      'enable_logo',
      'disable_logo',
      'create_attachment',
      'update_attachment',
      'delete_attachment',
      'create_link',
      'update_link',
      'delete_link',
      'begin_game',
      'change_configuration',
      'change_custom_logo',
      'create_announcement',
      'delete_announcement',
      'create_tokens',
      'end_game',
      'pause_game',
      'unpause_game',
      'reset_game',
      'export_attachments',
      'backup_db',
      'export_game',
      'export_teams',
      'export_logos',
      'export_levels',
      'export_categories',
      'restore_db',
      'import_game',
      'import_teams',
      'import_logos',
      'import_levels',
      'import_categories',
      'import_attachments',
      'reset_game_schedule',
      'flush_memcached',
      'reset_database',
    );
  }

  <<__Override>>
  protected async function genHandleAction(
    string $action,
    array<string, mixed> $params,
  ): Awaitable<string> {
    if ($action !== 'none') {
      // CSRF check
      if (idx($params, 'csrf_token') !== SessionUtils::CSRFToken()) {
        return Utils::error_response('CSRF token is invalid', 'admin');
      }
    }

    list($default_bonus, $default_bonusdec) = await \HH\Asio\va(
      Configuration::gen('default_bonus'),
      Configuration::gen('default_bonusdec'),
    );

    switch ($action) {
      case 'none':
        return Utils::error_response('Invalid action', 'admin');
      case 'create_quiz':
        $bonus = $default_bonus->getValue();
        $bonus_dec = $default_bonusdec->getValue();
        await Level::genCreateQuiz(
          must_have_string($params, 'title'),
          must_have_string($params, 'question'),
          must_have_string($params, 'answer'),
          must_have_int($params, 'entity_id'),
          must_have_int($params, 'points'),
          intval($bonus),
          intval($bonus_dec),
          must_have_string($params, 'hint'),
          intval(must_have_idx($params, 'penalty')),
        );
        return Utils::ok_response('Created succesfully', 'admin');
      case 'update_quiz':
        await Level::genUpdateQuiz(
          must_have_string($params, 'title'),
          must_have_string($params, 'question'),
          must_have_string($params, 'answer'),
          must_have_int($params, 'entity_id'),
          must_have_int($params, 'points'),
          must_have_int($params, 'bonus'),
          must_have_int($params, 'bonus_dec'),
          must_have_string($params, 'hint'),
          intval(must_have_idx($params, 'penalty')),
          must_have_int($params, 'level_id'),
        );
        return Utils::ok_response('Updated succesfully', 'admin');
      case 'create_flag':
        $bonus = $default_bonus->getValue();
        $bonus_dec = $default_bonusdec->getValue();
        await Level::genCreateFlag(
          must_have_string($params, 'title'),
          must_have_string($params, 'description'),
          must_have_string($params, 'flag'),
          must_have_int($params, 'entity_id'),
          must_have_int($params, 'category_id'),
          must_have_int($params, 'points'),
          intval($bonus),
          intval($bonus_dec),
          must_have_string($params, 'hint'),
          intval(must_have_idx($params, 'penalty')),
        );
        return Utils::ok_response('Created succesfully', 'admin');
      case 'update_flag':
        await Level::genUpdateFlag(
          must_have_string($params, 'title'),
          must_have_string($params, 'description'),
          must_have_string($params, 'flag'),
          must_have_int($params, 'entity_id'),
          must_have_int($params, 'category_id'),
          must_have_int($params, 'points'),
          must_have_int($params, 'bonus'),
          must_have_int($params, 'bonus_dec'),
          must_have_string($params, 'hint'),
          intval(must_have_idx($params, 'penalty')),
          must_have_int($params, 'level_id'),
        );
        return Utils::ok_response('Updated succesfully', 'admin');
      case 'create_base':
        $bonus = $default_bonus->getValue();
        await Level::genCreateBase(
          must_have_string($params, 'title'),
          must_have_string($params, 'description'),
          must_have_int($params, 'entity_id'),
          must_have_int($params, 'category_id'),
          must_have_int($params, 'points'),
          must_have_int($params, 'bonus'),
          must_have_string($params, 'hint'),
          intval(must_have_idx($params, 'penalty')),
        );
        return Utils::ok_response('Created succesfully', 'admin');
      case 'update_base':
        await Level::genUpdateBase(
          must_have_string($params, 'title'),
          must_have_string($params, 'description'),
          must_have_int($params, 'entity_id'),
          must_have_int($params, 'category_id'),
          must_have_int($params, 'points'),
          must_have_int($params, 'bonus'),
          must_have_string($params, 'hint'),
          intval(must_have_idx($params, 'penalty')),
          must_have_int($params, 'level_id'),
        );
        return Utils::ok_response('Updated succesfully', 'admin');
      case 'delete_level':
        await Level::genDelete(must_have_int($params, 'level_id'));
        return Utils::ok_response('Deleted succesfully', 'admin');
      case 'toggle_status_level':
        await Level::genSetStatus(
          must_have_int($params, 'level_id'),
          must_have_int($params, 'status') === 1,
        );
        return Utils::ok_response('Success', 'admin');
      case 'toggle_status_all':
        if (must_have_string($params, 'all_type') === 'team') {
          await Team::genSetStatusAll(must_have_int($params, 'status') === 1);
          return Utils::ok_response('Success', 'admin');
        } else {
          await Level::genSetStatusAll(
            must_have_int($params, 'status') === 1,
            must_have_string($params, 'all_type'),
          );
          return Utils::ok_response('Success', 'admin');
        }
      case 'create_team':
        $password_hash =
          Team::generateHash(must_have_string($params, 'password'));
        await Team::genCreate(
          must_have_string($params, 'name'),
          $password_hash,
          must_have_string($params, 'logo'),
        );
        return Utils::ok_response('Created succesfully', 'admin');
      case 'update_team':
        await Team::genUpdate(
          must_have_string($params, 'name'),
          must_have_string($params, 'logo'),
          must_have_int($params, 'points'),
          must_have_int($params, 'team_id'),
        );
        if (strlen(must_have_string($params, 'password')) > 0) {
          $password_hash =
            Team::generateHash(must_have_string($params, 'password'));
          await Team::genUpdateTeamPassword(
            $password_hash,
            must_have_int($params, 'team_id'),
          );
        }
        return Utils::ok_response('Updated succesfully', 'admin');
      case 'toggle_admin_team':
        await Team::genSetAdmin(
          must_have_int($params, 'team_id'),
          must_have_int($params, 'admin') === 1,
        );
        return Utils::ok_response('Success', 'admin');
      case 'toggle_status_team':
        await Team::genSetStatus(
          must_have_int($params, 'team_id'),
          must_have_int($params, 'status') === 1,
        );
        return Utils::ok_response('Success', 'admin');
      case 'toggle_visible_team':
        await Team::genSetVisible(
          must_have_int($params, 'team_id'),
          must_have_int($params, 'visible') === 1,
        );
        return Utils::ok_response('Success', 'admin');
      case 'enable_logo':
        await Logo::genSetEnabled(must_have_int($params, 'logo_id'), true);
        return Utils::ok_response('Success', 'admin');
      case 'disable_logo':
        await Logo::genSetEnabled(must_have_int($params, 'logo_id'), false);
        return Utils::ok_response('Success', 'admin');
      case 'enable_country':
        await Country::genSetStatus(
          must_have_int($params, 'country_id'),
          true,
        );
        return Utils::ok_response('Success', 'admin');
      case 'disable_country':
        await Country::genSetStatus(
          must_have_int($params, 'country_id'),
          false,
        );
        return Utils::ok_response('Success', 'admin');
      case 'delete_team':
        // Delete team and associated sessions
        await \HH\Asio\va(
          Session::genDeleteByTeam(must_have_int($params, 'team_id')),
          Team::genDelete(must_have_int($params, 'team_id')),
        );
        return Utils::ok_response('Deleted successfully', 'admin');
      case 'update_session':
        await Session::genUpdate(
          must_have_string($params, 'cookie'),
          must_have_string($params, 'data'),
        );
        return Utils::ok_response('Updated successfully', 'admin');
      case 'delete_session':
        await Session::genDelete(must_have_string($params, 'cookie'));
        return Utils::ok_response('Deleted successfully', 'admin');
      case 'delete_category':
        await Category::genDelete(must_have_int($params, 'category_id'));
        return Utils::ok_response('Deleted successfully', 'admin');
      case 'create_category':
        await Category::genCreate(
          must_have_string($params, 'category'),
          false,
        );
        return Utils::ok_response('Created successfully', 'admin');
      case 'update_category':
        await Category::genUpdate(
          must_have_string($params, 'category'),
          must_have_int($params, 'category_id'),
        );
        return Utils::ok_response('Updated successfully', 'admin');
      case 'create_attachment':
        $result = await Attachment::genCreate(
          'attachment_file',
          must_have_string($params, 'filename'),
          must_have_int($params, 'level_id'),
        );
        if ($result) {
          return Utils::ok_response('Created successfully', 'admin');
        } else {
          return ''; // TODO
        }
      case 'update_attachment':
        await Attachment::genUpdate(
          must_have_int($params, 'attachment_id'),
          must_have_int($params, 'level_id'),
          must_have_string($params, 'filename'),
        );
        return Utils::ok_response('Updated successfully', 'admin');
      case 'delete_attachment':
        await Attachment::genDelete(must_have_int($params, 'attachment_id'));
        return Utils::ok_response('Deleted successfully', 'admin');
      case 'create_link':
        await Link::genCreate(
          must_have_string($params, 'link'),
          must_have_int($params, 'level_id'),
        );
        return Utils::ok_response('Created successfully', 'admin');
      case 'update_link':
        await Link::genUpdate(
          must_have_string($params, 'link'),
          must_have_int($params, 'level_id'),
          must_have_int($params, 'link_id'),
        );
        return Utils::ok_response('Updated succesfully', 'admin');
      case 'delete_link':
        await Link::genDelete(must_have_int($params, 'link_id'));
        return Utils::ok_response('Deleted successfully', 'admin');
      case 'change_configuration':
        $field = must_have_string($params, 'field');
        $valid_field = await Configuration::genValidField($field);
        if ($valid_field) {
          await Configuration::genUpdate(
            $field,
            must_have_string($params, 'value'),
          );
          return Utils::ok_response('Success', 'admin');
        } else {
          return Utils::error_response('Invalid configuration', 'admin');
        }
      case 'change_custom_logo':
        $logo = must_have_string($params, 'logo_b64');
        $custom_logo = await Logo::genCreateCustom($logo, true);
        if ($custom_logo) {
          return Utils::ok_response('Success', 'admin');
        } else {
          return Utils::error_response('Error changing logo', 'admin');
        }
      case 'create_announcement':
        await Announcement::genCreate(
          must_have_string($params, 'announcement'),
        );
        return Utils::ok_response('Success', 'admin');
      case 'delete_announcement':
        await Announcement::genDelete(
          must_have_int($params, 'announcement_id'),
        );
        return Utils::ok_response('Success', 'admin');
      case 'create_tokens':
        await Token::genCreate();
        return Utils::ok_response('Success', 'admin');
      case 'export_tokens':
        await Token::genExport();
        return Utils::ok_response('Success', 'admin');
      case 'begin_game':
        await Control::genBegin();
        return Utils::ok_response('Success', 'admin');
      case 'end_game':
        await Control::genEnd();
        return Utils::ok_response('Success', 'admin');
      case 'pause_game':
        await Control::genPause();
        return Utils::ok_response('Success', 'admin');
      case 'unpause_game':
        await Control::genUnpause();
        return Utils::ok_response('Success', 'admin');
      case 'export_attachments':
        await Control::exportAttachments();
        return Utils::ok_response('Success', 'admin');
      case 'backup_db':
        await Control::backupDb();
        return Utils::ok_response('Success', 'admin');
      case 'export_game':
        await Control::exportGame();
        return Utils::ok_response('Success', 'admin');
      case 'export_teams':
        await Control::exportTeams();
        return Utils::ok_response('Success', 'admin');
      case 'export_logos':
        await Control::exportLogos();
        return Utils::ok_response('Success', 'admin');
      case 'export_levels':
        await Control::exportLevels();
        return Utils::ok_response('Success', 'admin');
      case 'export_categories':
        await Control::exportCategories();
        return Utils::ok_response('Success', 'admin');
      case 'restore_db':
        $result = await Control::restoreDb();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'import_game':
        $result = await Control::importGame();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'import_teams':
        $result = await Control::importTeams();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'import_logos':
        $result = await Control::importLogos();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'import_levels':
        $result = await Control::importLevels();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'import_categories':
        $result = await Control::importCategories();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'import_attachments':
        $result = await Control::importAttachments();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'reset_game_schedule':
        await \HH\Asio\va(
          Configuration::genUpdate('start_ts', '0'), // Put timestamps to zero
          Configuration::genUpdate('end_ts', '0'),
          Configuration::genUpdate('next_game', '0'),
        );
        return Utils::ok_response('Success', 'admin');
      case 'flush_memcached':
        $result = await Control::genFlushMemcached();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error flushing memcached', 'admin');
      case 'reset_database':
        $result = await Control::genResetDatabase();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error resetting database', 'admin');
      default:
        return Utils::error_response('Invalid action', 'admin');
    }
  }
}
