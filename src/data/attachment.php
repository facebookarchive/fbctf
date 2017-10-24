<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class AttachmentDataController extends DataController {
  public async function genGenerateData(): Awaitable<void> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    await tr_start();

    $data = tr('File Does Not Exist');
    $filename = tr('error');

    $attachment_id = idx(Utils::getGET(), 'id', '');
    if (intval($attachment_id) !== 0) {
      $attachment_exists =
        await Attachment::genCheckExists(intval($attachment_id));
      if ($attachment_exists === true) {
        $attachment = await Attachment::gen(intval($attachment_id));
        $filename = $attachment->getFilename();

        // Remove all non alpahnum characters from filename - allow international chars, dash, underscore, and period
        $filename = preg_replace('/[^\p{L}\p{N}_\-.]+/u', '_', $filename);

        $data = readfile(Attachment::attachmentsDir.$filename);
      }
    }

    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header('Content-disposition: attachment; filename="'.$filename.'"');
    print $data;
  }
}

/* HH_IGNORE_ERROR[1002] */
$attachment_file = new AttachmentDataController();
\HH\Asio\join($attachment_file->genGenerateData());
