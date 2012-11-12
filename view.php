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

// check unit visibility, network address, password and popup
if ($error = $QUIZPORT->require_unit_access()) {
    $QUIZPORT->print_error($error);
}

// check unit is set up and is currently available (=open and not closed)
if ($error = $QUIZPORT->require_unit_availability()) {
    $QUIZPORT->print_error($error);
}

if (data_submitted() && isset($quizattempt->id)) {
    // some results have been submitted
    add_to_log($course->id, 'quizport', 'submit', "report.php?quizattemptid=$quizattempt->id", $quizport->id, $coursemodule->id);

    // check whether user can submit to this unit and quiz attempt
    if ($error = $QUIZPORT->require_unit_cansubmit()) {
        $QUIZPORT->print_error($error);
    }
    if ($error = $QUIZPORT->require_quiz_cansubmit()) {
        $QUIZPORT->print_error($error);
    }

    // quizzes are responsible for storing their own results
    $QUIZPORT->quiz->output->store();

    // transfer gradelimit and gradeweighting to $quizport
    // (required for quizport_get_user_grades() in "mod/quizport/lib.php")
    $quizport->gradelimit = $unit->gradelimit;
    $quizport->gradeweighting = $unit->gradeweighting;

    // update grades for this user
    quizport_update_grades($quizport, $USER->id);

    // decide if we need to redirect or not
    switch (true) {
        case $QUIZPORT->quiz->delay3==QUIZPORT_DELAY3_DISABLE:
            // results have already been saved
            $redirect = true;
            break;

        case $QUIZPORT->quizattempt->status==QUIZPORT_STATUS_INPROGRESS:
            // this quiz attempt is still in progress
            $redirect = true;
            break;

        case $QUIZPORT->quizattempt->redirect==0:
            // this quiz attempt has told us not to do anything
            $redirect = true;
            break;

        case $QUIZPORT->quizattempt->status==QUIZPORT_STATUS_ABANDONED:
            // check whether we can continue this quizattempt and/or unitattempt
            switch ($QUIZPORT->quiz->output->can_continue()) {
                case QUIZPORT_CONTINUE_RESUMEQUIZ:  $redirect = true; break;
                case QUIZPORT_CONTINUE_RESTARTQUIZ: $redirect = true; break;
                case QUIZPORT_CONTINUE_RESTARTUNIT: $redirect = empty($QUIZPORT->unit->entrypage); break;
                case QUIZPORT_CONTINUE_ABANDONUNIT: $redirect = false; break;
                default: $redirect = false; // shouldn't happen !!
            }
            if ($redirect) {
                $redirect = $CFG->wwwroot.'/course/view.php?id='.$QUIZPORT->courserecord->id;
            }
            break;

        default:
            // do not redirect
            $redirect = false;
    }

    // redirect the browser (if necessary)
    if ($redirect) {
        $QUIZPORT->quiz->output->redirect($redirect); // script stops here
    }

    if ($QUIZPORT->quizattempt->status==QUIZPORT_STATUS_ABANDONED) {
        // quiz attempt was abandoned, but we can continue
        // reset unitattempt (it will be resumed or created anew later)
        $QUIZPORT->unitattemptid = 0;
        $QUIZPORT->unitattempt = null;
        //$QUIZPORT->unitgradeid = 0;
        //$QUIZPORT->unitgrade = null;
    }

    $QUIZPORT->quizid = 0;
    $QUIZPORT->quiz = null;
    $QUIZPORT->qnumber = 0;
    $QUIZPORT->quizattemptid = 0;
    $QUIZPORT->quizattempt = null;
    $QUIZPORT->quizscoreid = 0;
    $QUIZPORT->quizscore = null;
    $QUIZPORT->cache_available_quiz = array();
} else {
    // no results submitted, so someone is just trying to view this QuizPort
    add_to_log($course->id, 'quizport', 'view', "view.php?id=$coursemodule->id", $quizport->id, $coursemodule->id);
}

// if unit attempt number is >=0, we can
// try to restart from the last quiz attempt
$trylastquizattempt = ($QUIZPORT->unumber>=0);

// check the unit attempt number, if any
if ($error = $QUIZPORT->require_valid_unumber()) {
    $QUIZPORT->print_error($error);
}

switch ($QUIZPORT->action) {
    case 'regrade':
        $QUIZPORT->regrade_selected_attempts();
        break;
    case 'deleteall':
    case 'deleteselected':
        $QUIZPORT->delete_selected_attempts();
}

if ($QUIZPORT->unumber) {

    if ($QUIZPORT->quizid==0) {
        // no quiz specified, so try and decide what to do by looking at the last quiz attempt, if any

        // try to get the last quiz attempt (within this unit attempt)
        if ($trylastquizattempt && ! $error = $QUIZPORT->require_lastattempt('quiz')) {

            // check whether the last quiz attempt is in progress
            if (! $error = $QUIZPORT->require_inprogress('quiz', 'lastquizattempt')) {

                // previous quiz attempt is in progress, so resume from there
                $QUIZPORT->quizid = $QUIZPORT->lastquizattempt->quizid;
                $QUIZPORT->qnumber = $QUIZPORT->lastquizattempt->qnumber;
                $QUIZPORT->quizattempt = &$QUIZPORT->lastquizattempt;
                $QUIZPORT->quizattemptid = &$QUIZPORT->lastquizattempt->id;

            } else {

                // previous quiz attempt is completed (or timedout or abandoned)
                // get id of next quiz that is available for this user (using post conditions)
                $QUIZPORT->quizid = $QUIZPORT->get_available_quiz($QUIZPORT->lastquizattempt->quizid);
                $QUIZPORT->qnumber = -1; // start new quiz attempt

                // Note : $QUIZPORT->quizid may be 0, if the postconditions did not specify what to do next
            }
        }

        if ($QUIZPORT->quizid==0) {
            // either there is no last quiz attempt, i.e. this is the start of a new unit attempt
            // or the post-conditions for the quiz of the last attempt do not specify a next quiz
            // get ids of quizzes that are available to this user (using pre-conditions)
            // ids of available quizzes are put into $QUIZPORT->availablequizids
            $QUIZPORT->get_available_quizzes();
            switch ($QUIZPORT->countavailablequizids) {
                case 0:
                    // no quizzes are available at this time :-(
                    break;

                case 1:
                    // just one quiz is available, so use that
                    $QUIZPORT->quizid = reset($QUIZPORT->availablequizids);
                    $QUIZPORT->qnumber = -1; // start new quiz attempt
                    break;

                default:
                    // several quizzes are available, so let the user choose what to do
                    $QUIZPORT->quizid = QUIZPORT_CONDITIONQUIZID_MENUNEXT;
            }
        }
    }

    if ($QUIZPORT->quizid>0) {
        // make sure we have read in the quiz record
        if (! $QUIZPORT->get_quiz()) {
            $QUIZPORT->print_error('Invalid quiz id');
        }

        // check quiz network address, password
        if ($error = $QUIZPORT->require_quiz_access()) {
            $QUIZPORT->print_error($error);
        }

        // check quiz is set up and is currently available (=open and not closed)
        if ($error = $QUIZPORT->require_quiz_availability()) {
            $QUIZPORT->print_error($error);
        }

        // check qnumber is valid
        if ($error = $QUIZPORT->require_valid_qnumber()) {
            $QUIZPORT->print_error($error);
        }
    }
}

// everything has been set up, so we just have to print the page

if ($QUIZPORT->quizid>0) {
    // quizzes are responsible for displaying themselves
    $QUIZPORT->quiz->output->generate();
} else {
    // display standard page
    $QUIZPORT->print_page();
}
?>