<?hh // strict
require_once (__DIR__.'/../../vendor/autoload.php');

/* HH_IGNORE_ERROR[1002] */
$lang = null;

async function tr_start(): Awaitable<void> {
  $config = await Configuration::gen('language');
  global $language; // temporary fix
  $language = $config->getValue();
  $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
  if (preg_match('/^[^,;]+$/', $language) &&
      file_exists($document_root."/language/lang_".$language.".php")) {
    include ($document_root."/language/lang_".$language.".php");
  } else {
    include ($document_root."/language/lang_en.php");
    error_log(
      "\nWarning: Selected language ({$language}) has no translation file in the languages folder. English (languages/lang_en.php) is used instead.",
    );
  }
  /* HH_IGNORE_ERROR[2049] */
  /* HH_IGNORE_ERROR[4106] */
  global $lang;
  /* HH_IGNORE_ERROR[2050] */
  $lang = $translations;
}

function tr(string $word): string {
  /* HH_IGNORE_ERROR[2049] */
  /* HH_IGNORE_ERROR[4106] */
  global $lang;
  global $language;
  /* HH_IGNORE_ERROR[2050] */
  if ($language === 'en' && $word != 'date and time format') { // temporary fix for 
    return $word;           // reducing function calls to
  }                         // tr()
  elseif (array_key_exists($word, $lang)) {
    /* HH_IGNORE_ERROR[2050] */
    return $lang[$word];
  } else {
    error_log(
      "\nWarning: '{$word}' has no translation in the selected language. Using the English version instead.",
    );
    return $word;
  }
}