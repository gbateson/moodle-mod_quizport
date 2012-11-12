<?php // $Id$
/**
 * View a single a QuizPort unit
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

// set $QUIZPORT object
require_once('class.php');

$url = 'editcolumnlists.php?'.implode('&amp;', array("id=$course->id", "columnlisttype=$QUIZPORT->columnlisttype", "columnlistid=$QUIZPORT->columnlistid"));
if ($QUIZPORT->columnlisttype=='quiz') {
    add_to_log($course->id, 'quizport', 'editcolumnlists', $url."&amp;cm=$coursemodule->id", $quizport->id, $coursemodule->id);
} else {
    add_to_log($course->id, 'quizport', 'editcolumnlists', $url, $QUIZPORT->columnlistid);
}

// define "mod_quizport_editcolumnlists_form" class
require_once('editcolumnlists.form.php');

$mform = new mod_quizport_editcolumnlists_form();

if ($mform->is_cancelled()) {
    $QUIZPORT->action = 'editcancelled';
} else if ($QUIZPORT->action=='update' && ($newdata = $mform->get_data())) {
    $QUIZPORT->action = 'datasubmitted';
}

switch ($QUIZPORT->action) {

    case 'deleteconfirmed' :
        $text = '';

        $columnlists = $QUIZPORT->get_columnlists($QUIZPORT->columnlisttype);
        if (is_numeric($QUIZPORT->columnlistid) && $QUIZPORT->columnlistid>0) {
            if (array_key_exists($QUIZPORT->columnlistid, $columnlists)) {
                // delete a single columnlist
                unset_user_preference('quizport_'.$QUIZPORT->columnlisttype.'_columnlist_'.$QUIZPORT->columnlistid);
                $text = get_string('columnlist', 'quizport', $columnlists[$QUIZPORT->columnlistid]);
            }
        } else {
            // delete all user defined column lists
            foreach ($columnlists as $id => $name) {
                if (is_numeric($id)) {
                    unset_user_preference('quizport_'.$QUIZPORT->columnlisttype.'_columnlist_'.$id);
                    if ($text=='') {
                        $text = get_string('columnlists'.$QUIZPORT->columnlisttype, 'quizport');
                    }
                }
            }
        }
        $text = get_string('deletedactivity', '', moodle_strtolower($text));
        $QUIZPORT->print_page_quick($text, 'close');
        break;

    case 'delete' :
        $text = get_string('confirmdeletecolumnlistquiz', 'quizport');
        $QUIZPORT->print_page_delete($text, 'editcolumnlists.php', array('id'=>$QUIZPORT->courserecord->id));
        break;

    case 'deleteall' :
        $text = get_string('confirmdeleteallcolumnlistsquiz', 'quizport');
        $QUIZPORT->print_page_delete($text, 'editcolumnlists.php', array('id'=>$QUIZPORT->courserecord->id));
        break;

    case 'deletecancelled':
    case 'editcancelled':
        close_window();
        break;

    case 'datasubmitted':
        $columnlistnames = $QUIZPORT->get_columnlists($QUIZPORT->columnlisttype);

        if ($newdata->columnlistname) {
            // list name given, so check it is unique
            $id = array_search($newdata->columnlistname, $columnlistnames);
            if (is_numeric($id)) {
                $newdata->columnlistid = $id;
            }
        } else {
            // no list name given, so use old name if there was one
            if ($newdata->columnlistid && array_key_exists($newdata->columnlistid, $columnlistnames)) {
                $newdata->columnlistname = $columnlistnames[$newdata->columnlistid];
            }
        }

        if (empty($newdata->columnlistid)) {
            if (count($columnlistnames)) {
                // new columnlist id required
                $id = max(array_keys($columnlistnames)) + 1;
                $newdata->columnlistid = sprintf('%02d', $id);
            } else {
                // first column list is being added
                $newdata->columnlistid = '01';
            }
        }

        if (empty($newdata->columnlistname)) {
            $newdata->columnlistname = get_string('columnlist', 'quizport', $newdata->columnlistid);
        }

        $name = 'quizport_'.$newdata->columnlisttype.'_columnlist_'.$newdata->columnlistid;
        set_user_preference($name, $newdata->columnlistname.':'.implode(',', $mform->columnlist));

        $QUIZPORT->print_page_quick(get_string('resultssaved'), 'close');
        break;

    case 'update':
    default:
        $QUIZPORT->print_page();

} // end switch $QUIZPORT->action
?>