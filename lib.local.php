<?php // $Id$
/**
 * Library of functions for the quizport module
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

// block direct access to this script
if (empty($CFG)) {
    die;
}

require_once($CFG->dirroot.'/mod/quizport/lib.php');

define('QUIZPORT_NO',  '0');
define('QUIZPORT_YES', '1');

define('QUIZPORT_MIN', '-1');
define('QUIZPORT_MAX', '1');

define('QUIZPORT_GRADEMETHOD_TOTAL',   '0');
define('QUIZPORT_GRADEMETHOD_HIGHEST', '1');
define('QUIZPORT_GRADEMETHOD_AVERAGE', '2');
define('QUIZPORT_GRADEMETHOD_FIRST',   '3');
define('QUIZPORT_GRADEMETHOD_LAST',    '4');
define('QUIZPORT_GRADEMETHOD_LASTCOMPLETED', '5');
define('QUIZPORT_GRADEMETHOD_LASTTIMEDOUT',  '6');
define('QUIZPORT_GRADEMETHOD_LASTABANDONED', '7');

define('QUIZPORT_EQUALWEIGHTING', '-1');

define('QUIZPORT_NAVIGATION_NONE', '0');
define('QUIZPORT_NAVIGATION_BAR', '1');
define('QUIZPORT_NAVIGATION_FRAME', '2');
define('QUIZPORT_NAVIGATION_EMBED', '3');
define('QUIZPORT_NAVIGATION_ORIGINAL', '4');

define('QUIZPORT_STOPBUTTON_NONE', 0);
define('QUIZPORT_STOPBUTTON_LANGPACK', 1);
define('QUIZPORT_STOPBUTTON_SPECIFIC', 2);

define('QUIZPORT_TITLE_SOURCE', 0x03); // 1st - 2nd bits
define('QUIZPORT_TITLE_UNITNAME', 0x04); // 3rd bit
define('QUIZPORT_TITLE_SORTORDER', 0x08); // 4th bit

define('QUIZPORT_FEEDBACK_NONE', '0');
define('QUIZPORT_FEEDBACK_WEBPAGE', '1');
define('QUIZPORT_FEEDBACK_FORMMAIL', '2');
define('QUIZPORT_FEEDBACK_MOODLEFORUM', '3');
define('QUIZPORT_FEEDBACK_MOODLEMESSAGING', '4');

define('QUIZPORT_CONDITIONTYPE_PRE',  '1');
define('QUIZPORT_CONDITIONTYPE_POST', '2');

define('QUIZPORT_CONDITIONQUIZID_SAME',        '-1');
define('QUIZPORT_CONDITIONQUIZID_PREVIOUS',    '-2');
define('QUIZPORT_CONDITIONQUIZID_NEXT1',       '-3'); // was NEXT
define('QUIZPORT_CONDITIONQUIZID_NEXT2',       '-4'); // was SKIP
define('QUIZPORT_CONDITIONQUIZID_NEXT3',       '-5');
define('QUIZPORT_CONDITIONQUIZID_NEXT4',       '-6');
define('QUIZPORT_CONDITIONQUIZID_NEXT5',       '-7');
define('QUIZPORT_CONDITIONQUIZID_UNSEEN',      '-10'); // was -5
define('QUIZPORT_CONDITIONQUIZID_UNANSWERED',  '-11'); // was -6
define('QUIZPORT_CONDITIONQUIZID_INCORRECT',   '-12'); // was -7
define('QUIZPORT_CONDITIONQUIZID_RANDOM',      '-13'); // was -8
define('QUIZPORT_CONDITIONQUIZID_MENUNEXT',    '-20'); // was -10 was -11
define('QUIZPORT_CONDITIONQUIZID_MENUNEXTONE', '-21'); // was -11
define('QUIZPORT_CONDITIONQUIZID_MENUALL',     '-22'); // was -12 was -10
define('QUIZPORT_CONDITIONQUIZID_MENUALLONE',  '-23'); // was -13
define('QUIZPORT_CONDITIONQUIZID_ENDOFUNIT',   '-99');

define('QUIZPORT_ATTEMPTTYPE_ANY', '0');
define('QUIZPORT_ATTEMPTTYPE_RECENT', '1');
define('QUIZPORT_ATTEMPTTYPE_CONSECUTIVE', '2');

define ('QUIZPORT_TEXTSOURCE_FILE', '0');
define ('QUIZPORT_TEXTSOURCE_FILENAME', '1');
define ('QUIZPORT_TEXTSOURCE_FILEPATH', '2');
define ('QUIZPORT_TEXTSOURCE_SPECIFIC', '3');

define('QUIZPORT_DELAY3_TEMPLATE', '-1');
define('QUIZPORT_DELAY3_AFTEROK',  '-2');
define('QUIZPORT_DELAY3_DISABLE',  '-3');

define('QUIZPORT_COLUMNS_EDITQUIZZES_ALL', '');
define('QUIZPORT_COLUMNS_EDITQUIZZES_DEFAULT', '');
define('QUIZPORT_COLUMNS_EDITQUIZZES_MINIMUM', '');

define('QUIZPORT_COLUMNS_ALL', '1');
define('QUIZPORT_COLUMNS_DEFAULT', '2');
define('QUIZPORT_COLUMNS_MINIMUM', '3');
define('QUIZPORT_COLUMNS_CUSTOM', '4');

define('QUIZPORT_ADDQUIZZES_ATSTART', '-1');
define('QUIZPORT_ADDQUIZZES_ATEND', '-2');

define('QUIZPORT_SELECTQUIZZES_THISQUIZPORT', '1');
define('QUIZPORT_SELECTQUIZZES_ALLMYQUIZPORTS', '2');
define('QUIZPORT_SELECTQUIZZES_ALLMYCOURSES', '3');
define('QUIZPORT_SELECTQUIZZES_WHOLESITE', '4');

define('QUIZPORT_QUIZZESACTION_DELETE', '-1');
define('QUIZPORT_QUIZZESACTION_DEFAULTS', '-2');
define('QUIZPORT_QUIZZESACTION_MOVETOSTART', '-3');

define('QUIZPORT_TIMELIMIT_TEMPLATE', '-1');
define('QUIZPORT_TIMELIMIT_DISABLE', '1');
define('QUIZPORT_TIMELIMIT_SPECIFIC', '0');

define('QUIZPORT_ACTIVITY_NONE', '0');
define('QUIZPORT_ACTIVITY_COURSE_ANY', '-1');
define('QUIZPORT_ACTIVITY_SECTION_ANY', '-2');
define('QUIZPORT_ACTIVITY_COURSE_GRADED', '-3');
define('QUIZPORT_ACTIVITY_SECTION_GRADED', '-4');
define('QUIZPORT_ACTIVITY_COURSE_QUIZPORT', '-5');
define('QUIZPORT_ACTIVITY_SECTION_QUIZPORT', '-6');

define('QUIZPORT_ENTRYOPTIONS_TITLE', 0x01);
define('QUIZPORT_ENTRYOPTIONS_GRADING', 0x02);
define('QUIZPORT_ENTRYOPTIONS_DATES', 0x04);
define('QUIZPORT_ENTRYOPTIONS_ATTEMPTS', 0x08);

define('QUIZPORT_EXITOPTIONS_TITLE', 0x01);
define('QUIZPORT_EXITOPTIONS_ENCOURAGEMENT', 0x02);
define('QUIZPORT_EXITOPTIONS_UNITATTEMPT', 0x04);
define('QUIZPORT_EXITOPTIONS_UNITGRADE', 0x08);
define('QUIZPORT_EXITOPTIONS_RETRY', 0x10);
define('QUIZPORT_EXITOPTIONS_INDEX', 0x20);
define('QUIZPORT_EXITOPTIONS_COURSE', 0x40);
define('QUIZPORT_EXITOPTIONS_GRADES', 0x80);

define('QUIZPORT_CONTINUE_RESUMEQUIZ',   1);
define('QUIZPORT_CONTINUE_RESTARTQUIZ',  2);
define('QUIZPORT_CONTINUE_RESTARTUNIT',  3);
define('QUIZPORT_CONTINUE_ABANDONUNIT',  4);

define('QUIZPORT_ALLOWRESUME_NO',        0);
define('QUIZPORT_ALLOWRESUME_YES',       1);
define('QUIZPORT_ALLOWRESUME_FORCE',     2);

define('QUIZPORT_BODYSTYLES_BACKGROUND', 1);
define('QUIZPORT_BODYSTYLES_COLOR',      2);
define('QUIZPORT_BODYSTYLES_FONT',       4);
define('QUIZPORT_BODYSTYLES_MARGIN',     8);

/**
* The different review options are stored in the bits of $quiz->reviewoptions
* These constants help to extract the options
*/
// three sets of 6 bits define the times at which a quiz may be reviewed
define('QUIZPORT_REVIEW_DURINGATTEMPT', 0x3f); // 1st set of 6 bits : during attempt
define('QUIZPORT_REVIEW_AFTERATTEMPT', 0xfc0); // 2nd set of 6 bits : after attempt (but before quiz closes)
define('QUIZPORT_REVIEW_AFTERCLOSE', 0x3f000); // 3rd set of 6 bits : after the quiz closes
//define('QUIZPORT_REVIEW_UNUSED',    0xfc0000); // 4th set of 6 bits : unused

