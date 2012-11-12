<?php // $Id$
/**
* Standardize Moodle API for Moodle 1.1
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/
if (! function_exists('set_user_preference')) {
    function set_user_preference($name, $value) {
        return true;
    }
}

if (! function_exists('set_user_preferences')) {
    function set_user_preferences($prefarray) {
        return true;
    }
}

if (! function_exists('get_user_preferences')) {
    function get_user_preferences($name=NULL, $default=NULL) {
        return $default;
    }
}

if (! function_exists('can_use_html_editor')) {
    function can_use_html_editor() {
        return false;
    }
}

if (! function_exists('filter_text')) {
    function filter_text($text, $courseid=NULL) {
        return $text;
    }
}

if (! function_exists('fullname')) {
    function fullname($user, $override=false) {
        return "$user->firstname $user->lastname";
    }
}

// set missing strings for Moodle 1.1
if (empty($CFG->quizport_missingstrings_mdl_11)) {
    $missingstrings['mdl_11'] = array(
        'en_utf8' => array(
            'moodle' => array(
                'disable' => 'Disable',
                'deleteall' => 'Delete all',
                'deleteselected' => 'Delete selected',
                'previous' => 'Previous'
            ),
            'quiz' => array(
                'reportfullstat' => 'Detailed statistics'
            )
        )
    );
}
?>