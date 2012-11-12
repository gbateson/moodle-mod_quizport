<?php // $Id$

// set $QUIZPORT object
require_once('class.php');

class mod_quizport_editquiz extends mod_quizport {
    var $pagehascolumns = false;
    var $pagehasreporttab = true;

    function print_heading() {
        switch ($this->action) {
            case 'deleteconfirmed' :
            case 'delete':
                $heading = get_string('deletequiz', 'quizport', format_string($this->quiz->name));
                break;

            case 'update':
            default:
                if ($this->quizid) {
                    // Updating a QuizPort Quiz
                    $stringname = 'updatinga';
                } else {
                    // Adding a new QuizPort Quiz
                    $stringname = 'addinganew';
                }
                $heading = get_string($stringname, 'moodle', get_string('quiz', 'quizport'));
        }
        print_heading($heading);
    }
}
?>