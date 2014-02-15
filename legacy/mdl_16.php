<?php // $Id$
/**
* Standardize Moodle API for Moodle 1.6
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

// PARAM_xxx (lib/moodlelib.php)
if (! defined('PARAM_TEXT')) {
    define('PARAM_TEXT',  0x0001);
}
// PARAM_TEXT should be 0x0009 but that is not handled by clean_param
// so use 0x1000 (=PARAM_CLEANHTML) or 0x0001 (=PARAM_CLEAN)
// Note: PARAM_CLEAN = PARAM_CLEANHTML plus addslashes

// context definitions (lib/accesslib.php)
if (! defined('CONTEXT_SYSTEM')) {
    define('CONTEXT_SYSTEM', 10);
}
if (! defined('CONTEXT_USER')) {
    define('CONTEXT_USER', 30);
}
if (! defined('CONTEXT_COURSECAT')) {
    define('CONTEXT_COURSECAT', 40);
}
if (! defined('CONTEXT_COURSE')) {
    define('CONTEXT_COURSE', 50);
}
if (! defined('CONTEXT_GROUP')) {
    define('CONTEXT_GROUP', 60);
}
if (! defined('CONTEXT_MODULE')) {
    define('CONTEXT_MODULE', 70);
}
if (! defined('CONTEXT_BLOCK')) {
    define('CONTEXT_BLOCK', 80);
}

if (! function_exists('get_context_instance')) {
    // replacement for "get_context_instance()" (lib/accesslib.php)
    function get_context_instance($contextlevel, $instance=0) {
        if (is_object($instance)) {
            $instanceid = $instance->id;
        } else {
            $instanceid = $instance;
        }
        return (object)array('instanceid'=>$instanceid, 'id'=>0, 'contextlevel'=>$contextlevel, 'path'=>'', 'depth'=>0);
    }
}

if (! function_exists('delete_context')) {
    // replacement for "delete_context()" (lib/accesslib.php)
    function delete_context($contextlevel, $instanceid) {
        return true;
    }
}

if (! function_exists('get_capability_courseid')) {
    function get_capability_courseid($context) {
        switch ($context->contextlevel) {
            case CONTEXT_SYSTEM: return SITEID;
            case CONTEXT_COURSE: return $context->instanceid;
            case CONTEXT_MODULE: return get_field('course_modules', 'course', 'id', $context->instanceid);
            default: return 0;
        }
    }
}

if (! function_exists('has_capability')) {
    // replacement for "has_capability()" (lib/accesslib.php)
    function has_capability($capability, $context, $userid=NULL, $doanything=true) {
        $courseid = get_capability_courseid($context);
        switch ($capability) {
            case 'moodle/site:config':
                return isadmin($userid);

            case 'moodle/course:managefiles':
            case 'moodle/course:manageactivities':
            case 'moodle/course:viewhiddensections':
            case 'moodle/course:viewhiddenactivities':
            case 'mod/quizport:manage':
            case 'mod/quizport:preview':
            case 'mod/quizport:grade':
            case 'mod/quizport:viewreports':
            case 'mod/quizport:deleteattempts':
            // courselinks.js.php
            case 'moodle/grade:viewall':
            case 'moodle/site:accessallgroups';
                return (isadmin($userid) || isteacher($courseid, $userid));

            case 'moodle/course:view':
            case 'mod/quizport:view':
            case 'mod/quizport:attempt':
            case 'mod/quizport:reviewmyattempts':
            // courselinks.js.php
            case 'mod/assignment:submit':
            case 'mod/attforblock:view':
            case 'mod/data:writeentry':
            case 'mod/forum:replypost':
            case 'mod/glossary:write':
            case 'mod/quiz:attempt':
            case 'mod/hotpot:attempt':
            case 'mod/scorm:participate':
            case 'mod/survey:participate':
            case 'mod/workshop:participate':
            case 'moodle/grade:view':
                return (isadmin($userid) || isteacher($courseid, $userid) || isstudent($courseid, $userid));

            case 'mod/quizport:ignoretimelimits':
                return false;

            default: die("has_capability($capability) ?");
            // isadmin($userid)
            // isteacher($courseid, $userid)
            // isteacheredit($courseid, $userid)
            // isstudent($courseid, $userid)
        }
    }
}

if (! function_exists('require_capability')) {
    // replacement for "require_capability()" (lib/accesslib.php)
    function require_capability($capability, $context, $userid=NULL, $doanything=true, $errormessage='nopermissions', $stringfile='') {
        if (has_capability($capability, $context, $userid)) {
            return true;
        }
        $capabilityname = get_capability_string($capability);
        print_error($errormessage, $stringfile, $errorlink, $capabilityname);
    }
}

if (! function_exists('get_capability_string')) {
    // copy of "get_capability_string()" (lib/accesslib.php)
    function get_capability_string($capabilityname) {

        // Typical capabilityname is mod/choice:readresponses

        $names = split('/', $capabilityname);
        $stringname = $names[1];                 // choice:readresponses
        $components = split(':', $stringname);
        $componentname = $components[0];               // choice

        switch ($names[0]) {
            case 'mod':
                $string = get_string($stringname, $componentname);
            break;

            case 'block':
                $string = get_string($stringname, 'block_'.$componentname);
            break;

            case 'moodle':
                if ($componentname == 'local') {
                    $string = get_string($stringname, 'local');
                } else {
                    $string = get_string($stringname, 'role');
                }
            break;

            case 'enrol':
                $string = get_string($stringname, 'enrol_'.$componentname);
            break;

            case 'format':
                $string = get_string($stringname, 'format_'.$componentname);
            break;

            case 'gradeexport':
                $string = get_string($stringname, 'gradeexport_'.$componentname);
            break;

            case 'gradeimport':
                $string = get_string($stringname, 'gradeimport_'.$componentname);
            break;

            case 'gradereport':
                $string = get_string($stringname, 'gradereport_'.$componentname);
            break;

            default:
                $string = get_string($stringname);
            break;

        }
        return $string;
    }
}

if (! function_exists('get_users_by_capability')) {
    // replacement for ""get_users_by_capability()" (lib/accesslib.php)
    function get_users_by_capability($context, $capability, $fields='', $sort='',
            $limitfrom='', $limitnum='', $groups='', $exceptions='', $doanything=true,
            $view=false, $useviewallgroups=false) {

        global $CFG;

        $courseid = get_capability_courseid($context);
        switch ($capability) {
            case 'mod/quizport:grade':
            case 'mod/quizport:viewreports':
                $tables = "{$CFG->prefix}user u, {$CFG->prefix}user_teachers ut";
                $select = "ut.course=$courseid AND ut.userid=u.id";
                return get_records_sql("SELECT u.* FROM $tables WHERE $select");

            case 'mod/quizport:attempt':
                $tables = "{$CFG->prefix}user u, {$CFG->prefix}user_students us";
                $select = "us.course=$courseid AND us.userid=u.id";
                return get_records_sql("SELECT u.* FROM $tables WHERE $select");

            default: die("get_users_by_capability($capability) ?");
            // isadmin($userid)
            // isteacher($courseid, $userid)
            // isteacheredit($courseid, $userid)
            // isstudent($courseid, $userid)
        }
    }
}

if (! function_exists('set_field_select')) {
    // replacement for ""set_field_select()" (lib/dmllib.php)
    function set_field_select($table, $newfield, $newvalue, $select='') {

        global $db, $CFG;

        if (defined('MDL_PERFDB')) { global $PERF ; $PERF->dbqueries++; };

        if ($select) {
            $select = 'WHERE '.$select;
        }

        return $db->Execute('UPDATE '. $CFG->prefix . $table .' SET '. $newfield  .' = \''. $newvalue .'\' '. $select);
    }
}

if (! function_exists('get_field_select')) {
    // replacement for ""get_field_select()" (lib/dmllib.php)
    function get_field_select($table, $return, $select) {
        global $CFG;
        if ($select) {
            $select = 'WHERE '. $select;
        }
        return get_field_sql('SELECT ' . $return . ' FROM ' . $CFG->prefix . $table . ' ' . $select);
    }
}

if (! function_exists('record_exists_select')) {
    // replacement for ""record_exists_select()" (lib/dmllib.php)
    function record_exists_select($table, $select='') {
        global $CFG;
        if ($select) {
            $select = 'WHERE '.$select;
        }
        return record_exists_sql('SELECT * FROM '. $CFG->prefix . $table . ' ' . $select);
    }
}

if (! function_exists('unset_config')) {
    // copy of "unset_config()" (lib/moodlelib)
    function unset_config($name, $plugin=NULL) {
        global $CFG, $DB;
        unset($CFG->$name);

        $conditions = array('name'=>$name);
        if ($plugin) {
            $conditions['plugin'] = $plugin;
        }
        return $DB->delete_records('config', $conditions);
    }
}

/// Debug levels (lib/moodlelib.php)
if (! defined('DEBUG_NONE')) {
    define('DEBUG_NONE', 0);
}
if (! defined('DEBUG_MINIMAL')) {
    define('DEBUG_MINIMAL', 5);
}
if (! defined('DEBUG_NORMAL')) {
    define('DEBUG_NORMAL', 15);
}
if (! defined('DEBUG_ALL')) {
    define('DEBUG_ALL', 6143);
}
if (! defined('DEBUG_DEVELOPER')) {
    define('DEBUG_DEVELOPER', 38911);
}

if (! function_exists('debugging')) {
    // copy of "debugging()" (lib/moodlelib)
    function debugging($message='', $level=DEBUG_NORMAL) {

        global $CFG;

        if (empty($CFG->debug)) {
            return false;
        }

        if ($CFG->debug >= $level) {
            if ($message) {
                $callers = debug_backtrace();
                $from = '<ul style="text-align: left">';
                foreach ($callers as $caller) {
                    if (!isset($caller['line'])) {
                        $caller['line'] = '?'; // probably call_user_func()
                    }
                    if (!isset($caller['file'])) {
                        $caller['file'] = $CFG->dirroot.'/unknownfile'; // probably call_user_func()
                    }
                    $from .= '<li>line ' . $caller['line'] . ' of ' . substr($caller['file'], strlen($CFG->dirroot) + 1);
                    if (isset($caller['function'])) {
                        $from .= ': call to ';
                        if (isset($caller['class'])) {
                            $from .= $caller['class'] . $caller['type'];
                        }
                        $from .= $caller['function'] . '()';
                    }
                    $from .= '</li>';
                }
                $from .= '</ul>';
                if (!isset($CFG->debugdisplay)) {
                    $CFG->debugdisplay = ini_get_bool('display_errors');
                }
                if ($CFG->debugdisplay) {
                    if (! defined('DEBUGGING_PRINTED')) {
                        define('DEBUGGING_PRINTED', 1); // indicates we have printed something
                    }
                    notify($message . $from, 'notifytiny');
                } else {
                    trigger_error($message . $from, E_USER_NOTICE);
                }
            }
            return true;
        }
        return false;
    }
}

// set missing strings for Moodle 1.6
if (empty($CFG->quizport_missingstrings_mdl_16)) {
    $missingstrings['mdl_16'] = array(
        'en_utf8'=>array(
            'form' => array(
                'display' => 'Display',
                'general' => 'General',
                'hideadvanced' => 'Hide Advanced',
                'modstandardels' => 'Common module settings',
                'showadvanced' => 'Show Advanced'
            ),
            'admin' => array(
                'coursemanager' => 'Course managers'
            )
        )
    );
}
?>