// within each group of 6 bits we determine what should be shown
// Note: 0x1041 = 00 0001 0000 0100 0001 = 000001 000001 000001 (i.e. 3 sets of 6 bits)
define('QUIZPORT_REVIEW_RESPONSES', 1*0x1041); // 1st bit of each 6-bit set : Show student responses
define('QUIZPORT_REVIEW_ANSWERS',   2*0x1041); // 2nd bit of each 6-bit set : Show correct answers
define('QUIZPORT_REVIEW_SCORES',    4*0x1041); // 3rd bit of each 6-bit set : Show scores
define('QUIZPORT_REVIEW_FEEDBACK',  8*0x1041); // 4th bit of each 6-bit set : Show feedback
//define('QUIZPORT_REVIEW_UNUSED',   16*0x1041); // 5th bit of each 6-bit set : unused
//define('QUIZPORT_REVIEW_UNUSED',   32*0x1041); // 6th bit of each 6-bit set : unused

function quizport_format_allowresume($allowresume=null) {
    static $str = null;
    if (is_null($str)) {
        $str = array(
            QUIZPORT_ALLOWRESUME_NO => get_string('no'),
            QUIZPORT_ALLOWRESUME_YES => get_string('yes'),
            QUIZPORT_ALLOWRESUME_FORCE => get_string('force')
        );
    }
    if (is_null($allowresume)) {
        return $str;
    }
    if (array_key_exists($allowresume, $str)) {
        return $str[$allowresume];
    }
    return $allowresume;
}

