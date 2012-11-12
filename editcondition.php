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

if (isset($condition->id)) {
    add_to_log($course->id, 'quizport', 'editcondition', "editcondition.php?id=$condition->id", $quizport->id, $coursemodule->id);
} else {
    add_to_log($course->id, 'quizport', 'editcondition', "editcondition.php?cm=$coursemodule->id", $quizport->id, $coursemodule->id);
}

// define "mod_quizport_editcondition_form" class
require_once('editcondition.form.php');

$mform = new mod_quizport_editcondition_form();

if ($mform->is_cancelled()) {
    $QUIZPORT->action = 'editcancelled';
} else if ($newdata = $mform->get_data()) {
    $QUIZPORT->action = 'datasubmitted';
}

switch ($QUIZPORT->action) {

    case 'deleteconfirmed' :

            $text = '';
            $select = '';
            if ($QUIZPORT->conditionid) {
                // delete a single condition ($QUIZPORT->condition)
                $select = 'id='.$QUIZPORT->conditionid;
                switch ($QUIZPORT->condition->conditiontype) {
                    case QUIZPORT_CONDITIONTYPE_PRE:
                        $text = get_string('precondition', 'quizport');
                        break;
                    case QUIZPORT_CONDITIONTYPE_POST:
                        $text = get_string('postcondition', 'quizport');
                        break;
                }
            } else {
                // delete all (pre/post) conditions
                $select = 'quizid='.$QUIZPORT->quizid;
                switch ($QUIZPORT->conditiontype) {
                    case QUIZPORT_CONDITIONTYPE_PRE:
                        $text = get_string('allpreconditions', 'quizport');
                        $select .= ' AND conditiontype='.QUIZPORT_CONDITIONTYPE_PRE;
                        break;
                    case QUIZPORT_CONDITIONTYPE_POST:
                        $text = get_string('allpostconditions', 'quizport');
                        $select .= ' AND conditiontype='.QUIZPORT_CONDITIONTYPE_POST;
                        break;
                }
            }
            if ($DB->delete_records_select('quizport_conditions', $select)) {
                // success
                $text = get_string('deletedactivity', '', moodle_strtolower($text));
                $QUIZPORT->print_page_quick($text, 'close');
            } else {
                print_error('error_deleterecords', 'quizport', 'quizport_conditions');
            }
        break;

    case 'delete' :
        if ($QUIZPORT->condition->conditiontype==QUIZPORT_CONDITIONTYPE_PRE) {
            $type = 'precondition';
        } else {
            $type = 'postcondition';
        }
        $text = get_string('confirmdelete'.$type, 'quizport');
        $QUIZPORT->print_page_delete($text, 'editcondition.php', array('id'=>$QUIZPORT->conditionid));
        break;

    case 'deleteall' :
        if ($QUIZPORT->conditiontype==QUIZPORT_CONDITIONTYPE_PRE) {
            $type = 'precondition';
        } else {
            $type = 'postcondition';
        }
        $text = get_string('confirmdeleteall'.$type.'s', 'quizport');
        $QUIZPORT->print_page_delete($text, 'editcondition.php', array('id'=>$QUIZPORT->conditionid));
        break;

    case 'deletecancelled':
    case 'editcancelled':
        close_window();
        break;

    case 'datasubmitted':
        // $newdata object holds the submitted data

        // set timer fields value
        $fields = array(
            'attemptduration','attemptdelay'
        );
        foreach ($fields as $field) {
            $newdata->$field = get_timer_value($newdata, $field);
        }

        // adjust fields that can take a negative value
        $fields = array(
            'conditionscore','attemptcount','attemptduration','attemptdelay'
        );
        foreach ($fields as $field) {
            $disable = $field.'disable';
            if (isset($newdata->$field) && empty($newdata->$disable)) {
                $minmax = $field.'minmax';
                if (isset($newdata->$minmax)) {
                    $newdata->$field = abs($newdata->$field) * $newdata->$minmax;
                }
            } else {
                $newdata->$field = 0;
            }
        }

        // save form values as user preferences
        set_user_preferences(array(
            'quizport_condition_groupid' => $newdata->groupid,
            'quizport_condition_conditiontype' => $newdata->conditiontype,
            'quizport_condition_conditionscore' => $newdata->conditionscore,
            'quizport_condition_conditionquizid' => $newdata->conditionquizid,
            'quizport_condition_attempttype' => $newdata->attempttype,
            'quizport_condition_attemptcount' => $newdata->attemptcount,
            'quizport_condition_attemptduration' => $newdata->attemptduration,
            'quizport_condition_delay' => $newdata->attemptdelay,
            'quizport_condition_nextquizid' => $newdata->nextquizid
        ));

        if (empty($newdata->id)) {
            // adding a new QuizPort quiz condition
            if (! $newdata->id = $DB->insert_record('quizport_conditions', $newdata)) {
                print_error('error_insertrecord', 'quizport', '', 'quizport_conditions');
             }
        } else {
            // updating a QuizPort quiz condition
            if (! $DB->update_record('quizport_conditions', $newdata)) {
                print_error('error_updaterecord', 'quizport', '', 'quizport_conditions');
            }
        }

        $QUIZPORT->print_page_quick(get_string('resultssaved'), 'close');
        break;

    default: // show the form to add /edit a condition
        $QUIZPORT->print_page();
}
?>