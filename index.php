<?php // $Id$
/**
 * Index of QuizPort activities in a given course
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

// set $QUIZPORT object
require_once('class.php');

add_to_log($course->id, 'quizport', 'viewindex', "index.php?id=$course->id");

switch ($QUIZPORT->action) {
    case 'regrade':
        $QUIZPORT->regrade_selected_attempts();
        break;
    case 'deleteall':
    case 'deleteselected':
        $QUIZPORT->delete_selected_attempts();
        break;
    case 'deleteinprogress':
        $QUIZPORT->delete_selected_attempts(QUIZPORT_STATUS_INPROGRESS);
        break;
    case 'deletetimedout':
        $QUIZPORT->delete_selected_attempts(QUIZPORT_STATUS_TIMEDOUT);
        break;
    case 'deleteabandoned':
        $QUIZPORT->delete_selected_attempts(QUIZPORT_STATUS_ABANDONED);
        break;
    case 'deletecompleted':
        $QUIZPORT->delete_selected_attempts(QUIZPORT_STATUS_COMPLETED);
        break;
}

$QUIZPORT->print_page();
?>