<?php // $Id$
/**
 * Report a javascript or image error in a QuizPort page
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/quizport/legacy.php');
if ($CFG->majorrelease<=1.4) {
    require_once($CFG->legacylibdir.'/filelib.php');
} else {
    require_once($CFG->libdir.'/filelib.php');
}
require_login();

$url = optional_param('url', '', PARAM_URL);
$msg = optional_param('msg', '', PARAM_CLEAN);
$line = optional_param('line', -1, PARAM_INT);

if ($url) {
    if ($line>=0) {
        $url = "$url (line $line)";
    }
    $msg = "$url: $msg";
}
if ($msg) {
    // Note: $msg length is limited to 1024 characters
    trigger_error($msg, E_USER_WARNING); // E_USER_ERROR
}
// send image file back to browser
send_file($CFG->dirroot.'/mod/quizport/icon.gif', 'icon.gif');
?>