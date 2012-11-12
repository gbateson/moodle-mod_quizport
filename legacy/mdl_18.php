<?php // $Id$
/**
* Standardize Moodle API for Moodle 1.8
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

// copied from (lib/grade/constants.php)
if (! defined('GRADE_TYPE_NONE')) {
    define('GRADE_TYPE_NONE', 0);
}
if (! defined('GRADE_TYPE_VALUE')) {
    define('GRADE_TYPE_VALUE', 1);
}
if (! defined('GRADE_UPDATE_OK')) {
    define('GRADE_UPDATE_OK', 0);
}

// copied from (lib/grouplib.php)
if (! defined('NOGROUPS')) {
    define('NOGROUPS', 0);
}
if (! defined('SEPARATEGROUPS')) {
    define('SEPARATEGROUPS', 1);
}
if (! defined('VISIBLEGROUPS')) {
    define('VISIBLEGROUPS', 2);
}

if (! function_exists('quizport_print_header')) {
    // replacement for "page_generic_activity->print_header()" (lib/pagelib.php)
    function quizport_print_header($title, $morenavlinks=null, $bodytags='', $meta='') {
        global $CFG, $PAGE, $THEME;

        $PAGE->init_full();

        $buttons = '&nbsp;';
        if (empty($morenavlinks)) {
            if ($PAGE->user_allowed_editing()) {
                $buttons = '<table><tr><td>'.update_module_button($PAGE->modulerecord->id, $PAGE->courserecord->id, get_string('modulename', $PAGE->activityname)).'</td>';
                if (!empty($CFG->showblocksonmodpages)) {
                    if ($PAGE->user_is_editing()) {
                        $edit = 'off';
                    } else {
                        $edit = 'on';
                    }
                    $buttons .= '<td><form '.$CFG->frametarget.' method="get" action="view.php"><div>'.
                        '<input type="hidden" name="id" value="'.$PAGE->modulerecord->id.'" />'.
                        '<input type="hidden" name="edit" value="'.$edit.'" />'.
                        '<input type="submit" value="'.get_string('blocksedit'.$edit).'" /></div></form></td>';
                }
                $buttons .= '</tr></table>';
            }
            $morenavlinks = array();
        }

        $navigation = build_navigation($morenavlinks, $PAGE->modulerecord);

        if ($CFG->majorrelease<=1.4) {
            $meta .= '<link rel="stylesheet" type="text/css" href="'.$CFG->wwwroot.'/mod/quizport/styles.php" />';
            if ($CFG->majorrelease<=1.2) {
                $THEME->body .= '"'.' class="mod-quizport" id="'.QUIZPORT_PAGEID;
            } else {
                $bodytags .= ' class="mod-quizport" id="'.QUIZPORT_PAGEID.'"';
            }
        }

        // $title, $heading, $navigation, $focus, $meta, $cache, $buttons, $menu, $usexml, $bodytags, $return
        print_header($title, $PAGE->courserecord->fullname, $navigation, '', $meta, true, $buttons, navmenu($PAGE->courserecord, $PAGE->modulerecord), false, $bodytags);
    }
}

if (! function_exists('build_navigation')) {
    // replacement for "build_navigation()" (lib/weblib.php)
    // see "print_navigation()" (lib/weblib.php) for format of $navigation
    function build_navigation($extranavlinks, $cm=null) {
        global $CFG, $PAGE;

        $navigation = array();

        // Course name, if appropriate.
        if (isset($PAGE->courserecord->id) && $PAGE->courserecord->id != SITEID) {
            $navigation[] = array(
                'title'=>format_string($PAGE->courserecord->shortname),
                'url'=>$CFG->wwwroot.'/course/view.php?id='.$PAGE->courserecord->id
            );
        }

        // Activity type and instance, if appropriate.
        if (is_object($cm) && isset($cm->modname) && isset($cm->name)) {
            $navigation[] = array(
                'title'=>get_string('modulenameplural', $cm->modname),
                'url'=>$CFG->wwwroot.'/mod/'.$cm->modname.'/index.php?id='.$cm->course
            );
            $navigation[] = array(
                'title'=>format_string($cm->name),
                'url'=>$CFG->wwwroot.'/mod/'.$cm->modname.'/view.php?id='.$cm->id
            );
        }

        // Extra navigation links, if appropriate
        if ($extranavlinks) {
            if (is_array($extranavlinks)) {
                foreach ($extranavlinks as $navlink) {
                    if (! is_array($navlink)) {
                        continue;
                    }
                    $navigation[] = array(
                        'title' => $navlink['name'],
                        'url' => (empty($navlink['link']) ? '' : $navlink['link'])
                    );
                }
            } else if (is_string($extranavlinks)) {
                $navigation[] = array('title'=>$extranavlinks, 'link'=>'');
            }
        }

        if ($count = count($navigation)) {
            $navigation[$count-1]['url'] = '';
        }

        if ($CFG->majorrelease<=1.7) {
            // convert $navigation to a string
            $str = '';
            foreach ($navigation as $i=>$navlink) {
                if ($i) {
                    $str .= ' -> ';
                }
                if (empty($navlink['url']) || $i==($count-1)) {
                    $str .= $navlink['title'];
                } else {
                    $str .= '<a href="'.$navlink['url'].'" onclick="'."this.target='$CFG->framename'".'">'.$navlink['title'].'</a>';
                }
            }
            $navigation = $str;
        }

        return $navigation;
    }
}

if (! function_exists('grade_update')) {
    // replacement for "grade_update()" (lib/gradelib.php)
    function grade_update($source, $courseid, $itemtype, $itemmodule, $iteminstance, $itemnumber, $grades=NULL, $itemdetails=NULL) {
        return GRADE_UPDATE_OK;
    }
}

if (! function_exists('grade_get_grades')) {
    // replacement for "grade_get_grades()" (lib/gradelib.php)
    function grade_get_grades($courseid, $itemtype, $itemmodule, $iteminstance, $userids=null) {
        global $CFG;

        $file = $CFG->dirroot.'/mod/'.$itemmodule.'/lib.php';
        if (! file_exists($file)) {
            return false;
        }

        // get the lib.php for this module
        require_once($file);

        $function = $itemmodule.'_grades';
        if (! function_exists($function)) {
            return false;
        }

        $grades = $function($iteminstance);
        if (! $grades || ! isset($grades->grades)) {
            return false;
        }

        $usergrades = array();
        if (is_null($userids)) {
            $userids = array_keys($grades->grades);
        } else if (! is_array($userids)) {
            $userids = explode(',', $userids);
        }

        foreach($userids as $userid) {
            if (isset($grades->grades[$userid])) {
                $usergrades[$userid] = (object)array('grade' => $grades->grades[$userid]);
            }
        }

        return (object)array(
            'items' => array(
                0 => (object)array(
                    'grades' => $usergrades,
                    'grademax' => empty($grades->maxgrade) ? 0 : $grades->maxgrade
                )
            )
        );
    }
}

if (! function_exists('coursemodule_visible_for_user')) {
    // replacement for "coursemodule_visible_for_user()" (lib/accesslib.php)
    function coursemodule_visible_for_user($cm, $userid=0) {
        global $USER;

        if (empty($cm->id)) {
            debugging("Incorrect course module parameter!", DEBUG_DEVELOPER);
            return false;
        }
        if (empty($userid)) {
            $userid = $USER->id;
        }
        if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', get_context_instance(CONTEXT_MODULE, $cm->id), $userid)) {
            return false;
        }
        return groups_course_module_visible($cm, $userid);
    }
}

if (! function_exists('groups_course_module_visible')) {
    function groups_course_module_visible($cm, $userid=null) {
        global $CFG, $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }
        if (empty($CFG->enablegroupings)) {
            return true;
        }
        if (empty($cm->groupmembersonly)) {
            return true;
        }
        //if (has_capability('moodle/site:accessallgroups', get_context_instance(CONTEXT_MODULE, $cm->id), $userid) or groups_has_membership($cm, $userid)) {
        //    return true;
        //}
        return false;
    }
}

if (! function_exists('element_to_popup_window')) {
    // standard "element_to_popup_window()" (lib/weblib.php)
    function element_to_popup_window ($type=null, $url=null, $name=null, $linkname=null,
                                      $height=400, $width=500, $title=null,
                                      $options=null, $return=false, $id=null, $class=null) {

        if (is_null($url)) {
            debugging('You must give the url to display in the popup. URL is missing - can\'t create popup window.', DEBUG_DEVELOPER);
        }

        global $CFG;

        if ($options == 'none') { // 'none' is legacy, should be removed in v2.0
            $options = null;
        }

        // add some sane default options for popup windows
        if (!$options) {
            $options = 'menubar=0,location=0,scrollbars,resizable';
        }
        if ($width) {
            $options .= ',width='. $width;
        }
        if ($height) {
            $options .= ',height='. $height;
        }
        if ($id) {
            $id = ' id="'.$id.'" ';
        }
        if ($class) {
            $class = ' class="'.$class.'" ';
        }
        if ($name) {
            $_name = $name;
            if (($name = preg_replace("/\s/", '_', $name)) != $_name) {
                debugging('The $name of a popup window should not contain spaces - string modified. '. $_name .' changed to '. $name, DEBUG_DEVELOPER);
            }
        } else {
            $name = 'popup';
        }

        // get some default string, using the localized version of legacy defaults
        if (is_null($linkname) || $linkname === '') {
            $linkname = get_string('clickhere');
        }
        if (!$title) {
            $title = get_string('popupwindowname');
        }

        $fullscreen = 0; // must be passed to openpopup
        $element = '';

        switch ($type) {
            case 'button' :
                $element = '<input type="button" name="'. $name .'" title="'. $title .'" value="'. $linkname .'" '. $id . $class .
                           'onclick="'."return openpopup('$url', '$name', '$options', $fullscreen);".'" />'."\n";
                break;
            case 'link' :
                // some log url entries contain _SERVER[HTTP_REFERRER] in which case wwwroot is already there.
                if (!(strpos($url,$CFG->wwwroot) === false)) {
                    $url = substr($url, strlen($CFG->wwwroot));
                }
                $element = '<a title="'. s(strip_tags($title)) .'" href="'. $CFG->wwwroot . $url .'" '.
                           'onclick="'."this.target='$name'; return openpopup('$url', '$name', '$options', $fullscreen);".'">'.$linkname.'</a>'."\n";
                break;
            default :
                error("Undefined element type ($type) - can't create popup window.");
                break;
        }

        if ($return) {
            return $element;
        } else {
            echo $element;
        }
    }
}

if (! function_exists('groups_get_course_groupmode')) {
    // replacement for "groups_get_course_groupmode()" (lib/grouplib.php)
    function groups_get_course_groupmode($course) {
        if (empty($course->groupmode)) {
            return 0;
        } else {
            return $course->groupmode;
        }
    }
}

if (! function_exists('groups_get_all_groupings')) {
    // replacement for "groups_get_all_groupings()" (lib/grouplib.php)
    function groups_get_all_groupings($courseid) {
        return false;
    }
}

if (! function_exists('groups_get_all_groups')) {
    // replacement for "groups_get_all_groups()" (lib/grouplib.php)
    function groups_get_all_groups($courseid) {
        global $CFG;
        if (! $courseid) {
            return false;
        }
        if ($CFG->majorrelease<=1.7) {
            // Moodle 1.7 and earlier does not have groups
            return false;
        }
        return get_records_select('groups_courses_groups', "courseid=$courseid");
    }
}

if (! function_exists('groups_get_activity_groupmode')) {
    function groups_get_activity_groupmode($cm, $course=null) {
        global $COURSE;

        // get course object (reuse COURSE if possible)
        if (isset($course->id) && $course->id == $cm->course) {
            //ok
        } else if (isset($COURSE->id) && $cm->course == $COURSE->id) {
            $course = $COURSE;
        } else {
            if (! $course = get_record('course', 'id', $cm->course)) {
                return NOGROUPS;
            }
        }

        return empty($course->groupmodeforce) ? $cm->groupmode : $course->groupmode;
    }
}

if (! function_exists('blocks_delete_all_on_page')) {
    // replacement for "blocks_delete_all_on_page()" (lib/blocklib.php)
    function blocks_delete_all_on_page($pagetype, $pageid) {
        global $DB;
        $select = "pageid=$pageid AND pagetype='$pagetype'";
        if ($instances = $DB->get_records_select('block_instance', $select, null, '', 'id, id AS instanceid')) {
            foreach ($instances as $instance) {
                delete_context(CONTEXT_BLOCK, $instance->id);
            }
        }
        return $DB->delete_records_select('block_instance', $select);
    }
}

if (! function_exists('upgrade_mod_savepoint')) {
    function upgrade_mod_savepoint($result, $version, $modname, $allowabort=true) {
        global $DB;
        static $versions = array();

        if ($result) {
            if (! isset($versions[$modname])) {
                $versions[$modname] = $DB->get_field('modules', 'version', array('name'=>$modname));
            }
            if ($versions[$modname] && $versions[$modname] < $version) {
                $DB->set_field('modules', 'version', $version, array('name'=>$modname));
                $versions[$modname] = $version;
            }
        }
    }
}

// set missing strings for Moodle 1.8
if (empty($CFG->quizport_missingstrings_mdl_18)) {
    $missingstrings['mdl_18'] = array(
        'en_utf8' => array(
            'filters' => array(
                'actfilterhdr' => 'Active filters',
                'addfilter' => 'Add filter',
                'contains' => 'contains',
                'doesnotcontain' => "doesn't contain",
                'isanyvalue' => 'is any value',
                'isequalto' => 'is equal to',
                'isnotequalto' => 'is not equal to',
                'newfilter' => 'New filter',
                'removeall' => 'Remove all filters',
                'removeselected' => 'Remove selected',
                'startswith' => 'starts with',
                'selectlabel' => '$a->label $a->operator $a->value',
                'textlabel' => '$a->label $a->operator $a->value',
                'endswith' => 'ends with'
            ),
            'quiz' => array(
                'reviewoptionsheading' => 'Review options'
            )
        )
    );
}

?>