function quizport_format_grademethod($type, $grademethod=null) {
    static $str = array();
    if (empty($str[$type])) {
        switch ($type) {
            case 'unit':
            case 'quiz':
                $str[$type] = array(
                    QUIZPORT_GRADEMETHOD_HIGHEST => get_string('highest', 'quizport'),
                    QUIZPORT_GRADEMETHOD_AVERAGE => get_string('average', 'quizport'),
                    QUIZPORT_GRADEMETHOD_FIRST => get_string('first', 'quizport'),
                    QUIZPORT_GRADEMETHOD_LAST => get_string('last', 'quizport')
                );
                break;
            case 'unitattempt':
                $str[$type] = array(
                    QUIZPORT_GRADEMETHOD_TOTAL => get_string('total', 'quizport'),
                    QUIZPORT_GRADEMETHOD_HIGHEST => get_string('highest', 'quizport'),
                    QUIZPORT_GRADEMETHOD_LAST => get_string('last', 'quizport'),
                    QUIZPORT_GRADEMETHOD_LASTCOMPLETED => get_string('lastcompleted', 'quizport'),
                    QUIZPORT_GRADEMETHOD_LASTTIMEDOUT => get_string('lasttimedout', 'quizport'),
                    QUIZPORT_GRADEMETHOD_LASTABANDONED => get_string('lastabandoned', 'quizport')
                );
                break;
            default:
                $str[$type] = array(); // shouldn't happen !!
        }
    }
    if (is_null($grademethod)) {
        return $str[$type];
    }
    if (array_key_exists($grademethod, $str[$type])) {
        return $str[$type][$grademethod];
    }
    return $grademethod;
}

function quizport_format_location($location=null) {
    static $str = null;
    if (is_null($str)) {
        $str = array(
            QUIZPORT_LOCATION_COURSEFILES => get_string('coursefiles'),
            QUIZPORT_LOCATION_SITEFILES => get_string('sitefiles'),
            QUIZPORT_LOCATION_WWW => get_string('webpage')
        );
    }
    if (is_null($location)) {
        return $str;
    }
    if (array_key_exists($location, $str)) {
        return $str[$location];
    }
    return $location;
}

function quizport_format_navigation($navigation=null) {
    static $str = null;
    if (is_null($str)) {
        $str = array (
            QUIZPORT_NAVIGATION_NONE => get_string('navigation_none', 'quizport'),
            QUIZPORT_NAVIGATION_BAR => get_string('navigation_bar', 'quizport'),
            QUIZPORT_NAVIGATION_FRAME => get_string('navigation_frame', 'quizport'),
            QUIZPORT_NAVIGATION_EMBED => get_string('navigation_embed', 'quizport'),
            QUIZPORT_NAVIGATION_ORIGINAL => get_string('navigation_original', 'quizport')
        );
    }
    if (is_null($navigation)) {
        return $str;
    }
    if (array_key_exists($navigation, $str)) {
        return $str[$navigation];
    }
    return $navigation;
}

