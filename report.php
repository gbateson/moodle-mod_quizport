<?php // $Id$
/**
 * Show reports for individial QuizPort units and quizzes
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

// set $QUIZPORT object
require_once('class.php');

switch (true) {
    case $quizattemptid>0: $url = "report.php?quizattemptid=$quizattemptid"; break;
    case $quizscoreid>0  : $url = "report.php?quizscoreid=$quizscoreid"; break;
    case $unitattemptid>0: $url = "report.php?unitattemptid=$unitattemptid"; break;
    case $unitgradeid>0  : $url = "report.php?unitgradeid=$unitgradeid"; break;
    default: $url = "report.php?id=$coursemodule->id";
}
add_to_log($course->id, 'quizport', 'report', $url, $quizport->id, $coursemodule->id);

$QUIZPORT->print_page();
?>