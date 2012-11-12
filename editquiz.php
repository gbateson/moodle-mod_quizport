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

if (isset($quiz->id)) {
    add_to_log($course->id, 'quizport', 'editquiz', "editquiz.php?id=$quiz->id", $quizport->id, $coursemodule->id);
} else {
    add_to_log($course->id, 'quizport', 'editquiz', "editquiz.php?qp=$quizport->id", $quizport->id, $coursemodule->id);
}

// define "mod_quizport_editquiz_form" class
require_once('editquiz.form.php');

$mform = new mod_quizport_editquiz_form();

if ($mform->is_cancelled()) {
    $QUIZPORT->action = 'editcancelled';
} else if ($newdata = $mform->get_data()) {
    $QUIZPORT->action = 'datasubmitted';
}

switch ($QUIZPORT->action) {
    case 'deleteconfirmed' :

        quizport_delete_quizzes($QUIZPORT->quiz->id);
        $text = get_string('deletedactivity', '', moodle_strtolower(get_string('quiz', 'quizport')));
        $link = $QUIZPORT->format_url('editquizzes.php', 'coursemoduleid', array('coursemoduleid'=>$QUIZPORT->courserecord->id, 'quizid'=>0));
        $QUIZPORT->print_page_quick($text, 'continue', $link);
        break;

    case 'delete' :

        // check the user really wants to delete this quiz
        $text = get_string('confirmdeletequiz', 'quizport');
        $QUIZPORT->print_page_delete($text, 'editquiz.php', array('id'=>$QUIZPORT->quiz->id));
        break;

    case 'deletecancelled':
    case 'editcancelled':

        $id = 'coursemoduleid';
        if ($QUIZPORT->unumber>0 && $QUIZPORT->qnumber>0) {
            // resume the unit/quiz attempt
            $params = array($id => $QUIZPORT->modulerecord->id);
            redirect($QUIZPORT->format_url('view.php', $id, $params));
        } else {
            // resume editing quizzes in unit
            $params = array($id => $QUIZPORT->modulerecord->id, 'quizid' => 0);
            redirect($QUIZPORT->format_url('editquizzes.php', $id, $params));
        }
        break;

    case 'datasubmitted':

        // get title options
        $newdata->title = 0;
        if (! empty($newdata->title_textsource)) {
            $newdata->title = $newdata->title_textsource & QUIZPORT_TITLE_SOURCE;
        }
        if (! empty($newdata->title_prependunitname)) {
            $newdata->title += QUIZPORT_TITLE_UNITNAME;
        }
        if (! empty($newdata->title_appendsortorder)) {
            $newdata->title += QUIZPORT_TITLE_SORTORDER;
        }
        unset($newdata->title_textsource, $newdata->title_prependunitname, $newdata->title_appendsortorder);

        // get stopbutton and stoptext
        if (empty($newdata->stopbutton_yesno)) {
            $newdata->stopbutton = QUIZPORT_STOPBUTTON_NONE;
        } else {
            if (empty($newdata->stopbutton_type)) {
                $newdata->stopbutton_type = '';
            }
            if (empty($newdata->stopbutton_text)) {
                $newdata->stopbutton_text = '';
            }
            if ($newdata->stopbutton_type=='specific') {
                $newdata->stopbutton = QUIZPORT_STOPBUTTON_SPECIFIC;
                $newdata->stoptext = $newdata->stopbutton_text;
            } else {
                $newdata->stopbutton = QUIZPORT_STOPBUTTON_LANGPACK;
                $newdata->stoptext = $newdata->stopbutton_type;
            }
        }
        unset($newdata->stopbutton_yesno, $newdata->stopbutton_type, $newdata->stopbutton_text);

        // get timer values
        $times = array('timelimit', 'delay1', 'delay2', 'delay3');
        foreach ($times as $time) {
            if (empty($newdata->$time)) {
                $newdata->$time = get_timer_value($newdata, $time);
            }
        }

        // set review options
        quizport_set_reviewoptions($newdata);

        if ($CFG->majorrelease<=1.7) {
            quizport_update_showadvanced_last($newdata, 'editquiz');
        }
        quizport_set_preferences('quiz', $newdata);

        if (empty($newdata->id)) {
            // add new QuizPort quiz(zes)
            $afterquizid = optional_param('afterquizid', 0, PARAM_INT);
            quizport_add_quizzes($newdata, $QUIZPORT->unit, $afterquizid);
        } else {
            if ($QUIZPORT->quiz->sourcefile==$newdata->sourcefile && $QUIZPORT->quiz->sourcelocation==$newdata->sourcelocation) {
                // do nothing - same source file
            } else {
                // get type of new source file
                $source = new quizport_file($newdata->sourcefile, $newdata->sourcelocation, true);
                if ($quizfile = reset($source->quizfiles)) {
                    $newdata->sourcetype = $quizfile->get_type();
                    if ($quizfile->location==QUIZPORT_LOCATION_WWW) {
                        $newdata->sourcefile = $quizfile->url;
                    } else {
                        $newdata->sourcefile = $quizfile->filepath;
                    }
                    $newdata->sourcelocation = $quizfile->location;
                    unset($quizfile);
                }
                unset($source);
            }
            if ($QUIZPORT->quiz->scoremethod==$newdata->scoremethod && $QUIZPORT->quiz->scorelimit==$newdata->scorelimit && $QUIZPORT->quiz->scoreweighting==$newdata->scoreweighting) {
                $regrade = false;
            } else {
                $regrade = true;
                $QUIZPORT->quiz->scoremethod = $newdata->scoremethod;
                $QUIZPORT->quiz->scorelimit = $newdata->scorelimit;
                $QUIZPORT->quiz->scoreweighting = $newdata->scoreweighting;
            }

            // set cache fields
            $QUIZPORT->quiz->title = $newdata->title;
            if (isset($newdata->stopbutton)) {
                $QUIZPORT->quiz->stopbutton = $newdata->stopbutton;
            }
            if (isset($newdata->stoptext)) {
                $QUIZPORT->quiz->stoptext = $newdata->stoptext;
            }

            // update the QuizPort quiz record
            if (! $DB->update_record('quizport_quizzes', $newdata)) {
                print_error('error_updaterecord', 'quizport', '', 'quizzes');
            }

            // recreate cache, as necessary
            if ($CFG->quizport_enablecache && $QUIZPORT->quiz->output->use_quizport_cache) {
                $QUIZPORT->quiz->output->generate(true);
            }

            // regrade quiz, if necessary
            if ($regrade) {
                if ($quizscores = $DB->get_records('quizport_quiz_scores', array('quizid'=>$QUIZPORT->quiz->id), '', 'id,unumber,userid')) {
                    $userids = array();
                    foreach ($quizscores as $id=>$quizscore) {
                        // set quiz score from quiz attempts
                        $QUIZPORT->regrade_attempts('quiz', $QUIZPORT->quiz, $quizscore->unumber, $quizscore->userid);
                        if (! array_key_exists($quizscore->userid, $userids)) {
                            $userids[$quizscore->userid] = array();
                        }
                        $userids[$quizscore->userid][] = $quizscore->unumber;
                    }
                    unset($quizscores);

                    // array of quizzes whose attempts are to be updated
                    $quizzes = array($QUIZPORT->quiz->id => &$QUIZPORT->quiz);

                    // transfer grade settings to quizport record
                    // required by quizport_update_grades (in quizport/lib.php)
                    $QUIZPORT->quizport->gradelimit = $QUIZPORT->unit->gradelimit;
                    $QUIZPORT->quizport->gradeweighting = $QUIZPORT->unit->gradeweighting;

                    // update unit grades and attempts
                    foreach ($userids as $userid=>$unumbers) {
                        foreach ($unumbers as $unumber) {
                            // set unit attempt from quiz score
                            $QUIZPORT->regrade_unitattempt($unit, $unumber, $userid, $quizzes);
                        }

                        // set unit grade from unit attempts
                        $QUIZPORT->regrade_attempts('unit', $unit, 0, $userid);

                        // update grades in Moodle gradebook
                        quizport_update_grades($QUIZPORT->quizport, $userid);
                    }
                    unset($userids, $quizzes);
                }
            }
        }

        // show the quiz
        $id = 'coursemoduleid';
        if ($QUIZPORT->unumber>0 && $QUIZPORT->qnumber>0) {
            // resume the unit/quiz attempt
            $params = array($id => $QUIZPORT->modulerecord->id);
            redirect($QUIZPORT->format_url('view.php', $id, $params));
        } else {
            // resume editing quizzes in unit
            $params = array($id => $QUIZPORT->modulerecord->id, 'quizid' => 0);
            redirect($QUIZPORT->format_url('editquizzes.php', $id, $params));
        }
        break;

    case 'update':
    default:

        // initizialize data in form
        if ($QUIZPORT->quizid) {
            // editing a quiz ($quiz was set up in mod/quizport/class.php)
            $defaults = (array)$quiz;
        } else {
            // adding a new quiz to this unit
            $defaults = array('unitid'=>$QUIZPORT->unitid);
        }
        $mform->data_preprocessing($defaults);
        $mform->set_data($defaults);
        unset($defaults);

        $QUIZPORT->print_header();

        if ($CFG->majorrelease<=1.9) {
            $QUIZPORT->print_main_table_start();
            $QUIZPORT->print_left_column();
            $QUIZPORT->print_middle_column_start();
        }

        // Print quizport name
        $QUIZPORT->print_heading();

        // display the form
        $mform->display();

        if ($CFG->majorrelease<=1.9) {
            $QUIZPORT->print_middle_column_finish();
            $QUIZPORT->print_right_column();
            $QUIZPORT->print_main_table_finish();
        }

        $QUIZPORT->print_footer();
}
?>