function quizport_format_title($title=null) {
    static $str = null;
    if (is_null($str)) {
        $str = array (
            QUIZPORT_TEXTSOURCE_SPECIFIC => get_string('quizname', 'quizport'),
            QUIZPORT_TEXTSOURCE_FILE => get_string('textsourcefile', 'quizport'),
            QUIZPORT_TEXTSOURCE_FILENAME => get_string('textsourcefilename', 'quizport'),
            QUIZPORT_TEXTSOURCE_FILEPATH => get_string('textsourcefilepath', 'quizport')
        );
    }
    if (is_null($title)) {
        return $str;
    }
    if (array_key_exists($title, $str)) {
        return $str[$title];
    }
    return $title;
}

function quizport_format_stopbutton($stopbutton=null) {
    static $str = null;
    if (is_null($str)) {
        $str = array (
            QUIZPORT_STOPBUTTON_NONE => get_string('none'),
            QUIZPORT_STOPBUTTON_LANGPACK => get_string('stopbutton_langpack', 'quizport'),
            QUIZPORT_STOPBUTTON_SPECIFIC => get_string('stopbutton_specific', 'quizport')
        );
    }
    if (is_null($stopbutton)) {
        return $str;
    }
    if (array_key_exists($stopbutton, $str)) {
        return $str[$stopbutton];
    }
    return $stopbutton;
}

function quizport_format_stoptext($stopbutton, $stoptext) {
    if ($stopbutton) {
        if ($this->stopbutton==QUIZPORT_STOPBUTTON_LANGPACK) {
            if ($pos = strpos($stoptext, '_')) {
                $mod = substr($stoptext, 0, $pos);
                $str = substr($stoptext, $pos + 1);
                $stoptext = get_string($str, $mod);
            } else if ($stoptext) {
                $stoptext = get_string($this->stoptext);
            } else {
                $stoptext = '';
            }
        }
        if (trim($stoptext)=='') {
            $stoptext = get_string('giveup', 'quizport');
        }
    }
    return $stoptext;
}

function quizport_format_studentfeedback($studentfeedback=null) {
    static $str = null;
    if (is_null($str)) {
        $str = array (
            QUIZPORT_FEEDBACK_NONE => get_string('none'),
            QUIZPORT_FEEDBACK_WEBPAGE => get_string('feedbackwebpage',  'quizport'),
            QUIZPORT_FEEDBACK_FORMMAIL => get_string('feedbackformmail', 'quizport'),
            QUIZPORT_FEEDBACK_MOODLEFORUM => get_string('feedbackmoodleforum', 'quizport'),
            QUIZPORT_FEEDBACK_MOODLEMESSAGING => get_string('feedbackmoodlemessaging', 'quizport')
        );
    }
    if (is_null($studentfeedback)) {
        return $str;
    }
    if (array_key_exists($studentfeedback, $str)) {
        return $str[$studentfeedback];
    }
    return $studentfeedback;
}

function quizport_format_reviewoptions($reviewoptions) {
    $str = '';
    if ($reviewoptions) {
        list($times, $items) = get_reviewoptions_timesitems();
        foreach ($items as $item) {
            if (strlen($str)) {
                $str .= '<br />';
            }
            $str .= substr($item, 0, 1).' : ';
            foreach ($times as $time) {
                eval('$value = ($reviewoptions & '.strtoupper("QUIZPORT_REVIEW_$item & QUIZPORT_REVIEW_$time").');');
                if ($value) {
                    $str .= 'o';
                } else {
                    $str .= 'x';
                }
            }
        }
    }
    return $str;
}

function quizport_format_cm($cm=null, $type='') {
    static $str = array();
    if (empty($str[$type])) {
        $str[$type] = array (
            QUIZPORT_ACTIVITY_NONE => get_string('none'),
            QUIZPORT_ACTIVITY_COURSE_ANY => get_string($type.'cmcourse', 'quizport'),
            QUIZPORT_ACTIVITY_SECTION_ANY => get_string($type.'cmsection', 'quizport'),
            QUIZPORT_ACTIVITY_COURSE_GRADED => get_string($type.'gradedcourse', 'quizport'),
            QUIZPORT_ACTIVITY_SECTION_GRADED => get_string($type.'gradedsection', 'quizport'),
            QUIZPORT_ACTIVITY_COURSE_QUIZPORT => get_string($type.'quizportcourse', 'quizport'),
            QUIZPORT_ACTIVITY_SECTION_QUIZPORT => get_string($type.'quizportsection', 'quizport')
        );
    }
    if (is_null($cm)) {
        return $str[$type];
    }
    if (array_key_exists($cm, $str[$type])) {
        return $str[$type][$cm];
    }
    return $cm;
}
?>