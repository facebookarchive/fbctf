<?hh // strict
require_once($_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php');

$lang = null;

async function tr_start(): Awaitable<string> {
  $config = await Configuration::gen('language');
  $language = $config->getValue();
  if(preg_match('/^\w{0,5}$/',$language) and file_exists($_SERVER['DOCUMENT_ROOT'] . "/language/lang_".$language.".php")) {
    include($_SERVER['DOCUMENT_ROOT'] . "/language/lang_".$language.".php");
  } else {
    include($_SERVER['DOCUMENT_ROOT'] . "/language/lang_en.php");
    error_log("\nWarning: Selected language ({$language}) has no translation file in the languages folder. English (languages/lang_en.php) is used instead.");
  }
  global $lang;
  $lang = $translations;
  return "";
}

function tr($word){
  global $lang;
  if(isset($lang[$word])) {
    return $lang[$word];
  } else {
    error_log("\nWarning: '{$word}' has no translation in the selected language. Using the English version instead.");
    return $word;
  }
}
