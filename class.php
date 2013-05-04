<?php // $Id$

// get main Moodle libraries and configuration settings
if (isset($CFG) && (defined('MOODLE_INTERNAL') || function_exists('qualified_me'))) {
    // do nothing - everything is already set up
} else {
    require_once('../../config.php');
}
require_once($CFG->dirroot.'/mod/quizport/legacy.php');

if (! defined('QUIZPORT_PAGEID')) {
    // Moodle >= 2.0, set pageid and pageclass from $SCRIPT
    //   $SCRIPT: /mod/quizport/view.php
    //   QUIZPORT_PAGEID: mod-quizport-view
    //   QUIZPORT_PAGECLASS: mod-quizport
    $str = str_replace('/', '-', substr($GLOBALS['SCRIPT'], 1, -4));
    define('QUIZPORT_PAGEID', $str);
    if ($pos = strrpos($str, '-')) {
        $str = substr($str, 0, $pos);
    }
    define('QUIZPORT_PAGECLASS', $str);
    unset($str, $pos);
}

// do QuizPort initialization if this is a QuizPort page
// i.e. don't initalize for backup, restore or upgrade
if (QUIZPORT_PAGECLASS=='mod-quizport') {

    // initialize main global objects that the QuizPort module maintains
    $course       = null;
    $coursemodule = null;
    $quizport     = null;
    $unit         = null;
    $quiz         = null;
    $condition    = null;
    $unitgrade    = null;
    $unitattempt  = null;
    $quizscore    = null;
    $quizattempt  = null;
    $block        = null;

    $courseid       = optional_param('courseid', 0, PARAM_INT);
    $coursemoduleid = optional_param('cm', 0, PARAM_INT);
    $quizportid     = optional_param('qp', 0, PARAM_INT);
    $unitid         = optional_param('unitid', 0, PARAM_INT);
    $quizid         = optional_param('quizid', 0, PARAM_INT);
    $conditionid    = optional_param('conditionid', 0, PARAM_INT);
    $unitgradeid    = optional_param('unitgradeid', 0, PARAM_INT);
    $unitattemptid  = optional_param('unitattemptid', 0, PARAM_INT);
    $quizscoreid    = optional_param('quizscoreid', 0, PARAM_INT);
    $quizattemptid  = optional_param('quizattemptid', 0, PARAM_INT);
    $blockid        = optional_param('blockid', 0, PARAM_INT);

    if ($quizattemptid==0) {
        $quizattemptid = optional_param('qedoc_quizattemptid', 0, PARAM_INT);
    }

    //get main id for this page
    switch (QUIZPORT_PAGEID) {
        case 'mod-quizport-editcolumnlists':
        case 'mod-quizport-editunits':
        case 'mod-quizport-index':
        case 'backup-backup':
            $courseid = optional_param('id', 0, PARAM_INT);
            break;
        case 'mod-quizport-editquizzes':
        case 'mod-quizport-report':
        case 'mod-quizport-view':
            $coursemoduleid = optional_param('id', 0, PARAM_INT);
            break;
        case 'mod-quizport-editquiz':
            $quizid = optional_param('id', 0, PARAM_INT);
            break;
        case 'mod-quizport-attempt':
            $quizattemptid = optional_param('id', 0, PARAM_INT);
            break;
        case 'mod-quizport-editcondition':
            $conditionid = optional_param('id', 0, PARAM_INT);
            break;
        case 'mod-quizport-mod':
            $coursemoduleid = optional_param('coursemodule', 0, PARAM_INT);
            break;
        default:
            print_error('error_unrecognizedpageid', 'quizport', '', QUIZPORT_PAGEID);
    }

    // define main select criteria
    $select = array();
    $allowquizid = false;
    switch (true) {
        case $quizattemptid>0: $select[] = "qqa.id=$quizattemptid"; break;
        case $quizscoreid>0: $select[] = "qqs.id=$quizscoreid"; break;
        case $unitattemptid>0: $select[] = "qua.id=$unitattemptid"; $allowquizid = true; break;
        case $unitgradeid>0: $select[] = "qug.id=$unitgradeid"; $allowquizid = true; break;
        case $conditionid>0: $select[] = "qc.id=$conditionid"; break;
        case $quizid>0: $allowquizid = true; break;
        case $unitid>0: $select[] = "qu.id=$unitid"; break;
        case $quizportid>0: $select[] = "q.id=$quizportid"; break;
        case $coursemoduleid>0: $select[] = "cm.id=$coursemoduleid"; break;
        case $courseid>0: $select[] = "c.id=$courseid"; break;
    }
    if ($allowquizid && $quizid>0) {
        $select[]= "qq.id=$quizid";
    }

    // ====================
    // Define join criteria
    // ====================
    // the most junior, i.e furthest to the right in the following list,
    // record that is required will be joined to all its parent records
    // User tables:
    //     unit_grades, unit_attempts, quiz_scores, quiz_attempts
    // Quiz tables:
    //     course, course_modules, quizport, quizport_units, quizport_quizzes, quizport_conditions
    // In addition ...
    //     unit_grades/attempts will be joined to their corresponding unit record
    //     quiz_scores/attempts will be joined to their corresponding quiz record
    //     the $tablesnames array stores the mapping: $tablename => $tablealias
    $joinquiz = false;
    $joinunit = false;
    $tablenames = array();

    switch (true) {
        case $quizattemptid>0:
            $tablenames['quizport_quiz_attempts'] = 'qqa';
            $select[] = 'qqa.quizid=qqs.quizid AND qqa.unumber=qqs.unumber AND qqa.userid=qqs.userid';
        case $quizscoreid>0:
            $tablenames['quizport_quiz_scores'] = 'qqs';
            $select[] = 'qqs.quizid=qq.id AND qq.unitid=qua.unitid AND qqs.unumber=qua.unumber AND qqs.userid=qua.userid';
            $joinquiz = true;
        case $unitattemptid>0:
            $tablenames['quizport_unit_attempts'] = 'qua';
            $select[] = 'qua.unitid=qu.id AND qua.userid=qug.userid';
        case $unitgradeid>0:
            $tablenames['quizport_unit_grades'] = 'qug';
            $select[] = 'qug.parenttype=qu.parenttype AND qug.parentid=qu.parentid';
        case $quizportid>0:
        case $coursemoduleid>0:
            $joinunit = true;
    }

    switch (true) {
        case $conditionid>0:
            $tablenames['quizport_conditions'] = 'qc';
            $select[] = 'qc.quizid=qq.id';
        case $joinquiz:
        case $quizid>0:
            $tablenames['quizport_quizzes'] = 'qq';
            $select[] = 'qq.unitid=qu.id';
        case $joinunit:
        case $unitid>0:
            $tablenames['quizport_units'] = 'qu';
            $select[] = 'qu.parenttype=0 AND qu.parentid=q.id';
            $tablenames['quizport'] = 'q';
            $select[] = 'q.id=cm.instance AND cm.module=m.id';
            $tablenames['modules'] = 'm';
            $select[] = "m.name='quizport'";
            $tablenames['course_modules'] = 'cm';
            $select[] = 'cm.course=c.id';
            if ($CFG->majorrelease<=1.4) {
                $select[] = 'cm.deleted=0';
            }
        case $courseid>0:
            $tablenames['course'] = 'c';
    }

    // define names and aliases for tables and fields
    $tables = array();
    $fields = array();
    foreach ($tablenames as $tablename=>$tablealias) {
        $tables[] = '{'.$tablename.'} '.$tablealias;
        if ($columns = $DB->get_columns($tablename)) {
            foreach ($columns as $column) {
                $field = strtolower($column->name);
                $fields[] = $tablealias.'.'.$field.' AS '.$tablealias.'_'.$field;
            }
        }
    }

    // check we had some sensible input
    if (empty($select) || empty($tables)) {
        print_error('error_noinputparameters', 'quizport');
    }
    if (empty($fields)) {
        print_error('error_nodatabaseinfo', 'quizport');
    }

    // get the information from the database
    if (! $record = $DB->get_record_sql('SELECT '.implode(',', $fields).' FROM '.implode(',', $tables).' WHERE '.implode(' AND ', $select))) {
        print_error('error_norecordsfound', 'quizport');
    }

    // distribute the database information into the relevant objects
    foreach(get_object_vars($record) as $field=>$value) {
        list($tablealias, $field) = explode('_', $field, 2);
        switch ($tablealias) {
            case 'qqa': $quizattempt->$field = $value; break;
            case 'qqs': $quizscore->$field = $value; break;
            case 'qua': $unitattempt->$field = $value; break;
            case 'qug': $unitgrade->$field = $value; break;
            case 'qc': $condition->$field = $value; break;
            case 'qq': $quiz->$field = $value; break;
            case 'qu': $unit->$field = $value; break;
            case 'q': $quizport->$field = $value; break;
            case 'cm': $coursemodule->$field = $value; break;
            case 'm': $module->$field = $value; break;
            case 'c': $course->$field = $value; break;
        }
    }
    if ($CFG->majorrelease>=2.0) {
        // mimic get_coursemodule_from_id() and get_coursemodule_from_instance()
        if ($coursemodule) {
            $coursemodule->name = $quizport->name;
            $coursemodule->modname = $module->name;
        }
    }
    // the main objects have now been set up

    // reclaim some memory
    unset($record, $tablenames, $tables, $fields, $select, $allowquizid, $joinquiz, $joinunit);

    // we should at least have a course record by now
    if (empty($course->id)) {
        print_error('error_nocourseid', 'quizport');
    }

    // require_login must come before inclusion of script libraries
    // so that correct language is set for calls to get_string() in the
    // included libraries
    require_login($course->id, true, $coursemodule);

    // check capabilities
    switch (QUIZPORT_PAGEID) {
        case 'mod-quizport-view':
            if (has_capability('mod/quizport:attempt', get_context_instance(CONTEXT_MODULE, $coursemodule->id), $USER->id)) {
                // let this user through
            } else {
                require_capability('mod/quizport:view', get_context_instance(CONTEXT_MODULE, $coursemodule->id), $USER->id);
            }
            break;
        case 'mod-quizport-report':
            if (has_capability('mod/quizport:viewreports', get_context_instance(CONTEXT_MODULE, $coursemodule->id), $USER->id)) {
                // let this user through
            } else {
                require_capability('mod/quizport:reviewmyattempts', get_context_instance(CONTEXT_MODULE, $coursemodule->id), $USER->id);
            }
            break;
        case 'mod-quizport-editcondition':
        case 'mod-quizport-editquiz':
        case 'mod-quizport-editquizzes':
            require_capability('mod/quizport:manage', get_context_instance(CONTEXT_MODULE, $coursemodule->id), $USER->id);
            break;
        case 'mod-quizport-index':
            require_capability('moodle/course:view', get_context_instance(CONTEXT_COURSE, $course->id), $USER->id);
            break;
        case 'mod-quizport-editcolumnlists':
            if (isset($coursemodule->id)) {
                require_capability('mod/quizport:manage', get_context_instance(CONTEXT_MODULE, $coursemodule->id), $USER->id);
            } else {
                require_capability('moodle/course:manageactivities', get_context_instance(CONTEXT_COURSE, $course->id), $USER->id);
            }
            break;
    }
} // end if $require_login

// get QuizPort libraries
require_once($CFG->dirroot.'/mod/quizport/lib.local.php');

/**
 * Class that models the behavior of a quizport instance
 *
 * @author  Gordon Bateson
 * @package quizport
 */


class mod_quizport {
    var $userid;        // cache $USER->userid
    var $realuserid;    // cache $USER->realuser

    var $courserecord = null;
    var $modulerecord = null;
    var $activityrecord = null;

    var $coursecontext = null;
    var $modulecontext = null;

    var $quizportid;    // cache $quizport->id
    var $quizport = null; // alias to $this->activitymodule

    var $mycourses = null;
    var $myquizports = null;
    var $quizports = null;

    var $unitid = 0;
    var $unit = null;
    var $units = null;

    var $unitgradeid = 0;
    var $unitgrade = null;
    var $unitgrades = null;

    var $unitattemptid = 0;
    var $unitattempt = null;
    var $unitattempts = null;

    var $lastunitattemptid = 0;
    var $lastunitattempt = null;
    var $lastunitattempttime = 0;

    var $quizid = 0;
    var $quiz = null;
    var $quizzes = null;

    var $quizattemptid = 0;
    var $quizattempt = null;
    var $quizattempts = null;

    var $lastquizattemptid = 0;
    var $lastquizattempt = null;
    var $lastquizattempttime = 0;

    var $quizscoreid = 0;
    var $quizscore = null;
    var $quizscores = null;

    var $availablequizid = 0; // id of first unattempted available quiz
    var $availablequizids = null; // id of all available quizzes

    // array of arrays (by quizid)
    var $cache_quizattempts = array();
    var $cache_quizattemptsusort = array();

    var $conditionid = 0;
    var $condition = null;
    var $conditiontype = 0;

    var $cache_preconditions = array();
    var $cache_postconditions = array();
    var $cache_available_quiz = array();

    // widths for block columns (0 : no column is displayed)
    var $leftcolumnwidth  = 0;
    var $rightcolumnwidth = 0;

    var $unumber = 0; // unit attempt number
    var $qnumber = 0; // quiz attempt number

     // settings for allowfreeaccess
    var $maxunitattemptgrade = null;
    var $unitcompleted = null;

    var $time; // the time this page was displayed
    var $action = ''; // 'add', 'update', 'delete', 'deleteall', 'datasubmitted'

    // page display settings
    var $forcetab = false;
    var $pagehastabs = true;
    var $pagehasreporttab = false;
    var $pagehascolumns = true;
    var $pagehasheader = true;
    var $pagehasfooter = true;
    var $columnlisttype = '';
    var $columnlistid = '';

    // empty table cell values
    var $nonumber = '-';
    var $notext = '&nbsp;';

   // constructor function
    function mod_quizport() {
        global $CFG, $USER,
            $course, $coursemodule, $quizport, $unit, $quiz, $condition,
            $unitgrade, $unitattempt, $quizscore, $quizattempt, $block
        ;

        // setup some useful aliases

        $this->userid = $USER->id;
        if (isset($USER->realuser)) {
            $this->realuserid = $USER->realuser;
        }

        if (isset($course)) {
            $this->courserecord = &$course;
            $this->coursecontext = get_context_instance(CONTEXT_COURSE, $this->courserecord->id);
        }
        if (isset($coursemodule)) {
            $this->modulerecord = &$coursemodule;
            $this->modulecontext = get_context_instance(CONTEXT_MODULE, $this->modulerecord->id);
            // fields required for navigation breadcrumbs
            $this->modulerecord->modname = 'quizport';
            $this->modulerecord->name = $quizport->name;
        }
        if (isset($quizport)) {
            $this->quizport = &$quizport;
            $this->quizportid = $this->quizport->id;
            $this->activityrecord = &$quizport;
        }
        if (isset($unit)) {
            $this->unit = &$unit;
            $this->unitid = $unit->id;
        }
        if (isset($quiz)) {
            $this->quiz = &$quiz;
            $this->quizid = $quiz->id;
            $this->get_quiz();
        }
        if (isset($condition)) {
            $this->condition = &$condition;
            $this->conditionid = $condition->id;
        }

        if ($this->coursecontext) {
            $has_capability_grade = has_capability('mod/quizport:grade', $this->coursecontext);
        } else if ($this->modulecontext) {
            $has_capability_grade = has_capability('mod/quizport:grade', $this->modulecontext);
        } else {
            // admin is upgrading site
            $has_capability_grade = false;
        }

        if (isset($unitgrade)) {
            if ($unitgrade->userid==$this->userid || $has_capability_grade) {
                $this->unitgrade = &$unitgrade;
                $this->unitgradeid = $unitgrade->id;
            }
        }

        if (isset($unitattempt)) {
            if ($unitattempt->userid==$this->userid || $has_capability_grade) {
                $this->unitattempt = &$unitattempt;
                $this->unitattemptid = $unitattempt->id;
                $this->unumber = $unitattempt->unumber;
                $this->get_unitgrade();
            }
        } else {
            $this->unumber = optional_param('unumber', 0, PARAM_INT);
        }
        if (isset($quizscore)) {
            if ($quizscore->userid==$this->userid || $has_capability_grade) {
                $this->quizscore = &$quizscore;
                $this->quizscoreid = $quizscore->id;
                $this->unumber = $quizscore->unumber;
                $this->get_unitattempt();
                $this->get_unitgrade();
            }
        }
        if (isset($quizattempt)) {
            if ($quizattempt->userid==$this->userid || $has_capability_grade) {
                $this->quizattempt = &$quizattempt;
                $this->quizattemptid = $quizattempt->id;
                $this->unumber = $this->quizattempt->unumber;
                $this->qnumber = $this->quizattempt->qnumber;
                $this->get_quizscore();
                $this->get_unitattempt();
                $this->get_unitgrade();
            }
        } else {
            $this->qnumber = optional_param('qnumber', 0, PARAM_INT);
        }

        // conditiontype : 1=pre-condition, 2=post-condition
        $this->conditiontype = optional_param('conditiontype', 0, PARAM_INT);

        // columnlisttype : 'unit' or 'quiz'
        if ($this->columnlisttype=='') {
            $this->columnlisttype = optional_param('columnlisttype', '', PARAM_ALPHANUM);
        }
        if ($this->columnlisttype) {
            $default = get_user_preferences('quizport_'.$this->columnlisttype.'_columnlistid', 'default');
            $this->columnlistid = optional_param('columnlistid', $default, PARAM_ALPHANUM);
            if ($this->columnlistid != $default) {
                set_user_preference('quizport_'.$this->columnlisttype.'_columnlistid', $this->columnlistid);
            }
        }

        // sort order increment (editquizzes.php)
        $default = get_user_preferences('quizport_sortorderincrement', 1);
        $this->sortorderincrement = optional_param('sortorderincrement', $default, PARAM_INT);
        if ($this->sortorderincrement != $default) {
            set_user_preference('quizport_sortorderincrement', $this->sortorderincrement);
        }

        if ($this->forcetab) {
            // report.php and editquizzes.php
            $this->tab = $this->forcetab;
        } else {
            $this->tab = optional_param('tab', 'info', PARAM_ALPHA);
        }

        $this->mode = optional_param('mode', '', PARAM_ALPHA);
        $this->action = optional_param('action', '', PARAM_ALPHA);
        $this->inpopup = optional_param('inpopup', 0, PARAM_BOOL);

        if ($this->action=='') {
            $actions = array(
                'add', 'update', 'delete', 'deleteall', 'deleteconfirmed', 'deletecancelled'
            );
            foreach ($actions as $action) {
                if (optional_param($action, '', PARAM_RAW)) {
                    $this->action = $action;
                    break;
                }
            }
        }

        if ($CFG->majorrelease<=1.4) {
            $this->pixpath = $CFG->legacypixpath;
        } else if ($CFG->majorrelease<=1.9) {
            $this->pixpath = $CFG->pixpath;
        } else {
            $this->pixpath = $CFG->wwwroot.'/pix';
        }

        // store the time this page was created
        $this->time  = time();
    }

    function init_pageblocks(&$pageblocks) {
        global $CFG, $PAGE, $THEME;

        $hasleftcolumn = false;
        $hasrightcolumn = false;

        if (empty($CFG->showblocksonmodpages)) {
            $this->pagehascolumns = false;
        }

        if ($this->pagehascolumns) {
            if (blocks_have_content($pageblocks, BLOCK_POS_LEFT)) {
                $hasleftcolumn = true;
            }
            if (blocks_have_content($pageblocks, BLOCK_POS_RIGHT)) {
                $hasrightcolumn = true;
            }
            if ($this->unumber && $this->quizid) {
                // no columns on a quiz page, because the quiz page needs extra navigation breadcrumbs
                // which disables the display of the edit Buttons
            } else {
                if ($PAGE->user_is_editing()) {
                    $hasleftcolumn = true;
                    $hasrightcolumn = true;
                }
            }
        }

        $this->leftcolumnwidth  = 0;
        $this->rightcolumnwidth = 0;

        if ($hasleftcolumn) {
            $this->leftcolumnwidth  = bounded_number(180, blocks_preferred_width($pageblocks[BLOCK_POS_LEFT]), 210);
        }
        if ($hasrightcolumn) {
            $this->rightcolumnwidth = bounded_number(180, blocks_preferred_width($pageblocks[BLOCK_POS_RIGHT]), 210);
        }

        $this->pageblocks = &$pageblocks;
    }

    // print parts of the standard 3-column page

    function print_header($title='', $navlinks=false, $bodyattributes='', $headcontent='', $xmldeclaration='') {
        global $CFG, $OUTPUT, $PAGE, $QUIZPORT, $THEME, $USER;
        if ($this->pagehasheader) {
            if ($bodyattributes=='' && $this->unit->showpopup) {
                if (! $this->inpopup) {
                    $strpopupblockerwarning = get_string('popupblockerwarning', 'quiz');
                    $bodyattributes .= ' onload="'."popupchecker('$strpopupblockerwarning')".'"';
                }
            }
            // Note: we don't print xml declaration if xmlstrictheaders is enabled
            if ($xmldeclaration && empty($CFG->xmlstrictheaders)) {
                print $xmldeclaration;
            }
            if ($CFG->majorrelease>=2.0) {
                $buttons = '&nbsp;';
                if (empty($navlinks)) {
                    if ($PAGE->user_allowed_editing()) {
                        $buttons = '<table><tr><td>'.update_module_button($PAGE->cm->id, $PAGE->course->id, get_string('modulename', 'quizport')).'</td>';
                        if (!empty($CFG->showblocksonmodpages)) {
                            if ($PAGE->user_is_editing()) {
                                $edit = 'off';
                            } else {
                                $edit = 'on';
                            }
                            $buttons .= '<td><form '.$CFG->frametarget.' method="get" action="view.php"><div>'.
                                '<input type="hidden" name="id" value="'.$PAGE->cm->id.'" />'.
                                '<input type="hidden" name="edit" value="'.$edit.'" />'.
                                '<input type="submit" value="'.get_string('blocksedit'.$edit).'" /></div></form></td>';
                        }
                        $buttons .= '</tr></table>';
                    }
                    $navlinks = array();
                }
                $navigation = build_navigation($navlinks, $PAGE->cm);
                if (isset($OUTPUT)) {
                    $PAGE->set_title($title);
                    $PAGE->set_heading($PAGE->course->fullname);
                    $PAGE->set_button($buttons);
                    $header = $OUTPUT->header($navigation, navmenu($PAGE->course, $PAGE->cm));
                    if ($headcontent) {
                        $header  = preg_replace('/(\s*)<\/head>/s', '\\1'.$headcontent.'\\0', $header ,1);
                    }
                    if ($bodyattributes) {
                        $header  = preg_replace('/(<body[^>]*)(>)/s', '\\1 '.$bodyattributes.'\\2', $header ,1);
                    }
                    print $header;
                } else {
                    print_header($title, $PAGE->course->fullname, $navigation, '', $headcontent, true, $buttons, navmenu($PAGE->course, $PAGE->cm), false, $bodyattributes);
                }
            } else if ($this->inpopup && isset($this->unit) && $this->unit->showpopup) {
                $PAGE->init_full();
                if (strpos($this->unit->popupoptions, 'MOODLEHEADER')===false) {
                    $title = '';
                    $navmenu = '';
                    $heading = '';
                } else {
                    $title = str_replace('%fullname%', format_string($PAGE->activityrecord->name), $title);
                    $navmenu = navmenu($PAGE->courserecord, $PAGE->modulerecord);
                    $heading = $PAGE->courserecord->fullname;
                }
                $buttons = array();
                if (strpos($this->unit->popupoptions, 'MOODLENAVBAR')===false) {
                    $navigation = '';
                } else {
                    if (empty($navlinks)) {
                         if ($PAGE->user_allowed_editing()) {
                            $buttons[] = update_module_button($PAGE->modulerecord->id, $PAGE->courserecord->id, get_string('modulename', $PAGE->activityname));
                            if (! empty($CFG->showblocksonmodpages)) {
                                if ($PAGE->user_is_editing()) {
                                    $edit = 'off';
                                } else {
                                    $edit = 'on';
                                }
                                $buttons[] = '<form '.$CFG->frametarget.' method="get" action="view.php"><div>'.
                                    '<input type="hidden" name="id" value="'.$PAGE->modulerecord->id.'" />'.
                                    '<input type="hidden" name="edit" value="'.$edit.'" />'.
                                    '<input type="submit" value="'.get_string('blocksedit'.$edit).'" /></div></form>';
                            }
                        }
                       $navlinks = array();
                    }
                    $navigation = build_navigation($navlinks, $PAGE->modulerecord);
                }
                if (count($buttons)) {
                    $buttons = '<table><tr><td>'.implode('</td><td>', $buttons).'</td></tr></table>';
                } else {
                    $buttons = '';
                }
                print_header($title, $heading, $navigation, '', $headcontent, true, $buttons, $navmenu, false, $bodyattributes);
            } else if ($CFG->majorrelease==1.9) {
                // use the standard "print_header()" function in the "page_generic_activity" class (lib/pagelib.php)
                $PAGE->print_header($title, $navlinks, $bodyattributes, $headcontent);
            } else {
                quizport_print_header($title, $navlinks, $bodyattributes, $headcontent);
            }
        } else {
            // print simple page header
            if ($CFG->majorrelease<=1.4) {
                $meta = '<link rel="stylesheet" type="text/css" href="'.$CFG->wwwroot.'/mod/quizport/styles.php" />';
                if ($CFG->majorrelease<=1.2) {
                    $THEME->body .= '"'.' class="mod-quizport" id="'.QUIZPORT_PAGEID;
                } else {
                    $bodyattributes .= ' class="mod-quizport" id="'.QUIZPORT_PAGEID.'"';
                }
                print_header('', '', '', '', $meta, true, '&nbsp;', '', false, $bodyattributes);
            } else {
                print_header();
            }
        }
    }

    function print_tabs($return=false) {
        global $CFG;

        $tabs = '';
        $js = '';
        if ($this->pagehastabs) {
            $tabrows = array();
            $inactive = array();
            $activated = array();

            if ($this->unitid) {
                $tabrow  = array();
                $inactive[] = $this->tab;
                $activated[] = $this->tab;

                // these parameters are always added to the link in each tab
                $params = array('coursemoduleid'=>$this->modulerecord->id, 'unitid'=>0, 'unumber'=>0, 'quizid'=>0, 'qnumber'=>0, 'unitgradeid'=>0, 'unitattemptid'=>0, 'quizscoreid'=>0, 'quizattemptid'=>0, 'mode'=>'');

                if (has_capability('mod/quizport:view', $this->modulecontext)) {
                    $tabrow[] = new tabobject('info', $this->format_url('view.php', 'coursemoduleid', $params, array('tab'=>'info')), get_string('info', 'quiz'));
                }
                if (has_capability('mod/quizport:viewreports', $this->modulecontext)) {
                    $tabrow[] = new tabobject('report', $this->format_url('report.php', 'coursemoduleid', $params, array('tab'=>'report')), get_string('results', 'quiz'));
                }
                if (has_capability('mod/quizport:preview', $this->modulecontext)) {
                    $tabrow[] = new tabobject('preview', $this->format_url('view.php', 'coursemoduleid', $params, array('unumber'=>'-1', 'tab'=>'preview')), get_string('preview', 'quiz'));
                }
                if (has_capability('mod/quizport:manage', $this->modulecontext)) {
                    $tabrow[] = new tabobject('edit', $this->format_url('editquizzes.php', 'coursemoduleid', $params, array('tab'=>'edit')), get_string('editquizzes', 'quizport'));
                }
                if ($this->inpopup && $this->unit->showpopup && strpos($this->unit->popupoptions, 'MOODLEHEADER')===false && has_capability('moodle/course:manageactivities', $this->modulecontext)) {
                    // we are in a popup with no header, so add a tab to edit this QuizPort
                    if ($CFG->majorrelease<=1.7) {
                        $updatescript = 'mod.php';
                    } else {
                        $updatescript = 'modedit.php';
                    }
                    $params = array(
                        'update'=>$this->modulerecord->id, 'return'=>1,
                        'columnlistid'=>'', 'columnlisttype'=>'', 'unitid'=>0, 'inpopup'=>0
                    );
                    $tabrow[] = new tabobject(
                        'updatemodule',
                        $this->format_url($CFG->wwwroot.'/course/'.$updatescript, '', $params),
                        get_string('updatethis', '', get_string('modulename', 'quizport'))
                    );
                    $js = ''
                        .'<script type="text/javascript">'."\n"
                        .'//<![CDATA['."\n"
                        ."var links = null;\n"
                        ."var divs= document.getElementsByTagName('div');\n"
                        ."if (divs) {\n"
                        ."    var i_max = divs.length;\n"
                        ."    for (var i=0; i<i_max; i++) {\n"
                        ."        if (divs[i].className=='tabtree') {\n"
                        ."            links = divs[i].getElementsByTagName('a');\n"
                        ."            break;\n"
                        ."        }\n"
                        ."    }\n"
                        ."    divs = null;\n"
                        ."}\n"
                        ."if (links) {\n"
                        ."    var i_max = links.length;\n"
                        ."    for (var i=0; i<i_max; i++) {\n"
                        ."        if (links[i].href && links[i].href.indexOf('".$updatescript."')>=0) {\n"
                        ."            links[i].onclick = quizport_update_module_onclick;\n"
                        ."            break;\n"
                        ."        }\n"
                        ."    }\n"
                        ."    links = null;\n"
                        ."}\n"
                        ."function quizport_update_module_onclick(){\n"
                        ."    if (window.opener && ! opener.closed) {\n"
                        ."        opener.location.href = this.href;\n"
                        ."    } else {\n"
                        ."        window.open(this.href);\n"
                        ."    }\n"
                        ."    self.close();\n"
                        ."    return false;\n"
                        ."}\n"
                        .'//]]>'."\n"
                        .'</script>'."\n"
                    ;
               }
                if (count($tabrow)==1) { //  && ($this->tab=='' || $tabrow[0]->id==$this->tab)
                    // don't show this tab row since there is only one tab
                } else {
                    $tabrows[] = $tabrow;
                }

                if ($modes = $this->get_modes()) {
                    if (! array_key_exists($this->mode, $modes)) {
                        $this->mode = reset(array_keys($modes));
                    }
                    $inactive[] = $this->mode;
                    $activated[] = $this->mode;

                    if (count($modes) > 1) {
                        $tabrow  = array();
                        foreach ($modes as $mode=>$str) {
                            $tabrow[] = new tabobject($mode, $this->format_url('report.php', 'coursemoduleid', array('mode'=>$mode)), get_string($str[0], $str[1]));
                        }
                        if (count($tabrow)) {
                            $tabrows[] = $tabrow;
                        }
                    }
                }
                $tabs = print_tabs($tabrows, $this->tab, $inactive, $activated, true);
            }
        }
        if ($return) {
            return $tabs.$js;
        } else {
            print $tabs.$js;
        }
    }

    function get_modes() {
        return false;
    }

    function print_heading() {
        if (isset($this->activityrecord)) {
            print_heading(format_string($this->activityrecord->name));
        } else if (isset($this->courserecord)) {
            print_heading(format_string($this->courserecord->fullname).':'); // shortname
        }
    }

    function print_main_table_start() {
        if ($this->pagehascolumns) {
            print '<table id="layout-table"><tr>';
        }
    }
    function print_left_column() {
        global $PAGE;
        if ($this->pagehascolumns && $this->leftcolumnwidth) {
            print '<td style="width: '.$this->leftcolumnwidth.'px;" id="left-column">';
            print_container_start();
            blocks_print_group($PAGE, $this->pageblocks, BLOCK_POS_LEFT);
            print_container_end();
            print '</td>';
        }
    }
    function print_middle_column_start() {
        if ($this->pagehascolumns) {
            print '<td id="middle-column">';
        } else {
            print '<div id="middle-column">';
        }
        print_container_start();
    }
    function print_content() {
        debugging(get_class($this).' object has no print_content method', DEBUG_DEVELOPER);
    }
    function print_middle_column_finish() {
        print_container_end();
        if ($this->pagehascolumns) {
            print '</td>';
        } else {
            print '</div>';
        }
    }
    function print_right_column() {
        global $PAGE;
        if ($this->pagehascolumns && $this->rightcolumnwidth) {
            print '<td style="width: '.$this->rightcolumnwidth.'px;" id="right-column">';
            print_container_start();
            blocks_print_group($PAGE, $this->pageblocks, BLOCK_POS_RIGHT);
            print_container_end();
            print '</td>';
        }
    }
    function print_main_table_finish() {
        if ($this->pagehascolumns) {
            print '</tr></table>';
        }
    }

    function print_footer($course=false) {
        global $CFG;
        if ($this->inpopup && isset($this->unit) && $this->unit->showpopup) {
            if (strpos($this->unit->popupoptions, 'MOODLEBUTTON')!==false) {
                close_window_button();
            }
            if (strpos($this->unit->popupoptions, 'MOODLEFOOTER')===false) {
                $course = 'empty';
            }
        }
        if (! $this->pagehasfooter) {
            $course = 'empty';
        }
        if ($CFG->majorrelease<=1.7 && $course==='empty') {
            print "\n</div>\n</body>\n</html>";
        } else if ($course) {
            print_footer($course);
        } else {
            print_footer($this->courserecord);
        }
    }

    function print_error($error) {
        $this->print_header();
        print_box($error, 'generalbox', 'notice');
        $this->print_footer();
        exit;
    }

    function print_page() {
        global $CFG;

        $this->print_header();

        if ($CFG->majorrelease<=1.9) {
            $this->print_main_table_start();
            $this->print_left_column();
            $this->print_middle_column_start();
        }

        $this->print_tabs();
        $this->print_heading();
        $this->print_content();

        if ($CFG->majorrelease<=1.9) {
            $this->print_middle_column_finish();
            $this->print_right_column();
            $this->print_main_table_finish();
        }

        $this->print_footer();
    }

    function print_page_quick($text='', $button='', $link='') {
        $this->print_header();
        print_box_start('generalbox', 'notice');

        print '<p align="center">'.$text.'</p>'."\n";
        switch ($button) {
            case 'continue':
                print_continue($link);
                break;
            case 'close':
                close_window_button('closewindow');
                break;
            default:
                $this->print_single_button($link, '', $button);
        }

        print_box_end();
        $this->print_js();
        $this->print_footer('empty');
    }

    function print_js() {
        // subclasses can print their own javascript if they want
    }

    function format_columnlists($type, $return_js=false) {
        $str = '';
        $name = 'columnlistid';

        if ($type=='unit') {
            $params = array('id'=>$this->courserecord->id, 'columnlistid'=>0);
            $str .= $this->print_form_start('editunits.php', $params, false, true);
        } else {
            $params = array('id'=>$this->modulerecord->id, 'columnlistid'=>0);
            $str .= $this->print_form_start('editquizzes.php', $params, false, true);
        }

        $str .= '<b>'.get_string('columnlists', 'quizport').':</b> ';

        $options = array (
            'default' => get_string('default'),
            'general' => get_string('general', 'form'),
            'display' => get_string('display', 'form'),
            'accesscontrol' => get_string('accesscontrol', 'lesson'),
            'assessment' => get_string('assessment', 'quizport')
        );
        if ($type=='quiz') {
            $options['conditions'] = get_string('conditions', 'quizport');
        }
        $options['all'] = get_string('all');
        $options = array_merge($options, $this->get_columnlists($type));

        $default = get_user_preferences('quizport_'.$type.'_'.$name, 'default');
        $selected = optional_param($name, $default, PARAM_ALPHANUM);

        if ($return_js) {
            $button_script = '';
            $button_style = 'display: none;';
        } else {
            $button_script = ''
                .'<script type="text/javascript">'."\n"
                .'//<![CDATA['."\n"
                .'    document.getElementById("noscript'.$name.'").style.display = "none";'."\n"
                .'//]]>'."\n"
                .'</script>'."\n"
            ;
            $button_style = 'display: inline;';
        }
        $str .= ''
            .choose_from_menu($options, $name, $selected, '', 'this.form.submit()', 0, true)."\n"
            .'<div id="noscript'.$name.'" style="'.$button_style.'">'
            .'<input type="submit" value="'.get_string('go').'" /></div>'."\n".$button_script
        ;

        $params = array('courseid'=>$this->courserecord->id, 'columnlisttype'=>$type);
        if ($type=='quiz') {
            $params['cm'] = $this->modulerecord->id;
        }
        $str .= $this->print_commands(
            array('update'), 'editcolumnlists.php', 'courseid', $params, array('width'=>300, 'height'=>600), true
        );

        $str .= $this->print_form_end(true);

        if ($return_js) {
            return addslashes_js($str);
        } else {
            return '<div id="quizport_columnlists">'.$str.'</div>'."\n";
        }
    }

    function get_columnlists($type, $return_columns=false) {
        $columnlists = array();
        if ($preferences = get_user_preferences()) {
            foreach ($preferences as $name => $value) {
                if (preg_match('/^quizport_'.$type.'_columnlist_(\d+)$/', $name, $matches)) {
                    if ($return_columns) {
                        // $columnlistid => array($column1, $column2, ...)
                        $columnlists[$matches[1]] = explode(',', substr($value, strpos($value, ':')+1));
                    } else {
                        // $columnlistid => $columnlistname
                        $columnlists[$matches[1]] = substr($value, 0, strpos($value, ':'));
                    }
                }
            }
        }
        return $columnlists;
    }

    function print_form_start($quizportscriptname, $params, $more_params=false, $return=false, $attributes=array()) {
        global $CFG;

        $str = '<form';
        if (empty($attributes['method'])) {
            $str .= ' method="post"';
        }
        if (empty($attributes['action'])) {
            $str .= ' action="'.$CFG->wwwroot.'/mod/quizport/'.$quizportscriptname.'"';
        }
        foreach ($attributes as $key => $value) {
            $str .= ' '.$key.'="'.$value.'"';
        }
        $str .= '>'."\n";

        $all_params = $this->merge_params($params, $more_params);

        $fieldset = false;
        foreach ($all_params as $name=>$value) {
            if ($value===0 || $value==='' || $value===false) {
                // do nothing
            } else {
                if (! $fieldset) {
                    // xhtml strict requires a container for the hidden input elements
                    $str .= '<fieldset style="display:none">'."\n";
                    $fieldset = true;
                }
                $str .= '<input type="hidden" name="'.$name.'" value="'.$value.'" />'."\n";
            }
        }
        if ($fieldset) {
            $str .= '</fieldset>'."\n";
        }
        // xhtml strict requires a container for the contents of the <form>
        $str .= '<div>'."\n";
        if ($return) {
            return $str;
        } else {
            print $str;
        }
    }

    function print_form_end($return=false) {
        $str = "</div>\n</form>\n";
        if ($return) {
            return $str;
        } else {
            print $str;
        }
    }

    function format_url($url, $id, $params, $more_params=false) {
        global $CFG;

        static $ampersand;
        if (! isset($ampersand)) {
            if ($CFG->majorrelease<=1.4) {
                $ampersand = '&';
            } else {
                $ampersand = '&amp;';
            }
        }

        // convert relative URL to absolute URL
        if (! preg_match('/^(https?:\/)?\//', $url)) {
            $url = $CFG->wwwroot.'/mod/quizport/'.$url;
        }

        // merge parameters into a single array
        $all_params = $this->merge_params($params, $more_params);

        // rename the $id parameter, if necesary
        if ($id && isset($all_params[$id])) {
            $all_params['id'] = $all_params[$id];
            unset($all_params[$id]);
        }

        $join = '?';
        foreach ($all_params as $name=>$value) {
            if ($value) {
                $url .= $join.$name.'='.$value;
                $join = $ampersand;
            }
        }
        return $url;
    }

    function merge_params($params=false, $more_params=false) {
        $basic_params = array(
            'id' => 0, 'inpopup' => $this->inpopup,
            'tab' => $this->tab, 'mode' => $this->mode,
            'unitid' => $this->unitid, 'unumber' => $this->unumber,
            'quizid' => $this->quizid, 'qnumber' => $this->qnumber,
            'conditionid' => $this->conditionid, 'conditiontype' => $this->conditiontype,
            'unitgradeid' => $this->unitgradeid, 'unitattemptid' => $this->unitattemptid,
            'quizscoreid' => $this->quizscoreid, 'quizattemptid' => $this->quizattemptid,
            'columnlistid' => $this->columnlistid, 'columnlisttype' => $this->columnlisttype
        );
        if (! $params) {
            $params = array();
        }
        if (! $more_params) {
            $more_params = array();
        }
        $all_params = array_merge($basic_params, $params, $more_params);

        if (isset($all_params['sesskey']) && empty($all_params['sesskey'])) {
            // sesskey is not required
            unset($all_params['sesskey']);
        } else {
            // sesskey was not set, so set it
            $all_params['sesskey'] = sesskey();
        }

        // remove unnecessary parameters
        $unset = array();
        $unsetquiz = false;
        $unsetunit = false;
        switch (true) {
            case ! empty($all_params['quizattemptid']):
                $unset[] = 'quizscoreid';
                $unset[] = 'qnumber';
            case ! empty($all_params['quizscoreid']):
                $unset[] = 'unitattemptid';
                $unsetquiz = true;
            case ! empty($all_params['unitattemptid']):
                $unset[] = 'unitgradeid';
                $unset[] = 'unumber';
            case ! empty($all_params['unitgradeid']):
                $unsetunit = true;
        }
        switch (true) {
            case ! empty($all_params['conditionid']):
            case $unsetquiz:
                $unset[] = 'quizid';
            case ! empty($all_params['quizid']):
            case $unsetunit:
                $unset[] = 'unitid';
            case ! empty($all_params['unitid']):
                $unset[] = 'quizportid';
            case ! empty($all_params['quizportid']):
                $unset[] = 'coursemoduleid';
            case ! empty($all_params['coursemoduleid']):
                $unset[] = 'courseid';
        }
        foreach ($unset as $name) {
            unset($all_params[$name]);
        }
        return $all_params;
   }

    function format_commands_quiz($quiz=false) {
        if (! $quiz) {
            $quiz = &$this->quiz;
        }
        $types = array('update', 'delete');
        $params = array('quizid'=>$quiz->id, 'unitid'=>$quiz->unitid);
        return $this->print_commands($types, 'editquiz.php', 'quizid', $params, false, true);
    }

    function format_commands_unit($unit=false) {
        global $CFG;
        if (! $unit) {
            $unit = &$this->unit;
        }
        if ($CFG->majorrelease<=1.7) {
            $url = $CFG->wwwroot.'/course/mod.php';
        } else {
            $url = $CFG->wwwroot.'/course/modedit.php';
        }
        $types = array('update');
        $params = array(
            'update'=>$unit->coursemodule, 'columnlistid'=>'', 'columnlisttype'=>'', 'return'=>1
        );
        return $this->print_commands($types, $url, '', $params, false, true);
    }

    function format_commands_conditions($conditiontype, $quizid=0) {
        if (! $quizid) {
            $quizid = $this->quizid;
        }
        $conditions = $this->get_conditions($conditiontype, $quizid, false);

        $types = array('add');
        if (count($conditions) > 1) {
            $types[] = 'deleteall';
        }

        $params = array('quizid'=>$quizid, 'conditiontype'=>$conditiontype, 'conditionid'=>0, 'inpopup'=>0);
        return $this->print_commands($types, 'editcondition.php', '', $params, 'quizportpopup', true);
    }

    function format_commands_condition($condition=false) {
        if (! $condition) {
            $condition = &$this->condition;
        }
        $types = array('update', 'delete');
        $params = array('conditionid'=>$condition->id, 'inpopup'=>0);
        return $this->print_commands($types, 'editcondition.php', 'conditionid', $params, 'quizportpopup', true);
    }

    function print_commands($types, $quizportscriptname, $id, $params, $popup=false, $return=false) {
        // $types : array('add', 'update', 'delete', 'deleteall')
        // $params : array('name' => 'value') for url query string
        // $popup : true, false or array('name' => 'something', 'width' => 999, 'height' => 999)

        $commands = '<span class="commands">';
        foreach ($types as $type) {
            $commands .= $this->print_command($type, $quizportscriptname, $id, $params, $popup, $return);
        }
        $commands .= '</span>'."\n";

        if ($return) {
            return $commands;
        } else {
            print $commands;
        }
    }

    function print_command($type, $quizportscriptname, $id, $params, $popup=false, $return=false) {
        global $CFG;

        static $str;
        if (! isset($str)) {
            $str = new stdClass();
        }
        if (! isset($str->$type)) {
            $str->$type = get_string($type);
        }

        switch ($type) {
            case 'add':
                $icon = '';
                break;
            case 'edit':
            case 'update':
                $icon = $CFG->pixpath.'/t/edit.gif';
                break;
            case 'delete':
                $icon = $CFG->pixpath.'/t/delete.gif';
                break;
            case 'deleteall':
                $icon = '';
                break;
            default:
                // unknown command type !!
                return '';
        }
        if ($icon) {
            $linktext = '<img src="'.$icon.'" class="iconsmall"  alt="'.$str->$type.'" />';
        } else {
            $linktext = $str->$type;
        }

        $url = $this->format_url($quizportscriptname, $id, $params, array('action'=>$type));

        if ($popup) {
            if (is_bool($popup)) {
                $popup = array();
            } else if (is_string($popup)) {
                $popup = array('name' => $popup);
            }
            $name  = (isset($popup['name']) ? $popup['name'] : '');
            $width  = (isset($popup['width']) ? $popup['width'] : 650);
            $height = (isset($popup['height']) ? $popup['height'] : 400);
            $command = element_to_popup_window(
                // $type, $url, $name, $linktext, $height, $width, $title, $options, $return, $id, $class
                'link', $url, $name, $linktext, $height, $width, $str->$type, '', true, '', ''
            );
        } else {
            $command = '<a title="'.$str->$type.'" href="'.$url.'">'.$linktext.'</a>';
        }

        if (! $icon) {
            // add white space between text commands
            $command .= ' &nbsp; ';
        }

        if ($return) {
            return ' '.$command;
        } else {
            print ' '.$command;
        }
    }

    function format_conditions($quizid, $conditiontype, $return_intro=true, $return_js=false, $return_commands=true, $default='') {
        $str = '';

        if ($conditions = $this->get_conditions($conditiontype, $quizid, false)) {
            $li = array();
            foreach ($conditions as $condition) {
                if ($formatted_condition = $this->format_condition($condition, false, $return_commands)) {
                    if (empty($li[$condition->sortorder])) {
                        $li[$condition->sortorder] = '';
                    }
                    $li[$condition->sortorder] .= '<li>'.$formatted_condition.'</li>';
                }
            }
            if (count($li)) {
                $join = ''
                    .'</ul>'
                    .'<p class="quizportconditionsor">'.get_string('or', 'quizport').'</p>'
                    .'<ul>'
                ;
                $str = '<ul>'.implode($join, $li).'</ul>';
            }
            unset($li);
        }

        if ($str) {
            if ($return_intro) {
                if ($conditiontype==QUIZPORT_CONDITIONTYPE_PRE) {
                    $intro = get_string('preconditionsintro', 'quizport');
                } else {
                    $intro = get_string('postconditionsintro', 'quizport');
                }
                $str = '<p class="quizportconditionsintro">'.$intro.'</p>'.$str;
            }
        } else {
            $str = $default; // e.g. '&nbsp;' for a table cell
        }

        // append icons for add and delete
        if ($return_commands) {
            $str .= $this->format_commands_conditions($conditiontype, $quizid);
        }

        if ($return_js) {
            return addslashes_js($str);
        } else {
            if ($return_commands) {
                $id = $quizid;
            } else {
                $id = 'default';
            }
            if ($conditiontype==QUIZPORT_CONDITIONTYPE_PRE) {
                $id = 'quizport_preconditions_'.$id;
            } else {
                $id = 'quizport_postconditions_'.$id;
            }
            return '<div id="'.$id.'" class="conditions">'.$str.'</div>';
        }
    }

    function format_condition(&$condition, $return_js=false, $return_commands=true) {
        $str ='';

        static $groupnames = array();
        if ($condition->groupid) {
            $gid = $condition->groupid;
            if (! isset($groupnames[$gid])) {
                $groupnames[$gid] = groups_get_group_name($gid);
            }
            $str .= $groupnames[$gid].': ';
        }

        switch ($condition->conditiontype) {

            case QUIZPORT_CONDITIONTYPE_PRE:

                switch ($condition->conditionquizid) {

                    case QUIZPORT_CONDITIONQUIZID_SAME:
                        $str .= get_string('samequiz', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_PREVIOUS:
                        $str .= get_string('previousquiz', 'quizport');
                        break;

                    default:
                        // specific quiz id
                        if ($this->get_quizzes() && isset($this->quizzes[$condition->conditionquizid])) {
                            $str .= '<b>'.format_string($this->quizzes[$condition->conditionquizid]->name).'</b>';
                            $str .= ' ('.$this->quizzes[$condition->conditionquizid]->sortorder.')';
                        } else {
                            // $str .= 'conditionquizid='.$condition->conditionquizid;
                        }
                }

                if ($details = $this->format_condition_details($condition, true)) {
                    $str .= ': '.$details;
                }

                if ($return_commands) {
                    $str .= $this->format_commands_condition($condition);
                }
                break;

            case (QUIZPORT_CONDITIONTYPE_POST):

                if ($details = $this->format_condition_details($condition)) {
                    $str .= $details.': ';
                }

                switch ($condition->nextquizid) {

                    case QUIZPORT_CONDITIONQUIZID_SAME:
                        $str .= get_string('samequiz', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_NEXT1:
                        $str .= get_string('next1quiz', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_NEXT2:
                        $str .= get_string('next2quiz', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_NEXT3:
                        $str .= get_string('next3quiz', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_NEXT4:
                        $str .= get_string('next4quiz', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_NEXT5:
                        $str .= get_string('next5quiz', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_PREVIOUS:
                        $str .= get_string('previousquiz', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_UNSEEN: // no attempts
                        $str .= get_string('unseenquiz', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_UNANSWERED: // no responses
                        $str .= get_string('unansweredquiz', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_INCORRECT: // score < 100%
                        $str .= get_string('incorrectquiz', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_RANDOM:
                        $str .= get_string('randomquiz', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_MENUNEXT:
                        $str .= get_string('menuofnextquizzes', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_MENUNEXTONE:
                        $str .= get_string('menuofnextquizzesone', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_MENUALL:
                       $str .= get_string('menuofallquizzes', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_MENUALLONE:
                       $str .= get_string('menuofallquizzesone', 'quizport');
                        break;

                    case QUIZPORT_CONDITIONQUIZID_ENDOFUNIT:
                        $str .= get_string('endofunit', 'quizport');
                        break;

                    default: // nextquizid > 0
                        if ($this->get_quizzes() && isset($this->quizzes[$condition->nextquizid])) {
                            $str .= '<b>'.format_string($this->quizzes[$condition->nextquizid]->name).'</b>';
                            $str .= ' ('.$this->quizzes[$condition->nextquizid]->sortorder.')';
                        } else {
                            // $str .= '<b>nextquizid='.$condition->nextquizid.'</b>';
                        }
                        break;
                }

                if ($return_commands) {
                    $str .= $this->format_commands_condition($condition);
                }
                break;

            default:
                // unknown condition type
        }

        if ($return_js) {
            return addslashes_js($str);
        } else {
            return '<span id="quizport_condition_'.$condition->id.'">'.$str.'</span>';
        }
    }

    function format_condition_details(&$condition, $returnlist=false) {
        static $str;
        if (! isset($str)) {
            $str = (object)array(
                'min' => '&gt;=',
                'max' => '&lt;=',
                'anyattempts' => get_string('anyattempts', 'quizport'),
                'recentattempts' => get_string('recentattempts', 'quizport'),
                'consecutiveattempts' => get_string('consecutiveattempts', 'quizport'),
                'conditionscore' => get_string('score', 'quizport'),
                'attemptduration' => get_string('duration', 'quizport'),
                'attemptdelay' => get_string('delay', 'quizport')
            );
        }

        $details = array();

        if ($condition->attemptcount) {
            switch ($condition->attempttype) {
                case QUIZPORT_ATTEMPTTYPE_ANY: $type = $str->anyattempts; break;
                case QUIZPORT_ATTEMPTTYPE_RECENT: $type = $str->recentattempts; break;
                case QUIZPORT_ATTEMPTTYPE_CONSECUTIVE: $type = $str->consecutiveattempts; break;
                default: $type = 'attempttype='.$condition->attempttype; // shouldn't happen !!
            }
            if ($condition->attemptcount<0) {
                // minimum number of attempts
                $details['attemptcount'] = get_string('ormore', 'quizport', abs($condition->attemptcount)).' x '.$type;
            } else {
                // maximum number of attempts
                $details['attemptcount'] = get_string('orless', 'quizport', $condition->attemptcount).' x '.$type;
            }
        }

        if ($condition->conditionscore) {
            $minmax = ($condition->conditionscore<0 ? $str->min : $str->max);
            $details['conditionscore'] = $minmax.abs($condition->conditionscore).'%';
        }
        if ($condition->attemptduration) {
            $minmax = ($condition->attemptduration<0 ? $str->min : $str->max);
            $details['attemptduration'] = $minmax.format_time(abs($condition->attemptduration));
        }
        if ($condition->attemptdelay) {
            $minmax = ($condition->attemptdelay<0 ? $str->min : $str->max);
            $details['attemptdelay'] = $minmax.format_time(abs($condition->attemptdelay));
        }

        $count = count($details);
        if ($count==0) {
            return '';
        }
        if ($count==1) {
            list($name, $detail) = each($details);
            if ($name=='conditionscore' || $name=='attemptcount') {
                return moodle_strtolower($detail);
            } else {
                return moodle_strtolower($str->$name).$detail;
            }
        }
        foreach ($details as $name=>$detail) {
            if ($name=='attemptcount') {
                $details[$name] = moodle_strtolower($detail);
            } else {
                $details[$name] = moodle_strtolower($str->$name.$detail);
            }
        }
        if ($returnlist) {
            return '<ul><li>'.implode('</li><li>', $details).'</li></ul>';
        } else {
            return implode(', ', $details);
        }
    }

    function print_page_delete($message, $quizportscriptname, $params, $footercourse='none') {

        $this->print_header();
        $this->print_heading();

        print_box_start('generalbox', 'notice');

        $this->print_form_start($quizportscriptname, $params);
        print '<div class="buttons">';
        print '<p>'. $message .'</p>';
        print '<input type="submit" name="deleteconfirmed" value=" '.get_string('yes').' " />'."\n";
        print '<input type="submit" name="deletecancelled" value=" '.get_string('no').'" />'."\n";
        print '</div>';
        $this->print_form_end();

        print_box_end();
        $this->print_footer($footercourse);
    }

    function get_mycourses($userid=0) {
        // get all courses in which this user can manage activities
        global $DB, $USER;
        if ($userid) {
            $thisuser = false;
            $mycourses = null;
        } else {
            $thisuser = true;
            $userid = $this->userid;
            $mycourses = &$this->mycourses;
        }
        if (is_null($mycourses)) {
            $mycourses = false;
            // get list of courses in which this user is a teacher
            if (function_exists('get_user_access_sitewide')) {
                // Moodle >= 1.8 : get access info for this user
                if ($thisuser && isset($USER->access)) {
                    $access = $USER->access;
                } else {
                    $access = get_user_access_sitewide($userid);
                }
                if ($courses = get_user_courses_bycap($userid, 'moodle/course:manageactivities', $access, true)) {
                    $mycourses = array();
                    foreach ($courses as $course) {
                        $mycourses[$course->id] = $course;
                    }
                }
            } else if (function_exists('get_user_capability_course')) {
                // Moodle 1.7
                if ($courses = get_user_capability_course('moodle/course:manageactivities', $userid)) {
                    $ids = array();
                    foreach ($courses as $course) {
                        $ids[$course->id] = true;
                    }
                    $select = 'id IN ('.implode(',', array_keys($ids)).')';
                    $mycourses = $DB->get_records_select('course', $select, null, 'sortorder');
                }
            } else {
                // Moodle <= 1.6
                $tables = "{user_teachers} ut, {course} c";
                $select = "ut.userid=$userid AND ut.course=c.id";
                $mycourses = $DB->get_records_sql("SELECT c.* FROM $tables WHERE $select");
            }
            if (empty($mycourses[SITEID])) {
                $sitecontext = get_context_instance(CONTEXT_COURSE, SITEID);
                if (has_capability('moodle/course:manageactivities', $sitecontext)) {
                    $mycourses = array(SITEID => get_site()) + $mycourses;
                }
            }
        }
        return $mycourses;
    }

    function get_myquizports($userid=0, $mycourses=null) {
        if ($userid) {
            $thisuser = false;
            $myquizports = null;
        } else {
            $thisuser = true;
            $userid = $this->userid;
            $myquizports = &$this->myquizports;
        }
        if (is_null($myquizports)) {
            if (is_null($mycourses)) {
                $mycourses = $this->get_mycourses($thisuser ? 0 : $userid);
            }
            // get all instances of quizports in all courses which this user can edit
            if ($instances = get_all_instances_in_courses('quizport', $mycourses, $userid)) {
                $myquizports = array();
                foreach ($instances as $instance) {
                    $myquizports[$instance->id] = $instance;
                }
            } else {
                $myquizports = false;
            }
        }
        return $myquizports;
    }

    // access the QuizPort tables in the database

    function get_quizports($userid=0) {
        global $CFG;
        if ($userid) {
            $thisuser = false;
            $quizports = null;
        } else {
            $thisuser = true;
            $userid = $this->userid;
            $quizports = &$this->quizports;
        }
        if (is_null($quizports)) {
            // get all quizports in this course that are visible to this user
            if ($CFG->majorrelease<=1.0) {
                $instances = get_all_instances_in_course('quizport', $this->courserecord->id);
            } else {
                // userid is not used by Moodle 1.1 thru 1.6, but it does no harm
                $instances = get_all_instances_in_course('quizport', $this->courserecord, $userid);
            }
            if ($instances) {
                $quizports = array();
                foreach ($instances as $instance) {
                    $quizports[$instance->id] = $instance;
                }
            } else {
                $quizports = false;
            }
        }
        return $quizports;
    }

    function get_units($userid=0, $quizports=null) {
        global $DB;
        if ($userid) {
            $thisuser = false;
            $units = null;
        } else {
            $thisuser = true;
            $units = &$this->units;
        }
        if (is_null($units)) {
            if (is_null($quizports)) {
                $quizports = $this->get_quizports($thisuser ? 0 : $userid);
            }
            if ($quizports) {
                $parentids = implode(',', array_keys($quizports));
                $select = 'parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY." AND parentid IN ($parentids)";
                $units = $DB->get_records_select('quizport_units', $select);
            } else {
                $units = false;
            }
        }
        return $units;
    }

    function get_unit($quizportid=0) {
        global $DB;
        if ($quizportid) {
            $unit = null;
        } else {
            $unit = &$this->unit;
            $quizportid = &$this->quizport->id;
        }
        if (is_null($unit)) {
            $unit = $DB->get_record('quizport_units', array('parenttype'=>QUIZPORT_PARENTTYPE_ACTIVITY, 'parentid'=>$quizportid));
        }
        return $unit;
    }

    function get_unitgrades($userid=0, $unitid=0, $unumber=0, $quizid=0, $qnumber=0) {
        global $DB;
        if ($userid) {
            $thisuser = false;
            $unitgrades = null;
        } else {
            $thisuser = true;
            $userid = $this->userid;
            $unitgrades = &$this->unitgrades;
        }
        if (is_null($unitgrades)) {
            if ($quizports = $this->get_quizports($thisuser ? 0 : $userid)) {
                $parentids = implode(',', array_keys($quizports));
                $select = 'parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY." AND parentid IN ($parentids) AND userid=$userid";
                $unitgrades = $DB->get_records_select('quizport_unit_grades', $select);
            } else {
                $unitgrades = false;
            }
        }
        return $unitgrades;
    }

    function get_unitgrade($userid=0, $unitid=0, $unumber=0, $quizid=0, $qnumber=0) {
        global $DB;
        if ($userid) {
            $thisuser = false;
            $unitgrade = null;
        } else {
            $thisuser = true;
            $userid = $this->userid;
            $unitgrade = &$this->unitgrade;
        }
        if (is_null($unitgrade)) {
            if ($unitid) {
                $unit = $DB->get_record('quizport_units', array('id'=>$unitid), 'id,parenttype,parentid');
            } else {
                $unit = &$this->unit;
            }
            $unitgrade = $DB->get_record('quizport_unit_grades', array('parenttype'=>$unit->parenttype, 'parentid'=>$unit->parentid, 'userid'=>$userid));
        }
        return $unitgrade;
    }

    function get_unitattempts($userid=0, $unitid=0) {
        global $DB;
        if ($userid) {
            $thisuser = false;
            $unitattempts = null;
        } else {
            $thisuser = true;
            $userid = $this->userid;
            $unitid = $this->unitid;
            $unitattempts = &$this->unitattempts;
        }
        if (is_null($unitattempts)) {
            $select = "userid=$userid";
            if (is_null($this->modulerecord)) {
                // all unit attempts at all units in the course
                if ($units = $this->get_units($thisuser ? 0 : $userid)) {
                    $unitids = implode(',', array_keys($units));
                    $select .= " AND unitid IN ($unitids)";
                }
            } else {
                // all unit attempts at one particular cm/quizport/unit in the course
                if ($unitid) {
                    $select .= " AND unitid=$unitid";
                }
            }
            $unitattempts = $DB->get_records_select('quizport_unit_attempts', $select);
        }
        return $unitattempts;
    }

    function get_maxunitattemptgrade() {
        if (is_null($this->maxunitattemptgrade)) {
            $this->maxunitattemptgrade = 0;
            if ($this->get_unitattempts()) {
                foreach ($this->unitattempts as $unitattempt) {
                    $this->maxunitattemptgrade = max($this->maxunitattemptgrade, $unitattempt->grade);
                }
            }
        }
        return $this->maxunitattemptgrade;
    }

    function get_unitcompleted() {
        if (is_null($this->unitcompleted)) {
            $this->unitcompleted = 0;
            if ($this->get_unitattempts()) {
                foreach ($this->unitattempts as $unitattempt) {
                    if ($unitattempt->status==QUIZPORT_STATUS_COMPLETED) {
                        $this->unitcompleted++;
                    }
                }
            }
        }
        return $this->unitcompleted;
    }

    function get_unitattempt($userid=0, $unitid=0, $unumber=0) {
        global $DB;
        if ($userid) {
            $thisuser = false;
            $unitattempt = null;
        } else {
            $thisuser = true;
            $userid = $this->userid;
            $unitid = $this->unitid;
            $unumber = $this->unumber;
            $unitattempt = &$this->unitattempt;
        }
        if (is_null($unitattempt)) {
            $select = "unitid=$unitid AND unumber=$unumber AND userid=$userid";
            if ($unitattempt = $DB->get_record_select('quizport_unit_attempts', $select)) {
                $unitattemptid = $unitattempt->id;
            }
        }
        return $unitattempt;
    }

    function get_quizzes($unitid=0) {
        global $DB;
        if ($unitid) {
            $thisuser = false;
            $quizzes = null;
        } else {
            $thisuser = true;
            $unitid = $this->unitid;
            $quizzes = &$this->quizzes;
        }
        if (is_null($quizzes)) {
            $select = '';
            if ($thisuser && is_null($this->modulerecord)) {
                // all quizzes in all units in the course
                if ($units = $this->get_units()) {
                    $unitids = implode(',', array_keys($units));
                    $select = "unitid IN ($unitids)";
                }
            } else {
                // all quizzes in one particular cm/quizport/unit in the course
                if ($unitid) {
                    $select = "unitid=$unitid";
                }
            }
            if ($select) {
                $quizzes = $DB->get_records_select('quizport_quizzes', $select, null, 'sortorder');
            } else {
                $quizzes = false;
            }
        }
        return $quizzes;
    }

    function get_quiz() {
        global $DB;
        if (is_null($this->quiz)) {
            if (! $this->quiz = $DB->get_record('quizport_quizzes', array('id'=>$this->quizid))) {
                return false; // shouldn't happen - $this->quizid was invalid !!
            }
        }
        if ($this->quiz) {
            if (! isset($this->quiz->output)) {

                // create config file object
                $class = 'quizport_file';
                quizport_load_quiz_class('file');
                $config = new $class($this->quiz->configfile, $this->quiz->configlocation);

                // create source file object
                $class = 'quizport_file_'.$this->quiz->sourcetype;
                quizport_load_quiz_class('file', $this->quiz->sourcetype);
                $source = new $class($this->quiz->sourcefile, $this->quiz->sourcelocation);

                // determine best output format for this quiz and browser
                if ($this->quiz->outputformat) {
                    $outputformat = $this->quiz->outputformat;
                } else {
                    // select "best" output format for this sourcetype and viewing device
                    $outputformat = $source->get_best_outputformat();
                }

                // create output object
                $class = 'quizport_output_'.$outputformat;
                quizport_load_quiz_class('output', $outputformat);
                $this->quiz->output = new $class($this->quiz);

                $this->quiz->output->source = &$source;
                $this->quiz->output->source->config =&$config;
            }
        }
        return $this->quizid;
    }

    function get_quizscores($userid=0, $unitid=0, $unumber=0) {
        // get all scores by this user for quizzes in this unit attempt
        global $DB;
        if ($userid) {
            $thisuser = false;
            $quizscores = null;
        } else {
            $thisuser = true;
            $userid = $this->userid;
            $unitid = $this->unitid;
            $unumber = $this->unumber;
            $quizscores = &$this->quizscores;
        }
        if (is_null($quizscores)) {
            if ($quizzes = $this->get_quizzes($unitid)) {
                $quizids = implode(',', array_keys($quizzes));
                $select = "quizid IN ($quizids) AND userid=$userid";
                if ($unumber>0) {
                    $select .= " AND unumber=$unumber";
                }
                $quizscores = $DB->get_records_select('quizport_quiz_scores', $select, null, 'timemodified');
            }
        }
        return $quizscores;
    }

    function get_quizscore($userid=0, $unumber=0, $quizid=0) {
        global $DB;
        if ($userid) {
            $thisuser = false;
            $quizscore = null;
            $quizscoreid = 0;
        } else {
            $thisuser = true;
            $userid = $this->userid;
            $quizid = $this->quizid;
            $unumber = $this->unumber;
            $quizscore = &$this->quizscore;
            $quizscoreid = &$this->quizscoreid;
        }
        if (is_null($quizscore)) {
            if ($quizscore = $DB->get_record('quizport_quiz_scores', array('quizid'=>$quizid, 'unumber'=>$unumber, 'userid'=>$userid))) {
                $quizscoreid = $quizscore->id;
            }
        }
        if ($thisuser) {
            return $quizscoreid;
        } else {
            return $quizscore;
        }
    }

    function get_quizattempts($userid=0, $unitid=0, $unumber=0, $quizid=0, $qnumber=0) {
        global $DB;
        if ($userid) {
            $thisuser = false;
            $quizattempts = null;
        } else {
            $thisuser = true;
            $userid = $this->userid;
            $quizid = $this->quizid;
            $unumber = $this->unumber;
            $qnumber = $this->qnumber;
            if ($this->quizattempts) {
                $record = reset($this->quizattempts);
                if ($record->quizid != $quizid) {
                    $this->quizattempts = null;
                }
            }
            $quizattempts = &$this->quizattempts;
        }
        if (is_null($quizattempts)) {
            $sort = '';
            $select = '';
            if ($quizid) {
                $select = "quizid=$quizid AND userid=$userid";
                if ($unumber>0) {
                    $select .= " AND unumber=$unumber";
                    if ($qnumber>0) {
                        $select .= " AND qnumber=$qnumber";
                    }
                }
                // time/resume finish is zero for "in progress" attempts, so sort by resumestart (most recent first)
                $sort = 'resumestart DESC';
            } else {
                if ($this->get_quizzes()) {
                    $quizids = implode(',', array_keys($this->quizzes));
                    $select = "quizid IN ($quizids) AND userid=$userid";
                    if ($unumber>0) {
                        $select .= " AND unumber=$unumber";
                    }
                }
                // most recent last
                $sort = 'resumestart ASC';
            }
            if ($select) {
                $quizattempts = $DB->get_records_select('quizport_quiz_attempts', $select, null, $sort);
            }
        }
        return $quizattempts;
    }

    function get_quizattempt($userid=0, $unitid=0, $unumber=0, $quizid=0, $qnumber=0) {
        global $DB;
        if ($userid) {
            $thisuser = false;
            $quizattempt = null;
            $quizattemptid = 0;
        } else {
            $thisuser = true;
            $userid = $this->userid;
            $quizid = $this->quizid;
            $unumber = $this->unumber;
            $qnumber = $this->qnumber;
            $quizattempt = &$this->quizattempt;
            $quizattemptid = &$this->quizattemptid;
        }
        if (is_null($quizattempt)) {
            if ($quizattempt = $DB->get_record_select('quizport_quiz_attempts', "quizid=$quizid AND unumber=$unumber AND qnumber=$qnumber AND userid=$userid")) {
                $quizattemptid = $quizattempt->id;
            }
        }
        if ($thisuser) {
            return $quizattemptid;
        } else {
            return $quizattempt;
        }
    }

    function get_attempts($type, $userid=0, $unitid=0, $unumber=0, $quizid=0, $qnumber=0) {
        $get_attempts = "get_{$type}attempts";
        return $this->$get_attempts($userid, $unitid, $unumber, $quizid, $qnumber);
    }

    function get_attempt($type, $userid=0, $unitid=0, $unumber=0, $quizid=0, $qnumber=0) {
        $get_attempt = "get_{$type}attempt";
        return $this->$get_attempt($userid, $unitid, $unumber, $quizid, $qnumber);
    }

    function get_grade($type, $userid=0, $unitid=0, $unumber=0, $quizid=0, $qnumber=0) {
        if ($type=='unit') {
            $get_grade = "get_unitgrade";
        } else {
            $get_grade = "get_quizscore";
        }
        return $get_grade($userid, $unitid, $unumber, $quizid, $qnumber);
    }

    function get_lastattempt($type) {

        $lastattempt = "last{$type}attempt";
        $lastattemptid = "last{$type}attemptid";
        $lastattempttime = "last{$type}attempttime";

        if (is_null($this->$lastattempt)) {

            $get_attempts = "get_{$type}attempts";
            if ($this->$get_attempts()) {

                $attempts = "{$type}attempts";
                if ($this->quizid) {
                    // most recent attempt is first
                    $this->$lastattempt = reset($this->$attempts);
                } else {
                    // most recent attempt is last
                    $this->$lastattempt = end($this->$attempts);
                }
                $this->$lastattemptid = $this->$lastattempt->id;

                if ($type=='unit') {
                    $this->$lastattempttime = $this->$lastattempt->timemodified;
                } else {
                    // quiz
                    $this->$lastattempttime = max(
                        $this->$lastattempt->timestart, $this->$lastattempt->timefinish,
                        $this->$lastattempt->resumestart, $this->$lastattempt->resumefinish
                    );
                }
            }
        }
        return $this->$lastattemptid;
    }

    function get_conditions($conditiontype=0, $quizid=0, $allgroups=0) {
        global $DB;
        static $groups;

        if (! isset($groups)) {
            // get list of groups (if any) for this user
            if (empty($this->modulerecord->groupmembersonly) || empty($this->modulerecord->groupingid)) {
                $groups = $allgroups;
            } else if (has_capability('mod/quizport:manage', $this->modulecontext)) {
                $groups = $allgroups;
            } else if (has_capability('moodle/site:accessallgroups', $this->modulecontext)) {
                $groups = $allgroups;
            } else {
                // groups in this course/grouping to which this user belongs
                if ($groups = groups_get_all_groups($this->courserecord->id, $this->userid, $this->modulerecord->groupingid)) {
                    $groups = implode(',', array_keys($groups));
                }
            }
        }

        switch ($conditiontype) {
            case QUIZPORT_CONDITIONTYPE_PRE:
                $conditions = &$this->cache_preconditions;
                break;
            case QUIZPORT_CONDITIONTYPE_POST:
                $conditions = &$this->cache_postconditions;
                break;
            default:
                // invalid $conditiontype
                $conditions[$quizid] = array();
        }

        if (! isset($conditions[$quizid])) {
            $select = "quizid=$quizid AND conditiontype=$conditiontype";
            if ($groups) {
                // restrict conditions to those for groups in this course/grouping to which this user belongs
                $select .= ' AND groupid IN (0,'.$groups.')';
            } else if ($groups===0) {
                // only select conditions which apply to any and all groups
                $select .= ' AND groupid=0';
            }
            // conditionscore < 0 : the minimum score at which this condition is satisfied
            // conditionscore > 0 : the maximum score at which this condition is satisfied
            // The post-conditions will be ordered by conditionscore:
            // -100 (highest min) ... (lowest min) 0 (lowest max) ... (highest max) 100
            if (! $conditions[$quizid] = $DB->get_records_select('quizport_conditions', $select, null, 'conditionquizid,sortorder,conditionscore,attemptcount,attemptduration,attemptdelay')) {
                $conditions[$quizid] = array();
            }
            // store absolute values for settings which can be negative
            foreach ($conditions[$quizid] as $condition) {
                $conditions[$quizid][$condition->id]->abs_conditionscore = abs($condition->conditionscore);
                $conditions[$quizid][$condition->id]->abs_attemptcount = abs($condition->attemptcount);
                $conditions[$quizid][$condition->id]->abs_attemptduration = abs($condition->attemptduration);
                $conditions[$quizid][$condition->id]->abs_attemptdelay = abs($condition->attemptdelay);
            }
        }

        return $conditions[$quizid];
    }

    // create records for QuizPort tables

    function create_unitgrade($grade=0, $status=QUIZPORT_STATUS_INPROGRESS, $duration=0, $unitid=0, $unumber=0, $userid=0) {
        global $DB;

        if ($userid==0 && $this->unitgradeid) {
            // unitgrade record already exists
            return $this->unitgradeid;
        }

        if ($userid) {
            $thisuser = false;
        } else {
            $thisuser = true;
            $userid = $this->userid;
        }
        if ($unitid) {
            $unit = $DB->get_record('quizport_units', array('id'=>$unitid), 'id,parenttype,parentid');
        } else {
            $unit = &$this->unit;
        }

        $unitgrade = new stdClass();
        $unitgrade->parenttype   = $unit->parenttype;
        $unitgrade->parentid     = $unit->parentid;
        $unitgrade->userid       = $userid;
        $unitgrade->grade        = $grade;
        $unitgrade->status       = $status;
        $unitgrade->duration     = $duration;
        $unitgrade->timemodified = time();

        if (! $unitgrade->id = $DB->insert_record('quizport_unit_grades', $unitgrade)) {
            print_error('error_insertrecord', 'quizport', '', 'quizport_unit_grades');
        }

        if ($thisuser) {
            $this->unitgrade   = &$unitgrade;
            $this->unitgradeid = $this->unitgrade->id;
        }

        return $unitgrade->id;
    }

    function create_unitattempt($grade=0, $status=QUIZPORT_STATUS_INPROGRESS, $duration=0, $unitid=0, $unumber=0, $userid=0) {
        global $DB;
        if ($userid==0 && $this->unitattemptid) {
            // unitattempt record already exists
            return $this->unitattemptid;
        }

        if ($userid) {
            $thisuser = false;
        } else {
            $thisuser = true;
            $userid = $this->userid;
        }
        if (! $unitid) {
            $unitid = $this->unitid;
        }
        if (! $unumber) {
            $unumber = $DB->count_records_select(
                'quizport_unit_attempts', "unitid=$unitid AND userid=$userid", null, 'MAX(unumber)'
            ) + 1;
        }

        $unitattempt = new stdClass();
        $unitattempt->unitid       = $unitid;
        $unitattempt->userid       = $userid;
        $unitattempt->unumber      = $unumber;
        $unitattempt->grade        = $grade;
        $unitattempt->status       = $status;
        $unitattempt->duration     = $duration;
        $unitattempt->timemodified = time();

        if (! $unitattempt->id = $DB->insert_record('quizport_unit_attempts', $unitattempt)) {
            print_error('error_insertrecord', 'quizport', '', 'quizport_unit_attempts');
        }

        // sql to select previous unit_attempts and their quiz_scores and quiz_attempts
        $quizids = "SELECT id FROM {quizport_quizzes} WHERE unitid=$unitid";
        $select = "unumber<$unumber AND userid=$userid AND status=".QUIZPORT_STATUS_INPROGRESS;

        // set status of previous unit attempts (and their quiz_attempts and quiz_scores) to adandoned
        $DB->set_field_select('quizport_quiz_attempts', 'status', QUIZPORT_STATUS_ABANDONED, "quizid IN ($quizids) AND $select");
        $DB->set_field_select('quizport_quiz_scores', 'status', QUIZPORT_STATUS_ABANDONED, "quizid IN ($quizids) AND $select");
        $DB->set_field_select('quizport_unit_attempts', 'status', QUIZPORT_STATUS_ABANDONED, "unitid=$unitid AND $select");

        // TODO : might be nice to have a setting for "number of concurrent attempts allowed"

        if ($thisuser) {
            $this->unitattempt   = &$unitattempt;
            $this->unitattemptid = $this->unitattempt->id;
            $this->unumber       = $this->unitattempt->unumber;

            if (! $this->get_unitgrade()) {
                $this->create_unitgrade(0, 0, 0, $unitid, $unumber, $userid);
            }
        }

        return $unitattempt->id;
    }

    function create_quizscore($score=0, $status=QUIZPORT_STATUS_INPROGRESS, $duration=0, $quizid=0, $unumber=0, $userid=0) {
        global $DB;

        if ($userid==0 && $this->quizscoreid) {
            // quizscore record already exists
            return $this->quizscoreid;
        }

        if ($userid) {
            $thisuser = false;
        } else {
            $thisuser = true;
            $userid = $this->userid;
        }
        if (! $quizid) {
            $quizid = $this->quizid;
        }
        if (! $unumber) {
            $unumber = $this->unumber;
        }

        $quizscore = new stdClass();
        $quizscore->quizid       = $quizid;
        $quizscore->unumber      = $unumber;
        $quizscore->userid       = $userid;
        $quizscore->score        = $score;
        $quizscore->status       = $status;
        $quizscore->duration     = $duration;
        $quizscore->timemodified = $this->time;

        if (! $quizscore->id = $DB->insert_record('quizport_quiz_scores', $quizscore)) {
            print_error('error_insertrecord', 'quizport', '', 'quizport_quiz_scores');
        }

        if ($thisuser) {
            $this->quizscore   = &$quizscore;
            $this->quizscoreid = $quizscore->id;
        }

        return $quizscore->id;
    }

    function create_quizattempt() {
        global $DB;
        if (empty($this->quizattempt)) {
            $max_qnumber = $DB->count_records_select(
                'quizport_quiz_attempts', "quizid=$this->quizid AND unumber=$this->unumber AND userid=$this->userid", null, 'MAX(qnumber)'
            );
            $this->quizattempt = new stdClass();
            $this->quizattempt->quizid       = $this->quizid;
            $this->quizattempt->unumber      = $this->unumber;
            $this->quizattempt->qnumber      = $max_qnumber + 1;
            $this->quizattempt->userid       = $this->userid;
            $this->quizattempt->status       = QUIZPORT_STATUS_INPROGRESS;
            $this->quizattempt->penalties    = 0;
            $this->quizattempt->score        = 0;
            $this->quizattempt->duration     = 0;
            $this->quizattempt->resumestart  = $this->time;
            $this->quizattempt->resumefinish = 0;
            $this->quizattempt->timestart    = $this->time;
            $this->quizattempt->timefinish   = 0;
            $this->quizattempt->timemodified = $this->time;

            $select = "unitid=$this->unitid AND userid=$this->userid";

            if (! $this->quizattempt->id = $DB->insert_record('quizport_quiz_attempts', $this->quizattempt)) {
                print_error('error_insertrecord', 'quizport', '', 'quizport_quiz_attempts');
            }

            $this->quizattemptid = $this->quizattempt->id;
            $this->qnumber       = $this->quizattempt->qnumber;

            // set previous quiz attempts to adandoned
            $select = "quizid=$this->quizid AND unumber=$this->unumber AND qnumber<$this->qnumber AND userid=$this->userid AND status=".QUIZPORT_STATUS_INPROGRESS;
            $DB->set_field_select('quizport_quiz_attempts', 'status', QUIZPORT_STATUS_ABANDONED, $select);

            // TODO : might be nice to have a setting for "number of concurrent attempts allowed"

            if (! $this->get_quizscore()) {
                $this->create_quizscore();
            }
        }
        return $this->quizattemptid;
    }

    function create_attempt($type, $grade=0, $status=QUIZPORT_STATUS_INPROGRESS, $duration=0) {
        $create_attempt = "create_{$type}attempt";
        return $this->$create_attempt($grade, $status, $duration);
    }

    // check access to the unit / quiz

    function require_access($type) {
        if (! $error = $this->require_subnet($type)) {
            if (! $error = $this->require_password($type)) {
                $error = false;
            }
        }
        return $error;
    }

    function require_subnet($type) {
        if (! $this->$type->subnet) {
            return false;
        }
        if (isset($_SERVER['REMOTE_ADDR']) && address_in_subnet($_SERVER['REMOTE_ADDR'], $this->$type->subnet)) {
            return false;
        }
        // user's IP address is missing or does not match required subnet mask
        return get_string($type.'subneterror', 'quizport');
    }

    function require_password($type) {
        global $SESSION;
        $error = '';

        // does this unit /quiz require a password?
        if ($this->$type->password) {

            $quizport_passwordchecked = 'quizport_'.$type.'_passwordchecked';

            // has password not already been given?
            if (empty($SESSION->$quizport_passwordchecked[$this->$type->id])) {

                // get password, if any, that was entered
                $password = optional_param('quizportpassword', '');
                if (strcmp($this->$type->password, $password)) {

                    // password is missing or invalid
                    $error  = '<form id="quizportpasswordform" method="post" action="view.php?id='.$this->modulerecord->id.'">'."\n";
                    $error .= get_string($type.'requirepasswordmessage', 'quizport').'<br /><br />';
                    $error .= '<b>'.get_string('password').':</b> ';
                    $error .= '<input name="quizportpassword" type="password" value="" /> ';
                    $error .= '<input type="submit" value="'.get_string('ok').'" /> ';
                    $error .= "</form>\n";
                    if ($password) {
                        // previously entered password was invalid
                        $error .= get_string('passworderror', 'quiz');
                    }
                } else {
                    // newly entered password was correct
                    if (empty($SESSION->$quizport_passwordchecked)) {
                        $SESSION->$quizport_passwordchecked = array();
                    }
                    $SESSION->$quizport_passwordchecked[$this->$type->id] = true;
                }
            }
        }
        if ($error) {
            return $error;
        } else {
            return false;
        }
    }

    // check availability of the unit / quiz

    function require_availability($type) {
        if (! $error = $this->require_isopen($type)) {
            if (! $error = $this->require_notclosed($type)) {
                $error = false;
            }
        }
        return $error;
    }

    function require_isopen($type) {
        if ($this->$type->timeopen && $this->$type->timeopen > $this->time) {
            // unit/quiz is not yet open
            return get_string($type.'notavailable', 'quizport', userdate($this->$type->timeopen));
        }
        return false;
    }

    function require_notclosed($type) {
        if ($this->$type->timeclose && $this->$type->timeclose < $this->time) {
            // unit/quiz is already closed
            return get_string($type.'closed', 'quizport', userdate($this->$type->timeclose));
        }
        return false;
    }

    // check unumber / qnumber are valid

    function require_valid_unumber() {

        if ($this->unumber>0) {
            if ($error = $this->require_attempt('unit')) {
                // unumber is not valid - either this unit attempt was deleted
                // or someone is making up unumbers for the fun of it
                return $error;
            }
            if ($error = $this->require_canresume('unit')) {
                // unit attempt is valid but cannot be resumed - probably it has just
                // been completed, but it may also have timed out or been abandoned
                $this->quizid = QUIZPORT_CONDITIONQUIZID_ENDOFUNIT;
                $this->quiz = null;
                $this->qnumber = 0;
            }
            return false;
        }

        if ($this->unumber==0) {
            if ($this->tab=='info' && has_capability('mod/quizport:preview', $this->modulecontext)) {
                // teacher can always view the entry page
                return false;
            }
            // if possible, find a previous unit attempt that can be resumed
            if ($this->unit->allowresume && $this->get_unitattempts()) {

                if ($this->unit->allowresume==QUIZPORT_ALLOWRESUME_FORCE) {
                    $force_resume = true;
                } else if ($error = $this->require_moreattempts('unit')) {
                    $force_resume = true;
                } else {
                    $force_resume = false;
                }

                foreach (array_keys($this->unitattempts) as $id) {
                    $attempt = &$this->unitattempts[$id];
                    if (! $error = $this->require_inprogress('unit', $attempt)) {
                        if (! $error = $this->require_moretime('unit', $attempt)) {
                            // $attempt can be resumed
                            if ($force_resume) {
                                // set unit attempt details
                                $this->unitattempt = &$attempt;
                                $this->unumber = $this->unitattempt->unumber;
                                $this->unitattemptid = $this->unitattempt->id;

                                // unset quiz details
                                $this->quizid = 0;
                                $this->quiz = null;
                                $this->qnumber = 0;
                            } else {
                                // adjust setting to show entry page with a list of attempts
                                $this->unit->entrypage = QUIZPORT_YES;
                                $this->unit->entryoptions = ($this->unit->entryoptions | QUIZPORT_ENTRYOPTIONS_ATTEMPTS);
                            }
                            // at least one attempt can be resumed, so we can stop
                            // (if necessary, unumber has been set to something valid)
                            return false;
                        }
                    }
                }
            }
            if ($this->unit->entrypage) {
                // entry page is viewable
                return false;
            }
            // no previous unit attempts could be resumed
            // so force the creation of a new unit attempt
            $this->unumber = -1;
        }

        // create a new unit attempt
        if ($error = $this->require_canstart('unit')) {
            return $error;
        }

        // at this point, $this->unumber and $this->unitatempt
        // have been set up by create_unitattempt()

        if (has_capability('mod/quizport:preview', $this->modulecontext)) {
            // let a teacher start at any quiz they like
        } else {
            // a student has to start at the begninning of the unit
            $this->quizid = 0;
            $this->qnumber = 0;
            $this->quiz = null;
        }

        return false;
    }

    function require_valid_qnumber() {
        if ($this->qnumber >= 0) {
            if (! $this->require_canresume('quiz')) {
                // no error (i.e. we can resume this unit/quiz attempt)
                return false;
            }
            // we cannot resume this unit/quiz attempt, so try and start a new one
            $this->qnumber = -1;
        }
        return $this->require_canstart('quiz');
    }

    // check user can start a new unit/quiz attempt

    function require_canstart($type) {
        if (has_capability('mod/quizport:preview', $this->modulecontext)) {
            // teacher can always start new attempt
            return $this->require_newattempt($type);
        }
        if (! $error = $this->require_delay($type, 'delay1')) {
            if (! $error = $this->require_delay($type, 'delay2')) {
                if (! $error = $this->require_moreattempts($type)) {
                    if (! $error = $this->require_newattempt($type)) {
                        // new unit/quiz attempt was successfully created
                        // $this->unumber/qnumber has now been set
                        $error = false;
                    }
                }
            }
        }
        return $error;
    }

    function require_delay($type, $delay) {
        $error = false;

        if ($this->$type->$delay && $this->get_lastattempt($type)) {
            // attempts and lastattempt have been retrieved from the database

            $attempts = "{$type}attempts";
            switch ($delay) {
                case 'delay1': $require_delay = (count($this->$attempts)==1); break;
                case 'delay2': $require_delay = (count($this->$attempts)>=2); break;
                default: $require_delay = false;
            }

            if ($require_delay) {
                $lastattempttime = "last{$type}attempttime";
                $nextattempttime = $this->$lastattempttime + ($this->$type->$delay);
                if ($this->time < $nextattempttime) {
                    // $delay has not expired yet
                    $error = get_string('temporaryblocked', 'quiz').' <strong>'. userdate($nextattempttime). '</strong>';
                }
            }
        }
        return $error;
    }

    function require_moreattempts($type, $shorterror=false) {
        if ($this->get_attempts($type)) {
            $attempts = "{$type}attempts";
            if ($this->$type->attemptlimit && $this->$type->attemptlimit <= count($this->$attempts)) {
                // maximum number of unit/quiz attempts reached
                if ($type=='unit') {
                    $name = $this->quizport->name;
                } else {
                    $name = $this->$type->name;
                }
                if ($shorterror) {
                    return get_string('nomore'.$type.'attempts', 'quizport');
                } else {
                    return ''
                        .'<p>'.get_string('nomore'.$type.'attempts', 'quizport').'</p>'
                        .'<p><b>'.format_string($name).'</b>'
                        .' ('.moodle_strtolower(get_string('attemptlimit', 'quizport')).'='.$this->$type->attemptlimit.')</p>'
                    ;
                }
            }
        }
        return false;
    }

    function require_newattempt($type) {
        // create_unit_attempt will get/create quizport_unit_grades record
        // create_quiz_attempt will get/create quizport_quiz_scores record
        if ($this->create_attempt($type)) {
            return false;
        } else {
            return true;
        }
    }

    function require_lastattempt($type) {
        if ($this->get_lastattempt($type)) {
            return false;
        }
        // no last attempt
        return get_string("nolast{$type}attempt", 'quizport');
    }

    // check user can resume this unit/quiz attempt

    function require_canresume($type) {
        // check whether user can resume this unit/quiz attempt
        if (! $error = $this->require_attempt($type)) {
            if (! $error = $this->require_inprogress($type)) {
                if (! $error = $this->require_moretime($type)) {
                    $error = false;
                }
            }
        }
        return $error;
    }

    // this function may be useful for checking that a "lastquizattempt" still satisfies preconditions
    // if attempts at earlier quizzes have been deleted, then the user may no longer be allowed to take this quiz
    function require_preconditions($attempt='') {
        $ok = false;
        if ($attempt) {
            $quizid = $this->$attempt->id;
        } else {
            $quizid = $this->quizattempt->id;
        }
        $previousquizid = 0;
        $this->get_quizzes();
        foreach ($this->quizzes as $quiz) {
            // check only the preconditions of the quiz we are interested in (skip other quizzes)
            if ($quiz->id==$quizid) {
                $ok = $this->check_conditions(QUIZPORT_CONDITIONTYPE_PRE, $quizid, $previousquizid);
                break;
            }
            // update $previousquizid, because some preconditions may require it
            $previousquizid = $quiz->id;
        }

        if ($ok) {
            return false; // pre-conditions were satisfied
        } else {
            return get_string('preconditionsnotsatisfied', 'quizport');
        }
    }

    function require_attempt($type) {
        $get_attempt = "get_{$type}attempt";
        $create_attempt = "create_{$type}attempt";
        if (! $this->$get_attempt() && ! $this->$create_attempt()) {
            if ($type=='unit') {
                $a = "unitid=$this->unitid AND unumber=$this->unumber userid=$this->userid";
            } else {
                $a = "quizid=$this->quizid AND unumber=$this->unumber AND qnumber=$this->qnumber AND userid=$this->userid";
            }
            return get_string($type.'attemptnotfound', 'quizport', $a);
        }
        return false;
    }

    function require_inprogress($type, $attempt='') {
        if (is_string($attempt)) {
            if ($attempt=='') {
                $attempt = "{$type}attempt";
            }
            $attempt = &$this->$attempt;
        }
        if ($attempt->status>QUIZPORT_STATUS_INPROGRESS) { // allow status==0
            return get_string($type.'attemptnotinprogress', 'quizport');
        }
        return false;
    }

    function require_moretime($type, $attempt='') {
        if (is_string($attempt)) {
            if ($attempt=='') {
                $attempt = "{$type}attempt";
            }
            $attempt = &$this->$attempt;
        }
        if ($this->$type->timelimit && $this->$type->timelimit < $attempt->duration) {
            return get_string('timelimitexpired', 'quizport');
        }
        return false;
    }

    // check user can submit this unit/quiz attempt

    function require_cansubmit($type) {
        // check whether user can submit results for this unit/quiz attempt
        if (! $error = $this->require_attempt($type)) {
            if (! $error = $this->require_inprogress($type)) {
                $error = false;
            }
        }
        return $error;
    }

    function require_unit_cansubmit() {
        return $this->require_cansubmit('unit');
    }

    function require_quiz_cansubmit() {
        return $this->require_cansubmit('quiz');
    }

    // check access to a unit

    function require_unit_access() {
        if ($this->tab=='info' && has_capability('mod/quizport:preview', $this->modulecontext)) {
            // teacher can always view the entry page
            return false;
        }
        if (! $error = $this->require_unit_visibility()) {
            if (! $error = $this->require_unit_grouping()) {
                if (! $error = $this->require_access('unit')) {
                    if (! $error = $this->require_unit_inpopup()) {
                        $error = false;
                    }
                }
            }
        }
        return $error;
    }

    function require_unit_visibility() {
        if ($this->modulerecord->visible) {
            // activity is visible to everyone
            return false;
        }
        if (has_capability('moodle/course:viewhiddenactivities', $this->modulecontext)) {
            // user can view hidden activities
            return false;
        }
        // activity is currently hidden
        return get_string('activityiscurrentlyhidden');
    }

    function require_unit_grouping() {
        global $CFG;
        if (empty($CFG->enablegroupings)) {
            // this site doesn't use groupings
            return false;
        }
        if (empty($this->modulerecord->groupmembersonly) || empty($this->modulerecord->groupingid)) {
            // this QuizPort activity doesn't use groupings
            return false;
        }
        if (has_capability('mod/quizport:manage', $this->modulecontext)) {
            // user is a teacher/coursecreator (or admin)
            return false;
        }
        if (has_capability('moodle/site:accessallgroups', $this->modulecontext)) {
            // user has access to activities for all groupings
            return false;
        }
        if (groups_has_membership($this->modulerecord)) {
            // user has membership of one of the groups in the required grouping for this activity
            return false;
        }
        // user has no special capabilities and is not a member of the required grouping
        return get_string('groupmembersonlyerror', 'group');
    }

    function require_unit_inpopup() {
        global $CFG;

        $error = '';
        if ($this->unit->showpopup) {

            if (! $this->inpopup) {

                $target = "quizport{$this->unit->parentid}";
                $popupurl = $this->format_url('view.php', 'coursemoduleid', array('coursemoduleid'=>$this->modulerecord->id, 'inpopup'=>'true'));
                $openpopupurl = str_replace('&amp;', '&', substr($popupurl, strlen($CFG->wwwroot)));

                $popupoptions = implode(',', preg_grep('/^moodle/i', explode(',', $this->unit->popupoptions), PREG_GREP_INVERT));
                $openpopup = "openpopup('$openpopupurl','$target','{$popupoptions}')";
                $error .= "\n".'<script type="text/javascript">'."\n"."//<![CDATA[\n"."$openpopup;\n"."//]]>\n"."</script>\n";

                $onclick = "this.target='$target'; return $openpopup;";
                $link = "\n".'<a href="'.$popupurl.'" onclick="'.$onclick.'">'.format_string($this->activityrecord->name).'</a>'."\n";
                $error .= get_string('popupresource', 'resource').'<br />'.get_string('popupresourcelink', 'resource', $link);
            }
        }
        if ($error) {
            return $error;
        } else {
            return false;
        }
    }

    // check availability of unit

    function require_unit_availability() {
        if (($this->tab=='info' || $this->tab=='preview') && has_capability('mod/quizport:preview', $this->modulecontext)) {
            // teacher can always view the entry page
            return false;
        }
        if (! $error = $this->require_unit_quizzes()) {
            if (! $error = $this->require_availability('unit')) {
                if (! $error = $this->require_unit_entrycm()) {
                    $error = false;
                }
            }
        }
        return $error;
    }

    function require_unit_quizzes() {
        if (! $this->get_quizzes()) {
            // there are no quizzes in this unit
            return get_string('noquizzesinunit', 'quizport');
        }
        return false;
    }

    function require_unit_entrycm() {
        global $CFG, $USER;
        $error = false;

        if ($cm = quizport_get_cm($this->courserecord, $this->modulerecord, $this->unit->entrycm, 'entry')) {
            $href = $CFG->wwwroot.'/mod/'.$cm->mod.'/view.php?id='.$cm->cm;
            if ($cm->mod=='quizport') {
                $href .= '&amp;tab='.$this->tab;
                if ($this->inpopup) {
                    $href .= '&amp;inpopup='.$this->inpopup;
                }
            }
            if ($this->unit->entrygrade) {
                if (! function_exists('grade_get_grades')) {
                    // Moodle >= 1.9
                    require_once($CFG->dirroot.'/lib/gradelib.php');
                }
                if ($grades = grade_get_grades($this->courserecord->id, 'mod', $cm->mod, $cm->instance, $USER->id)) {
                    $grade = 0;
                    if (isset($grades->items[0]) && $grades->items[0]->grademax > 0) {
                        // this activity has a grade item
                        if (isset($grades->items[0]->grades[$USER->id])) {
                            $grade = $grades->items[0]->grades[$USER->id]->grade;
                        } else {
                            $grade = 0;
                        }
                        if ($grade < $this->unit->entrygrade) {
                            // either this user has not attempted the entry activity
                            // or their grade so far on the entry activity is too low
                            $a = (object)array(
                                'usergrade' => intval($grade),
                                'entrygrade' => $this->unit->entrygrade,
                                'entryactivity' => '<a href="'.$href.'">'.format_string(urldecode($cm->name)).'</a>'
                            );
                            $error = get_string('entrygradewarning', 'quizport', $a);
                        }
                    }
                }
            } else {
                // no grade, so test for "completion"
                switch ($cm->mod) {
                    case 'resource':
                        $table = 'log';
                        $select = "cmid=$cm->id AND userid=$this->userid AND action='view'";
                        break;
                    case 'lesson':
                        $table = 'lesson_grades';
                        $select = "userid=$this->userid AND lessonid==$cm->instance AND completed>0";
                        break;
                    default:
                        $table = '';
                        $select = '';
                }
                if ($table && $select && ! record_exists_select($table, $select)) {
                    // user has not viewed or completed this activity yet
                    $a = '<a href="'.$href.'">'.format_string(urldecode($cm->name)).'</a>';
                    $error = get_string('entrycompletionwarning', 'quizport', $a);
                }
            }
        }
        return $error;
    }

    // check access to a quiz

    function require_quiz_access() {
        return $this->require_access('quiz');
    }

    // check availability of quiz

    function require_quiz_availability() {
        return $this->require_availability('quiz');
    }

    // work out which quiz to do next

    function get_available_quizzes() {
        if (is_null($this->availablequizids)) {

            // initialize array of ids of quizzes which satisfy preconditions
            $this->availablequizids = array();
            $this->countavailablequizids = 0;

            // get quizzes, if any, in this unit
            if ($this->get_quizzes()) {
                $previousquizid = 0;
                foreach ($this->quizzes as $quiz) {
                    $ok = $this->check_conditions(QUIZPORT_CONDITIONTYPE_PRE, $quiz->id, $previousquizid);
                    if ($ok) {
                        // all preconditions were satisfied, so store quiz id
                        $previousquizid = $quiz->id;
                        $this->availablequizids[] = $quiz->id;
                        $this->countavailablequizids++;
                        // store the first (by sort order) available quizid
                        // (used when post-condition specifies MENUNEXTONE or MENUALLONE)
                        if (! $this->availablequizid && ! $this->get_cache_quizattempts($quiz->id)) {
                            $this->availablequizid = $quiz->id;
                        }
                    }
                }
            }
        }
        return $this->countavailablequizids;
    }

    function get_available_quiz($quizid) {
        if (! isset($this->cache_available_quiz[$quizid])) {
            $nextquizid = $this->check_conditions(QUIZPORT_CONDITIONTYPE_POST, $quizid, 0);
            $this->cache_available_quiz[$quizid] = $nextquizid;
        }
        return $this->cache_available_quiz[$quizid];
    }

    function get_cache_quizattempts($quizid) {
        global $DB;
        if (! isset($this->cache_quizattempts[$quizid])) {
            // get attempts at $quizid in this unumber
            $this->cache_quizattempts[$quizid] = $DB->get_records_select('quizport_quiz_attempts', "quizid=$quizid AND userid=$this->userid AND unumber=$this->unumber", null, 'resumestart DESC');
            $this->cache_quizattemptsusort[$quizid] = 'time_desc';
        }
        if (empty($this->cache_quizattempts[$quizid])) {
            return false;
        } else {
            return true;
        }
    }

    // IF this user has [attemptcount] [attempttype] attempts at [conditionquiz]
    //     AND each score is greater than [conditionscore<0] OR each score is no more than [conditionscore>0]
    //     AND duration of attempts greater than [attemptduration<0] OR duration of attempts is no more than [attemptduration>0]
    //     AND delay since attempts is greater than [attemptdelay<0] OR delay somce attempts is no more than [attemptdelay>0]
    // THEN condition is satisfied
    // ELSE condition is *not* satisfied
    //
    // All preconditions must be satisfied(i.e. AND)
    // Post condition with highest score is used (i.e. OR)
    //
    // Additionally, a postcondition is not satisfied if the preconditions for [nextquizid] are not satisfied

    function check_conditions($conditiontype, $quizid, $previousquizid) {
        global $DB;

        // set initial return value
        if ($conditiontype==QUIZPORT_CONDITIONTYPE_PRE) {
            $ok = true; // if there are no pre conditions, then the user can do this quiz
        } else {
            $ok = false; // if there are no post conditions, then there is no prescribed next quiz
        }

        switch (true) {
            case $this->unit->allowfreeaccess > 0: // required grade
                if ($this->get_maxunitattemptgrade() >= $this->unit->allowfreeaccess) {
                    return $ok;
                }
                break;
            case $this->unit->allowfreeaccess < 0: // number of completed attempts
                if ($this->get_unitcompleted() >= abs($this->unit->allowfreeaccess)) {
                    return $ok;
                }
                break;
        }

        if (! $conditions = $this->get_conditions($conditiontype, $quizid)) {
            // no conditions found for this quiz
            return $ok;
        }

        // make sure we have info on all quizzes
        if (is_null($this->quizzes)) {
            $this->get_quizzes();
        }

        // initialize sortorder
        $sortorder = -1;
        // Note: $condition->sortorder is always >=0 because
        // the sortorder field in the database is UNSIGNED

        foreach ($conditions as $condition) {

            if ($sortorder>=0) {
                // not the first condition, so check status of previous group of conditions
                if ($ok) {
                    if ($conditiontype==QUIZPORT_CONDITIONTYPE_POST) {
                        // previous post-condition was satisfied (=success!)
                        break;
                    } else if ($condition->sortorder != $sortorder) {
                        // previous group of pre-conditions were satisfied (=success!)
                        break;
                    }
                } else if ($condition->sortorder==$sortorder && $conditiontype==QUIZPORT_CONDITIONTYPE_PRE) {
                    // a previous pre-condition was not satisfied (=failure),
                    // so skip remaining pre-conditions with the same sortorder
                    continue;
                }
            }
            $sortorder = $condition->sortorder;

            // this quiz has pre/post conditions, so the default return value is FALSE
            //     for pre-conditions, this means the quiz cannot be done
            //     for post-conditions, this means no next quiz was specified
            // to return TRUE, we must find a condition that is satisfied
            $ok = false;

            switch ($condition->conditionquizid) {

                case QUIZPORT_CONDITIONQUIZID_SAME:
                    $conditionquizid = $quizid;
                    break;

                case QUIZPORT_CONDITIONQUIZID_PREVIOUS:
                    $conditionquizid = $previousquizid;
                    break;

                default:
                    // specific quiz id
                    $conditionquizid = $condition->conditionquizid;
            }

            if (! isset($this->quizzes[$conditionquizid])) {
                // condition quiz id is not valid !!
                continue;
            }

            if (! $this->get_cache_quizattempts($conditionquizid)) {
                // no attempts at the [conditionquiz], so condition cannot be satisifed
                continue;
            }

            $usort = &$this->cache_quizattemptsusort[$conditionquizid];
            $attempts = &$this->cache_quizattempts[$conditionquizid];

            if ($conditiontype==QUIZPORT_CONDITIONTYPE_PRE && $condition->attemptdelay) {
                // sort attempts by time DESC (most recent attempts first)
                $this->usort_attempts($attempts, $usort, 'time_desc');

                // get delay (=time elapsed) since most recent attempt
                $attempt = reset($attempts);
                $attemptdelay = ($this->time - $attempt->timemodified);

                if ($condition->attemptdelay<0 && $attemptdelay<$condition->attemptdelay) {
                    // not enough time elapsed, so precondition fails
                    return false;
                }
                if ($condition->attemptdelay>0 && $attemptdelay>$condition->attemptdelay) {
                    // too much time has elapsed, so precondition fails
                    return false;
                }
            }

            $attemptcount = 0;
            $attemptduration = 0;
            switch ($condition->attempttype) {

                case QUIZPORT_ATTEMPTTYPE_ANY:

                    if ($condition->attemptduration>0) {
                        // total time must not exceed attemptduration, so
                        // sort attempts by duration ASC (fastest attempts first)
                        $this->usort_attempts($attempts, $usort, 'duration_asc');
                    }
                    if ($condition->attemptduration<0) {
                        // total time must be at least attemptduration, so
                        // sort attempts by duration DESC (slowest attempts first)
                        $this->usort_attempts($attempts, $usort, 'duration_desc');
                    }
                    foreach ($attempts as $attempt) {
                        $ok = $this->check_condition_score($condition, $attempt->score);
                        if (! $ok) {
                            // score condition not satisfied
                            continue;
                        }

                        $attemptduration += $attempt->duration;
                        $attemptcount ++;

                        $ok = $this->check_condition_max($condition, $attemptcount, $attemptduration);
                        if (! $ok) {
                            // exceeded maximum time or attempt count (=> the condition has failed)
                            break;
                        }

                        $ok = $this->check_condition_min($condition, $attemptcount, $attemptduration);
                        if ($ok) {
                            // minimum time and count conditions satisfied (=> success!)
                            break;
                        }
                    }
                    break;

                case QUIZPORT_ATTEMPTTYPE_RECENT:

                    // sort attempts by time DESC (recent attempts first)
                    $this->usort_attempts($attempts, $usort, 'time_desc');

                    foreach ($attempts as $attempt) {
                        $ok = $this->check_condition_score($condition, $attempt->score);
                        if (! $ok) {
                            break;
                        }
                        $attemptduration += $attempt->duration;
                        $attemptcount ++;

                        $ok = $this->check_condition_max($condition, $attemptcount, $attemptduration);
                        if (! $ok) {
                            // exceeded maximum time or attempt count (=> the condition has failed)
                            break;
                        }

                        $ok = $this->check_condition_min($condition, $attemptcount, $attemptduration);
                        if ($ok) {
                            // minimum time and count conditions satisfied (=>success!)
                            break;
                        }
                    }
                    break;

                case QUIZPORT_ATTEMPTTYPE_CONSECUTIVE:

                    // sort attempts by time DESC
                    foreach ($attempts as $attempt) {
                        $ok = $this->check_condition_score($condition, $attempt->score);
                        if ($ok) {
                            $attemptduration += $attempt->duration;
                            $attemptcount ++;
                        } else {
                            // reset totals (but keep looping through attempts)
                            $attemptcount = 0;
                            $attemptduration = 0;
                        }

                        $ok = $this->check_condition_max($condition, $attemptcount, $attemptduration);
                        if (! $ok) {
                            // exceeded maximum time or attempt count (=> the condition has failed)
                            break;
                        }

                        $ok = $this->check_condition_min($condition, $attemptcount, $attemptduration);
                        if ($ok) {
                            // minimum time and count conditions satisfied (=>success!)
                            break;
                        }
                    }
                    break;

            } // end switch ($condition->attempttype)

            if ($conditiontype==QUIZPORT_CONDITIONTYPE_POST && $ok) {
                // this postcondition has been satisfied, so get nextquizid (or false, if there isn't one)
                $ok = $this->get_nextquizid($condition, $quizid, $previousquizid);
            }
        } // end foreach $conditions

        return $ok;
    } // end function : check_conditions

    function check_condition_score(&$condition, &$attemptscore) {
        if ($condition->conditionscore>0 && $attemptscore > $condition->conditionscore) {
            // maximum score exceeded
            return false;
        }
        if ($condition->conditionscore<0 && $attemptscore < $condition->abs_conditionscore) {
            // minimum score not reached
            return false;
        }
        // score condition is satisfied
        return true;
    }

    function check_condition_max(&$condition, $attemptcount, $attemptduration) {
        if ($condition->attemptcount>0 && $attemptcount > $condition->attemptcount) {
            // maximum number of attempts exceeded
            return false;
        }
        if ($condition->attemptduration>0 && $attemptduration > $condition->attemptduration) {
            // maximum time exceeded
            return false;
        }
        // "max" conditions are satisfied
        return true;
    }

    function check_condition_min(&$condition, $attemptcount, $attemptduration) {
        if ($condition->attemptcount<0 && $attemptcount < $condition->abs_attemptcount) {
            // minimum number of attempts not reached
            return false;
        }
        if ($condition->attemptduration<0 && $attemptduration < $condition->abs_attemptduration) {
            // minimum time not reached
            return false;
        }
        // "min" conditions are satisfied
        return true;
    }

    function usort_attempts(&$attempts, &$usort, $newusort) {
        if ($usort == $newusort) {
            // do nothing - attempts are already in order
        } else {
            $usort = $newusort;
            // "uasort" maintains the id => record correlation (where "usort" does not)
            uasort($attempts, 'quizport_usort_'.$usort);
        }
    }

    function get_nextquizid(&$condition, $quizid, $previousquizid) {
        global $DB;

        // initialize recturn value
        $nextquizid = 0;

        $ids = array();
        $sql = '';
        $skip = 0;
        $random = false;

        switch ($condition->nextquizid) {

            case QUIZPORT_CONDITIONQUIZID_SAME:
                $ids[] = $quizid;
                break;

            case QUIZPORT_CONDITIONQUIZID_PREVIOUS:
                $sql = "
                    SELECT id, sortorder
                    FROM {quizport_quizzes}
                    WHERE unitid=$this->unitid AND sortorder<".$this->quizzes[$quizid]->sortorder."
                    ORDER BY sortorder DESC
                ";
                break;

            case QUIZPORT_CONDITIONQUIZID_NEXT1:
            case QUIZPORT_CONDITIONQUIZID_NEXT2:
            case QUIZPORT_CONDITIONQUIZID_NEXT3:
            case QUIZPORT_CONDITIONQUIZID_NEXT4:
            case QUIZPORT_CONDITIONQUIZID_NEXT5:
                // skip is the number of quizzes to skip (next1=0, next2=1, etc)
                // remember nextquizid and the NEXT constants are all negative
                $skip = (QUIZPORT_CONDITIONQUIZID_NEXT1 - $condition->nextquizid);
                $sql = "
                    SELECT id, sortorder
                    FROM {quizport_quizzes}
                    WHERE unitid=$this->unitid AND sortorder>".$this->quizzes[$quizid]->sortorder."
                    ORDER BY sortorder ASC
                ";
                break;

            case QUIZPORT_CONDITIONQUIZID_UNSEEN: // no attempts
                $sql = "
                    SELECT id, sortorder FROM {quizport_quizzes} q
                    LEFT JOIN (
                        # ids of quizzes attempted by this user (in this unit attempt)
                        SELECT DISTINCT quizid FROM {quizport_quiz_attempts}
                        WHERE quizid IN (
                            # quizzes in this unit
                            SELECT id FROM {quizport_quizzes} WHERE unitid=$this->unitid
                        ) AND unumber=$this->unumber AND userid=$this->userid
                    ) a ON q.id=a.quizid
                    WHERE unitid=$this->unitid AND a.quizid IS NULL
                    ORDER BY sortorder ASC
                ";
                $random = true;
                break;

            case QUIZPORT_CONDITIONQUIZID_UNANSWERED: // no responses
                $sql = "
                    SELECT id, sortorder FROM {quizport_quizzes} q
                    LEFT JOIN (
                        # quizzes with attempts that have responses
                        SELECT DISTINCT quizid FROM {quizport_quiz_attempts}
                        WHERE id IN (
                            # attempts that have responses
                            SELECT DISTINCT attemptid FROM {quizport_responses}
                            WHERE attemptid IN (
                                # attempts (on quizzes in this unit)
                                SELECT id FROM {quizport_quiz_attempts}
                                WHERE quizid IN (
                                    # quizzes in this unit
                                    SELECT id FROM {quizport_quizzes} WHERE unitid=$this->unitid
                                ) AND unumber=$this->unumber AND userid=$this->userid
                            )
                        )
                    ) a ON q.id=a.quizid
                    WHERE unitid=$this->unitid AND a.quizid IS NULL
                    ORDER BY q.sortorder ASC
                ";
                $random = true;
                break;

            case QUIZPORT_CONDITIONQUIZID_INCORRECT: // score < 100%
                $sql = "
                    SELECT q.id, q.sortorder FROM {quizport_quizzes} q
                    LEFT JOIN (
                        SELECT DISTINCT quizid FROM {quizport_quiz_scores}
                        WHERE quizid IN (
                            # quizzes in this unit
                            SELECT id FROM {quizport_quizzes} WHERE unitid=$this->unitid
                        ) AND score=100 AND unumber=$this->unumber AND userid=$this->userid
                    ) qs ON q.id=qs.quizid
                    WHERE unitid=$this->unitid AND qs.quizid IS NULL
                ";
                $random = true;
                break;

            case QUIZPORT_CONDITIONQUIZID_RANDOM:
                $sql = "
                    SELECT q.id, q.sortorder
                    FROM {quizport_quizzes} q
                    WHERE q.unitid=$this->unitid
                    ORDER BY sortorder ASC
                ";
                $random = true;
                break;

            case QUIZPORT_CONDITIONQUIZID_MENUALL:
            case QUIZPORT_CONDITIONQUIZID_MENUNEXT:
                $nextquizid = $condition->nextquizid;
                break;

            default:
                $ids[] = $condition->nextquizid;
                break;
        } // end switch : $condition->nextquizid

        if ($sql) {
            if ($records = $DB->get_records_sql($sql)) {
                $ids = array_keys($records);
            }
        }

        if ($i_max = count($ids)) {

            // set capability, if necessary
            static $has_capability_preview = null;
            if (is_null($has_capability_preview)) {
                $has_capability_preview = has_capability('mod/quizport:preview', $this->modulecontext);
            }

            $i = 0;
            while ($i<$i_max) {
                if ($random) {
                    $i = $this->random_number(0, $i_max-1);
                }
                if ($has_capability_preview) {
                    // a teacher: don't check pre-conditions
                    $ok = true;
                } else {
                    // a student: always check pre-conditions on candidate for next quiz
                    // (and pass the current $quizid to be the $previousquizid in order to do the checking)
                    $ok = $this->check_conditions(QUIZPORT_CONDITIONTYPE_PRE, $ids[$i], $quizid);
                }
                if ($ok) {
                    if ($skip > 0) {
                        $skip--;
                    } else {
                        $nextquizid = $ids[$i];
                        break; // nextquizid has been found
                    }
                }
                if ($random) {
                    // remove this id from the $ids array
                    $ids = array_splice($ids, $i, 1);
                    $i_max--;
                    $i = 0;
                } else {
                    $i++;
                }
            }
        }

        return $nextquizid;
    }

    function random_number($min=0, $max=RAND_MAX) {
        static $rand;
        if (! isset($rand)) {
            // get random number functons ("mt" functions are available starting PHP 4.2)
            $rand = function_exists('mt_rand') ? 'mt_rand' : 'rand';
            $srand = function_exists('mt_srand') ? 'mt_srand' : 'srand';

            // seed the random number generator
            list($usec, $sec) = explode(' ', microtime());
            $srand((float) $sec + ((float) $usec * 100000));
        }
        return $rand($min, $max);
    }

    function regrade_selected_attempts() {
        if (! $userfilter = $this->get_userfilter('')) {
            return false; // no users selected
        }

        if (! $selected = optional_param('selected', 0, PARAM_INT)) {
            return false; // no attempts select
        }

        if (! $confirmed = optional_param('confirmed', 0, PARAM_INT)) {
            return false; // regrade is not confirmed
        }

        // clean the array of selected records (i.e. only alow units and quizzes that this user is allowed to regrade)
        list($quizports, $units, $quizzes, $quizattempts) = $this->clean_selected($selected, 'mod/quizport:grade');

        // regrade quizzes, units and quizports
        $this->regrade_selected_quizzes($selected, $quizports, $units, $quizzes, $userfilter);
    }

    function delete_selected_attempts($status=0) {
        global $DB;

        if (! $userfilter = $this->get_userfilter('')) {
            return false; // no users selected
        }

        if (! $selected = optional_param('selected', 0, PARAM_INT)) {
            return false; // no attempts select
        }

        if (! $confirmed = optional_param('confirmed', 0, PARAM_INT)) {
            return false; // delete is not confirmed
        }

        // we are going to return some totals of how many records were deleted
        $this->deleted = (object)array(
            'quizattempts'=>0, 'quizscores'=>0, 'unitattempts'=>0, 'unitgrades'=>0, 'total'=>0
        );

        list($quizports, $units, $quizzes, $quizattempts) = $this->clean_selected($selected, 'mod/quizport:deleteattempts');

        if (count($quizports)) {
            $parentfilter = 'parentid IN ('.implode(',', array_keys($quizports)).')';
        } else {
            $parentfilter = 'parentid IN (SELECT id FROM {quizport} WHERE course='.$this->courserecord->id.')';
        }
        if (count($units)) {
            $unitfilter = 'unitid IN ('.implode(',', array_keys($units)).')';
        } else {
            $unitfilter = 'unitid IN (SELECT id FROM {quizport_units}  WHERE parenttype=0 AND '.$parentfilter.')';
        }
        if (count($quizzes)) {
            $quizfilter = 'quizid IN ('.implode(',', array_keys($quizzes)).')';
        } else {
            $quizfilter = 'quizid IN (SELECT id FROM {quizport_quizzes} WHERE '.$unitfilter.')';
        }

        $select = $this->get_selected_sql($selected, $units, $quizzes, $quizattempts);

        if ($select) {
            $select = $userfilter.' AND '. $select;
            if ($status) {
                $select .= " AND status=$status";
            }
           // remove all quiz_attempts by users in $userfilter
            if ($records = $DB->get_records_select('quizport_quiz_attempts', $select, null, 'id', 'id, id AS quizattemptid')) {
                $select = 'id IN ('.implode(',', array_keys($records)).')';
                if (! $DB->delete_records_select('quizport_quiz_attempts', $select)) {
                    print_error('error_deleterecords', 'quizport', 'quizport_quiz_attempts');
                }
                // update totals
                $this->deleted->quizattempts = count($records);
                $this->deleted->total += $this->deleted->quizattempts;
            }
        }

        // remove all quiz_scores which have no quiz attempts by users in $userfilter
        $select = "id IN (
            SELECT qs.id FROM {quizport_quiz_scores} qs
            LEFT JOIN (
                SELECT id,quizid,unumber,userid,score FROM {quizport_quiz_attempts}
                WHERE $userfilter AND $quizfilter
            ) qa ON qs.quizid=qa.quizid AND qs.unumber=qa.unumber AND qs.userid=qa.userid
            WHERE qs.$userfilter AND qs.$quizfilter AND qa.score IS NULL
        )";

        if ($records = $DB->get_records_select('quizport_quiz_scores', $select, null, 'id', 'id, id AS quizscoreid')) {
            $select = 'id IN ('.implode(',', array_keys($records)).')';
            if (! $DB->delete_records_select('quizport_quiz_scores', $select)) {
                print_error('error_deleterecords', 'quizport', 'quizport_quiz_scores');
            }
            // update totals
            $this->deleted->quizscores = count($records);
            $this->deleted->total += $this->deleted->quizscores;
        }

        // remove all unit_attempts which have no quiz scores by users in $userfilter
        $select = "id IN (
            SELECT ua.id FROM {quizport_unit_attempts} ua
            LEFT JOIN (
                SELECT quizid,unumber,userid,q.unitid,score FROM {quizport_quiz_scores}
                LEFT JOIN (
                    SELECT id,unitid FROM {quizport_quizzes} WHERE $unitfilter
                ) q ON quizid=q.id
                WHERE $userfilter AND $quizfilter
            ) qs ON ua.unitid=qs.unitid AND ua.unumber=qs.unumber AND ua.userid=qs.userid
            WHERE ua.$userfilter AND ua.$unitfilter AND qs.score IS NULL
        )";

        if ($records = $DB->get_records_select('quizport_unit_attempts', $select, null, 'id', 'id, id AS unitattemptid')) {
            $select = 'id IN ('.implode(',', array_keys($records)).')';
            if (! $DB->delete_records_select('quizport_unit_attempts', $select)) {
                print_error('error_deleterecords', 'quizport', 'quizport_unit_attempts');
            }
            $this->deleted->unitattempts = count($records);
            $this->deleted->total += $this->deleted->unitattempts;
        }

        // remove all unit_grades which have no unit_attempts by users in $userfilter
        $select = "id IN (
            SELECT ug.id FROM {quizport_unit_grades} ug
            LEFT JOIN (
                SELECT userid,unitid,u.parenttype,u.parentid,grade FROM {quizport_unit_attempts}
                LEFT JOIN (
                    SELECT id,parenttype,parentid FROM {quizport_units}
                    WHERE parenttype=0 AND $parentfilter
                ) u ON unitid=u.id
                WHERE $userfilter AND $unitfilter
            ) ua ON ug.parenttype=ua.parenttype AND ug.parentid=ua.parentid AND ug.userid=ua.userid
            WHERE ug.$userfilter AND ug.parenttype=0 AND ug.$parentfilter AND ua.grade IS NULL
        )";

        if ($records = $DB->get_records_select('quizport_unit_grades', $select, null, 'id', 'id, id AS unitgradeid')) {
            $select = 'id IN ('.implode(',', array_keys($records)).')';
            if (! $DB->delete_records_select('quizport_unit_grades', $select)) {
                print_error('error_deleterecords', 'quizport', 'quizport_unit_grades');
            }
            $this->deleted->unitgrades = count($records);
            $this->deleted->total += $this->deleted->unitgrades;
        }

        // regrade quizzes, units and quizports
        $this->regrade_selected_quizzes($selected, $quizports, $units, $quizzes, $userfilter);

        return $this->deleted;
    }

    function clean_selected(&$selected, $capability) {
        // we are expecting the "selected" array to be something like this:
        //     selected[unitid][unumber][quizid][qnumber][quizattemptid]
        // unumber and qnumber maybe zero
        // quizattemptid maybe be missing
        global $CFG, $DB;

        // arrays to hold ids of records this user wants to delete
        $coursemodules = array(); // course_modules (Moodle 1.6 only)
        $quizattemptids = array(); // quizport_quiz_attempts
        $quizids = array(); // quizport_quizzes
        $unitids = array(); // quizport_units
        $quizportids = array(); // quizport

        // get ids of records this user wants to delete (tidy up $selected where necessary)
        foreach ($selected as $unitid => $unumbers) {
            if (! $unumbers) {
                unset($selected[$unitid]);
                continue;
            }
            if (! $unitid) {
                // you could add ids for all units in this course here, but it is not necessary
                unset($selected[$unitid]);
                continue;
            }
            $unitids[] = $unitid;
            if (is_array($unumbers)) {
                foreach ($unumbers as $unumber => $unumberdetails) {
                    if (! $unumberdetails) {
                        unset($selected[$unitid][$unumber]);
                        continue;
                    }
                    if (is_array($unumberdetails)) {
                        foreach ($unumberdetails as $quizid => $qnumbers) {
                            if (! $qnumbers) {
                                unset($selected[$unitid][$unumber][$quizid]);
                                continue;
                            }
                            if (! $quizid) {
                                // you could add ids for all quizzes in this unit here, but it is not necessary
                                unset($selected[$unitid][$unumber][$quizid]);
                                continue;
                            }
                            if (is_array($qnumbers)) {
                                $quizids[] = $quizid;
                                foreach ($qnumbers as $qnumber => $qnumberdetails) {
                                    if (! $qnumberdetails) {
                                        unset($selected[$unitid][$unumber][$quizid][$qnumber]);
                                        continue;
                                    }
                                    if (is_array($qnumberdetails)) {
                                        foreach ($qnumberdetails as $quizattemptid => $delete) {
                                            if (! $delete) {
                                                unset($selected[$unitid][$unumber][$quizid][$qnumber][$quizattemptid]);
                                                continue;
                                            }
                                            $quizattemptids[] = $quizattemptid;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } // end foreach ($selected)

        // get requested quiz attempts
        if (empty($quizattemptids)) {
            $quizattempts = array();
        } else {
            $fields = 'id,quizid';
            $select = 'id IN ('.implode(',', array_unique($quizattemptids)).')';
            if (! $quizattempts = $DB->get_records_select('quizport_quiz_attempts', $select, null, 'id', $fields)) {
                $quizattempts = array();
            }
            // extract quiz ids from requested quiz attempts
            foreach ($quizattempts as $id=>$quizattempt) {
                $quizids[] = $quizattempt->quizid;
            }
        }

        // get requested quizzes
        if (empty($quizids)) {
            $quizzes = array();
        } else {
            $fields = 'id,unitid,timelimit,allowresume,attemptlimit,scoremethod,scoreignore,scorelimit,scoreweighting';
            $select = 'id IN ('.implode(',', array_unique($quizids)).')';
            if (! $quizzes = $DB->get_records_select('quizport_quizzes', $select, null, 'id', $fields)) {
                $quizzes = array();
            }
            // extract unit ids from requested quizzes
            foreach ($quizzes as $quizid => $quiz) {
                $unitids[] = $quiz->unitid;
            }
        }

        // get requested units and quizportids
        if (empty($unitids)) {
            $units = array();
        } else {
            $fields = 'id,parenttype,parentid,timelimit,allowresume,attemptlimit,attemptgrademethod,grademethod,gradeignore,gradelimit,gradeweighting';
            $select = 'id IN ('.implode(',', array_unique($unitids)).') AND parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY;
            if (! $units = $DB->get_records_select('quizport_units', $select, null, 'id', $fields)) {
                $units = array();
            }
            // extract quizport ids from requested units
            foreach ($units as $unitid=>$unit) {
                $quizportids[] = $unit->parentid;
            }
        }

        // select requested course modules for which this user is allowed to delete attempts at QuizPorts
        if (count($quizportids)) {

            if ($CFG->majorrelease<=1.6) {
                $select = "module=(SELECT id FROM {modules} WHERE name='quizport') AND instance IN (".implode(',', $quizportids).')';
                $coursemodules = $DB->get_records_select('course_modules', $select, null, 'id', 'id,instance');
            }

            if ($modinfo = unserialize($this->courserecord->modinfo)) {
                foreach ($modinfo as $cmid => $mod) {
                    // Note: $mod->id is the quizport id, $mod->cm is the course_modules id
                    if ($mod->mod=='quizport') {
                        if (isset($mod->id)) {
                            // Moodle 1.7 and later
                            $quizportid = $mod->id;
                        } else {
                            // Moodle 1.6 and earlier
                            $quizportid = $coursemodules[$cmid]->instance;
                        }
                        if (in_array($quizportid, $quizportids) && has_capability($capability, get_context_instance(CONTEXT_MODULE, $cmid))) {
                            // user can delete attempts, so save the quizport id
                            // we don't need to get the full quizport/coursemodule record
                            $coursemodules[$cmid] = true;
                            $quizports[$quizportid] = (object)array(
                                // these fields are required by quizport_grade_item_update() in "mod/quizport/lib.php"
                                'id'=>$quizportid, 'cmidnumber'=>$cmid, 'course'=>$this->courserecord->id, 'name'=>format_string(urldecode($mod->name))
                            );
                        }
                    }
                }
            }
        }

        // we don't need these anymore
        unset($coursemodules);
        unset($quizportids);
        unset($unitids);
        unset($quizids);
        unset($quizattemptids);

        // $coursemodules now holds only records for QuizPort activities for which this user has the required $capability
        // $quizports holds the corresponding quizport records

        // remove units that this user is not allowed to touch
        foreach ($units as $unitid=>$unit) {
            if (empty($quizports[$unit->parentid])) {
                unset($units[$unitid]);
            } else {
                $units[$unitid]->quizzes = array();
                $units[$unitid]->userids = array();

                // transfer gradelimit and gradeweighting to $quizport
                // (required for quizport_get_user_grades() in "mod/quizport/lib.php")
                $quizports[$unit->parentid]->gradelimit = $unit->gradelimit;
                $quizports[$unit->parentid]->gradeweighting = $unit->gradeweighting;
            }
        }

        // remove quizzes that this user is not allowed to touch
        foreach ($quizzes as $quizid=>$quiz) {
            if (empty($units[$quiz->unitid])) {
                unset($quizzes[$quizid]);
            }
            $quizzes[$quizid]->userids = array();
        }

        // remove quiz attempts that this user is not allowed to delete
        foreach ($quizattempts as $id=>$quizattempt) {
            if (empty($quizzes[$quizattempt->quizid])) {
                unset($quizattempts[$id]);
            }
        }

        return array(&$quizports, &$units, &$quizzes, &$quizattempts);
    }

    function get_selected_sql(&$selected, &$units, &$quizzes, &$quizattempts) {
        $unitid_filters = array();
        foreach ($selected as $unitid => $unumbers) {
            if (! $unitid) {
                continue;
            }
            if (empty($units[$unitid])) {
                unset($selected[$unitid]);
                continue; // invalid $unitid - shouldn't happen
            }
            $unitid_filter = array();
            // the unitid filter to select quizids for this unitid will be added later
            // because it is only required if specific quizids have not been specified
            $add_unitid_filter = true;
            if (is_array($unumbers)) {
                $unumber_filters = array();
                foreach ($unumbers as $unumber => $unumberdetails) {
                    $unumber_filter = array();
                    if ($unumber) {
                        $unumber_filter[] = "unumber=$unumber";
                    }
                    if (is_array($unumberdetails)) {
                        $quizid_filters = array();
                        foreach ($unumberdetails as $quizid => $qnumbers) {
                            if (! $quizid) {
                                continue;
                            }
                            if (empty($quizzes[$quizid])) {
                                unset($selected[$unitid][$unumber][$quizid]);
                                continue; // invalid $quizid - shouldn't happen
                            }
                            $quizid_filter = array();
                            $quizid_filter[] = "quizid=$quizid";
                            $add_unitid_filter = false; // not required if we have a quizid
                            if (is_array($qnumbers)) {
                                $qnumber_filters = array();
                                foreach ($qnumbers as $qnumber => $qnumberdetails) {
                                    $qnumber_filter = array();
                                    if ($qnumber) {
                                        $qnumber_filter[] = "qnumber=$qnumber";
                                    }
                                    if (is_array($qnumberdetails)) {
                                        $id_filters = array();
                                        foreach ($qnumberdetails as $quizattemptid => $delete) {
                                            if (! $quizattemptid) {
                                                continue;
                                            }
                                            if (empty($quizattempts[$quizattemptid])) {
                                                unset($selected[$unitid][$unumber][$quizid][$qnumber][$quizattemptid]);
                                                continue; // invalid quiz attempt id - shouldn't happen
                                            }
                                            $id_filters[] = "id=$quizattemptid";
                                        }
                                       switch (count($id_filters)) {
                                            case 0: break;
                                            case 1: $qnumber_filter[] = 'id='.$id_filters[0]; break;
                                            default: $qnumber_filter[] = 'id IN ('.implode(',', $id_filters).')';
                                        }
                                    }
                                    switch (count($qnumber_filter)) {
                                        case 0: break;
                                        case 1: $qnumber_filters[] = $qnumber_filter[0]; break;
                                        default: $qnumber_filters[] = '('.implode(' AND ', $qnumber_filter).')';
                                    }
                                }
                                switch (count($qnumber_filters)) {
                                    case 0: break;
                                    case 1: $quizid_filter[] = $qnumber_filters[0]; break;
                                    default: $quizid_filter[] = '('.implode(' OR ', $qnumber_filters).')';
                                }
                            }

                            switch (count($quizid_filter)) {
                                case 0: break;
                                case 1: $quizid_filters[] = $quizid_filter[0]; break;
                                default: $quizid_filters[] = '('.implode(' AND ', $quizid_filter).')';
                            }
                        }
                        switch (count($quizid_filters)) {
                            case 0: break;
                            case 1: $unumber_filter[] = $quizid_filters[0];break;
                            default: $unumber_filter[] = '('.implode(' OR ', $quizid_filters).')';
                        }
                    }
                    switch (count($unumber_filter)) {
                        case 0: break;
                        case 1: $unumber_filters[] = $unumber_filter[0]; break;
                        default: $unumber_filters[] = '('.implode(' AND ', $unumber_filter).')';
                    }
                }
                switch (count($unumber_filters)) {
                    case 0: break;
                    case 1: $unitid_filter[] = $unumber_filters[0]; break;
                    default: $unitid_filter[] = '('.implode(' OR ', $unumber_filters).')';
                }
            }
            if ($add_unitid_filter) {
                // prepend filter to select only quizids for this unitid
                array_unshift($unitid_filter, "quizid IN (SELECT id FROM {quizport_quizzes} WHERE unitid=$unitid)");
            }
            switch (count($unitid_filter)) {
                case 0: break;
                case 1: $unitid_filters[] = $unitid_filter[0]; break;
                default: $unitid_filters[] = '('.implode(' AND ', $unitid_filter).')';
            }
        }
        switch (count($unitid_filters)) {
            case 0: return ''; // nothing to delete
            case 1: return $unitid_filters[0];
            default: return '('.implode(' OR ', $unitid_filters).')';
        }
    }

    function get_unumbers(&$units, &$quizzes, &$userfilter) {
        global $CFG, $DB;
        $select = "quizid IN (SELECT id FROM {quizport_quizzes} WHERE unitid IN (".implode(',', array_keys($units)).'))';
        if ($userfilter) {
            $select .= " AND $userfilter";
        }
        $fields = 'id,userid,unumber,quizid,qnumber';
        $sort = 'userid,unumber,quizid,qnumber';
        if ($quizattempts = $DB->get_records_select('quizport_quiz_attempts', $select, null, $sort, $fields)) {
            foreach ($quizattempts as $id=>$quizattempt) {
                if (empty($quizzes[$quizattempt->quizid])) {
                    continue; // shouldn't happen !!
                }
                $quizid = $quizattempt->quizid;
                $userid = $quizattempt->userid;
                $unumber = $quizattempt->unumber;
                $unitid = $quizzes[$quizid]->unitid;

                if (empty($quizzes[$quizid]->userids[$userid])) {
                    $quizzes[$quizid]->userids[$userid] = new stdClass;
                    $quizzes[$quizid]->userids[$userid]->unumbers = array();
                }
                $quizzes[$quizid]->userids[$userid]->unumbers[$unumber] = true;

                if (empty($units[$unitid]->userids[$userid])) {
                    $units[$unitid]->userids[$userid] = new stdClass;
                    $units[$unitid]->userids[$userid]->unumbers = array();
                }
                $units[$unitid]->userids[$userid]->unumbers[$unumber] = true;
            }
        }
    }

    function regrade_selected_quizzes(&$selected, &$quizports, &$units, &$quizzes, &$userfilter) {
        $get_unumbers = true;
        if (preg_match_all('/\d+/', $userfilter, $userids)) {
            foreach ($selected as $unitid => $unumbers) {
                if (is_array($unumbers)) {
                    foreach ($unumbers as $unumber => $unumberdetails) {
                        if (is_array($unumberdetails)) {
                            foreach ($unumberdetails as $quizid => $qnumbers) {
                                if ($unumber) {
                                    foreach ($userids[0] as $userid) {
                                        $this->regrade_attempts('quiz', $quizzes[$quizid], $unumber, $userid);
                                    }
                                } else {
                                    if ($get_unumbers) {
                                        $this->get_unumbers($units, $quizzes, $userfilter);
                                        $get_unumbers = false;
                                    }
                                    foreach (array_keys($quizzes[$quizid]->userids) as $temp_userid) {
                                        foreach(array_keys($quizzes[$quizid]->userids[$temp_userid]->unumbers) as $temp_unumber) {
                                            $this->regrade_attempts('quiz', $quizzes[$quizid], $temp_unumber, $temp_userid);
                                        }
                                    }
                                }
                            }
                        }
                        if ($unumber) {
                            foreach ($userids[0] as $userid) {
                                $this->regrade_unitattempt($units[$unitid], $unumber, $userid, $quizzes);
                            }
                        } else {
                            if ($get_unumbers) {
                                $this->get_unumbers($units, $quizzes, $userfilter);
                                $get_unumbers = false;
                            }
                            foreach (array_keys($units[$unitid]->userids) as $temp_userid) {
                                foreach(array_keys($units[$unitid]->userids[$temp_userid]->unumbers) as $temp_unumber) {
                                    $this->regrade_unitattempt($units[$unitid], $temp_unumber, $temp_userid, $quizzes);
                                }
                            }
                        } // end if $unumber
                    } // end foreach $unumber
                } // end if is_array($unumbers)
                foreach($userids[0] as $userid) {
                    $this->regrade_attempts('unit', $units[$unitid], 0, $userid);
                    quizport_update_grades($quizports[$units[$unitid]->parentid], $userid);
                }
            } // end foreach ($selected)
        }
    }

    function regrade_attempts($type, $record=null, $unumber=null, $userid=null) {
        // combine quiz attempts into a single quiz score
        // combine unit attempts into a single unit grade
        global $DB;

        if ($type=='unit') {
            $grade = 'grade';
        } else {
            $grade = 'score';
        }
        $grademethod = $grade.'method';
        $gradeignore = $grade.'ignore';
        $gradelimit = $grade.'limit';

        if (is_null($record)) {
            $record = &$this->$type;
        }
        if (is_null($userid)) {
            $userid = $this->userid;
            $thisuser = true;
        } else {
            $thisuser = false;
        }

        // prepare sql
        if ($type=='unit') {
            $attemptselect = "unitid=$record->id";
            $gradeselect = "parenttype=$record->parenttype AND parentid=$record->parentid";
            $timefield = 'timemodified';
        } else {
            if (is_null($unumber)) {
                $unumber = $this->unumber;
            }
            $attemptselect = "quizid=$record->id AND unumber=$unumber";
            $gradeselect = "quizid=$record->id AND unumber=$unumber";
            $timefield = 'resumestart';
        }

        if ($userid) {
            $attemptselect .= " AND userid=$userid";
        }

        if ($gradeignore) {
            $attemptselect .= " AND NOT ($grade=0 AND status=".QUIZPORT_STATUS_ABANDONED.")";
        }

        if ($record->$grademethod==QUIZPORT_GRADEMETHOD_AVERAGE || $record->$gradelimit<100) {
            $precision = 1;
        } else {
            $precision = 0;
        }
        $multiplier = $record->$gradelimit / 100;

        // set the SQL string to determine the $usergrade
        switch ($record->$grademethod) {
            case QUIZPORT_GRADEMETHOD_HIGHEST:
                $usergrade = "ROUND(MAX($grade) * $multiplier, $precision)";
                break;
            case QUIZPORT_GRADEMETHOD_AVERAGE:
                // the 'AVG' function skips abandoned quizzes, so use SUM(score)/COUNT(score)
                $usergrade = "ROUND(AVG($grade) * $multiplier, $precision)";
                break;
            case QUIZPORT_GRADEMETHOD_FIRST:
                $usergrade = 'MIN('.$DB->sql_concat($timefield, "'_'", "ROUND($grade * $multiplier, $precision)").')';
                break;
            case QUIZPORT_GRADEMETHOD_LAST:
                $usergrade = 'MAX('.$DB->sql_concat($timefield, "'_'", "ROUND($grade * $multiplier, $precision)").')';
                break;
            default:
                return false; // invalid score/grade mathod
        }

        $fields = "userid AS id, $usergrade AS $grade, COUNT($grade) AS countattempts, MAX(status) AS maxstatus, MIN(status) AS minstatus, SUM(duration) AS duration";
        $table = '{quizport_'.$type.'_attempts}';

        if ($aggregates = $DB->get_records_sql("SELECT $fields FROM $table WHERE $attemptselect GROUP BY userid")) {

            if ($record->$grademethod==QUIZPORT_GRADEMETHOD_FIRST || $record->$grademethod==QUIZPORT_GRADEMETHOD_LAST) {
                // remove left hand characters in $usergrade (up to and including the underscore)
                foreach ($aggregates as $userid=>$aggregate) {
                    $pos = strpos($aggregate->$grade, '_') + 1;
                    $aggregates[$userid]->$grade = substr($aggregate->$grade, $pos);
                }
            }

            $gradetable = 'quizport_'.$type.'_'.$grade.'s';
            foreach ($aggregates as $userid=>$aggregate) {

                // set status of quiz score or unit grade
                $status = 0;

                // if current user has just completed a quiz attempt
                // try to set quiz score status from post-conditions
                if ($thisuser && $type=='quiz' && $this->get_lastattempt('quiz')) {
                    $nextquizid = $this->get_available_quiz($this->lastquizattempt->quizid);
                    if ($nextquizid==QUIZPORT_CONDITIONQUIZID_ENDOFUNIT) {
                        // post condition for last quiz attempt specifies end of unit
                        $status = QUIZPORT_STATUS_COMPLETED;
                        $this->unitattempt->status = $status;
                        if (! $DB->set_field('quizport_unit_attempts', 'status', $status, array('id' => $this->unitattempt->id))) {
                            print_error('error_updaterecord', 'quizport', '', 'quizport_unit_attempts');
                        }
                    } else if ($nextquizid==$record->id) {
                        // post conditions specify this quiz is to be repeated
                        $status = QUIZPORT_STATUS_INPROGRESS;
                    }
                }

                if ($status==0) {
                    if ($aggregate->maxstatus==QUIZPORT_STATUS_COMPLETED) {
                        // at least one attempt is completed
                        $status = QUIZPORT_STATUS_COMPLETED;
                    } else if ($aggregate->minstatus==QUIZPORT_STATUS_INPROGRESS && $record->allowresume) {
                        // at least one attempt can be resumed
                        $status = QUIZPORT_STATUS_INPROGRESS;
                    } else if ($record->attemptlimit==0 || $aggregate->countattempts < $record->attemptlimit) {
                        // new attempt can be started
                        $status = QUIZPORT_STATUS_INPROGRESS;
                    } else if ($aggregate->minstatus==QUIZPORT_STATUS_TIMEDOUT && $aggregate->maxstatus==QUIZPORT_STATUS_TIMEDOUT) {
                        // all attempts are timed out and no new attempts can be started
                        $status = QUIZPORT_STATUS_TIMEDOUT;
                    } else {
                        // an assortment of inprogress, timedout and abandoned attempts
                        // no attempts can be resumed and no new attempt can be started
                        $status = QUIZPORT_STATUS_ABANDONED;
                    }
                }

                $typegrade = "$type$grade"; // quizscore or unitgrade

                // update/add grade record
                if ($graderecord = $DB->get_record_select($gradetable, $gradeselect." AND userid=$userid")) {
                    $graderecord->$grade = round($aggregate->$grade);
                    $graderecord->status = $status;
                    $graderecord->duration = $aggregate->duration;
                    if (! $DB->update_record($gradetable, $graderecord)) {
                        print_error('error_updaterecord', 'quizport', '', $table);
                    }
                    if ($thisuser) {
                        $this->$typegrade = &$graderecord;
                    }
                } else {
                    // grade record not found - should not happen !
                    $create_grade = 'create_'.$typegrade;
                    $this->$create_grade($aggregate->$grade, $status, $aggregate->duration, $record->id, $unumber, $userid);
                }
            }
        }
    }

    function regrade_unitattempt($unit=null, $unumber=null, $userid=null, $quizzes=null) {
        // combine quiz scores into a single unit attempt score

        // Note: $quizzes contains only the quizzes that are to be regraded
        // i.e. it does NOT contain all the quizzes in the unit, so it cannot be relied on to calculate equalweighting
        global $DB;

        // maintain a cache of grading/scoring info for each unit
        static $units = array();

        if (is_null($unit)) {
            $unit = $this->unit;
        }
        if (array_key_exists($unit->id, $units)) {
            $get_unit_quizzes = false;
        } else {
            $get_unit_quizzes = true;
        }
        if (is_null($unumber)) {
            $unumber = $this->unumber;
        }
        if (is_null($userid)) {
            $userid = $this->userid;
            $thisuser = true;
        } else {
            $thisuser = false;
        }
        if (is_null($quizzes)) {
            $this->get_quizzes();
            $quizzes = &$this->quizzes;
            $use_this_quizzes = true;
        } else {
            $use_this_quizzes = false;
        }
        if (empty($quizzes)) {
        //    return; // no quizzes to regrade
        }

        if ($get_unit_quizzes) {
            $units[$unit->id] = (object)array(
                'quizgroups' => array(),
                'equalweighting' => array(),
                'totalweighting' => 0,
                'countquizzes' => 0
            );
            if ($use_this_quizzes) {
                $units[$unit->id]->quizzes = &$this->quizzes;
            } else {
                $units[$unit->id]->quizzes = $DB->get_records('quizport_quizzes', array('unitid'=>$unit->id), 'sortorder', 'id,scoreweighting');
            }
            if ($units[$unit->id]->quizzes) {
                foreach ($units[$unit->id]->quizzes as $quiz) {
                    if ($quiz->scoreweighting<0) {
                        $quizgroup = $quiz->scoreweighting;
                    } else {
                        $quizgroup = 'default';
                        $units[$unit->id]->totalweighting += $quiz->scoreweighting;
                    }
                    if (! isset($units[$unit->id]->quizgroups[$quizgroup])) {
                        $units[$unit->id]->quizgroups[$quizgroup] = array();
                    }
                    $units[$unit->id]->quizgroups[$quizgroup][] = $quiz->id;
                }
                foreach ($units[$unit->id]->quizgroups as $quizgroup => $ids) {
                    if ($quizgroup=='default') {
                        continue;
                    }
                    if ($units[$unit->id]->totalweighting<100) {
                        $units[$unit->id]->equalweighting[$quizgroup] = (100 - $units[$unit->id]->totalweighting) / count($ids);
                    } else {
                        $units[$unit->id]->equalweighting[$quizgroup] = 0;
                    }
                }
                if (count($units[$unit->id]->equalweighting)) {
                    $units[$unit->id]->totalweighting = max(100, $units[$unit->id]->totalweighting);
                }
                // Note: totalweighting may not be exactly 100, in the following cases:
                //     (1) no "equalweighting" quizzes exist and the sum of quiz weightings is not equal to 100
                //     (2) "equalweighting" quizzes exist, but the sum of other quiz weightings is more than 100
                // in case (2), the equalweighting is set to zero, i.e. "equalweighting" quizzes have no effect on grade
            }
            if (count($units[$unit->id]->quizgroups)==1) {
                // this unit only has only one equalweighting group
                $quizgroup = reset($units[$unit->id]->quizgroups);
                $units[$unit->id]->countquizzes = count($quizgroup);
            }
        }

        if (empty($units[$unit->id]->quizzes)) {
            return; // no quizzes in this unit
        }

        $quizids = array_keys($units[$unit->id]->quizzes);

        $countquizzes = count($quizids);
        $quizids = implode(',', $quizids);

        $grade = 0;
        $duration = 0;
        $minstatus = 0;
        $maxstatus = 0;
        $timemodified = 0;

        $quizscores = $DB->get_records_select('quizport_quiz_scores', "userid=$userid AND unumber=$unumber AND quizid in ($quizids)");

        $canresume = false;
        $canrestart = false;
        $restartquizids = array();

        $countquizscores = 0;
        if ($quizscores) {
            $countquizscores = count($quizscores);

            // equalweighting quiz groups are considered to be mutually exclusive,
            // so if this user has attempted quizzes from more than one quiz group,
            // we should select the group which has the most number of attempts
            // and ignore attempts for quizzes in other groups
            $quizgroups = array();
            foreach ($quizscores as $quizscore) {
                if (! array_key_exists($quizscore->quizid, $quizzes)) {
                    continue;
                }
                $quizgroup = $quizzes[$quizscore->quizid]->scoreweighting;
                if ($quizgroup>=0) {
                    $quizgroup = 'default';
                }
                if (! array_key_exists($quizgroup, $quizgroups)) {
                    $quizgroups[$quizgroup] = array();
                }
                $quizgroups[$quizgroup][] = $quizscore->quizid;
            }
            $countquizzes = 0;
            $mainquizgroup = 0;
            foreach ($quizgroups as $quizgroup => $ids) {
                if ($quizgroup=='default') {
                    continue;
                }
                $count = count($ids);
                if ($count > $countquizzes) {
                    $countquizzes = $count;
                    $mainquizgroup = $quizgroup;
                }
            }
            if (empty($quizgroups['default'])) {
                $validquizids = array();
            } else {
                $validquizids = $quizgroups['default'];
            }
            if ($mainquizgroup) {
                $validquizids = array_merge($validquizids, $quizgroups[$mainquizgroup]);
            }
            $quizids = implode(',', $validquizids);

            if ($unit->attemptgrademethod==QUIZPORT_GRADEMETHOD_TOTAL) {
                $totalweighting = $units[$unit->id]->totalweighting;
            } else {
                $totalweighting = 100;
            }

            foreach ($quizscores as $quizscore) {

                if (! in_array($quizscore->quizid, $validquizids)) {
                    // we are not interested in this quiz
                    continue;
                }

                if ($totalweighting) {
                    $weighting = $quizzes[$quizscore->quizid]->scoreweighting;
                    if ($weighting<0) {
                        $weighting = $units[$unit->id]->equalweighting[$weighting];
                    }
                    $weightedscore = ($quizscore->score * ($weighting / $totalweighting));
                    switch ($unit->attemptgrademethod) {
                        case QUIZPORT_GRADEMETHOD_TOTAL:
                            $grade += $weightedscore;
                            break;
                        case QUIZPORT_GRADEMETHOD_HIGHEST:
                            if ($grade < $weightedscore) {
                                $grade = $weightedscore;
                            }
                            break;
                        case QUIZPORT_GRADEMETHOD_LAST:
                            if ($timemodified < $quizscore->timemodified) {
                                $grade = $weightedscore;
                            }
                            break;
                        case QUIZPORT_GRADEMETHOD_LASTCOMPLETED:
                            if ($timemodified < $quizscore->timemodified && ($quizscore->status==QUIZPORT_STATUS_COMPLETED)) {
                                $grade = $weightedscore;
                            }
                            break;
                        case QUIZPORT_GRADEMETHOD_LASTTIMEDOUT:
                            if ($timemodified < $quizscore->timemodified && ($quizscore->status==QUIZPORT_STATUS_COMPLETED || $quizscore->status==QUIZPORT_STATUS_TIMEDOUT)) {
                                $grade = $weightedscore;
                            }
                            break;
                        case QUIZPORT_GRADEMETHOD_LASTABANDONED:
                            if ($timemodified < $quizscore->timemodified && ($quizscore->status==QUIZPORT_STATUS_COMPLETED || $quizscore->status==QUIZPORT_STATUS_TIMEDOUT || $quizscore->status==QUIZPORT_STATUS_ABANDONED)) {
                                $grade = $weightedscore;
                            }
                            break;
                    } // end switch
                }

                if ($quizscore->status) {
                    if ($minstatus==0 || $minstatus>$quizscore->status) {
                        $minstatus = $quizscore->status;
                    }
                    if ($maxstatus==0 || $maxstatus<$quizscore->status) {
                        $maxstatus = $quizscore->status;
                    }

                    if ($quizscore->status==QUIZPORT_STATUS_COMPLETED) {
                        // do nothing - cannot resume or restart
                    } else if ($quizscore->status==QUIZPORT_STATUS_INPROGRESS) {
                        if ($quizzes[$quizscore->quizid]->allowresume) {
                            $canresume = true;
                        }
                    } else {
                        if ($quizzes[$quizscore->quizid]->attemptlimit) {
                            // check this quiz later
                            $restartquizids[] = $quizscore->quizid;
                        } else {
                            $canrestart = true;
                        }
                    }
                }

                $duration += $quizscore->duration;
            } // end foreach $quizzes

            // don't let grade go above gradelimit
            $grade = min($grade, $unit->gradelimit);

        } // end if $quizscores

        if ($use_this_quizzes && $this->unitattempt) {
            // user has just submitted some quiz results
            $unitattempt = &$this->unitattempt;
        } else {
            // teacher is deleting attempts or regrading
            $unitattempt = $DB->get_record('quizport_unit_attempts', array('unitid'=>$unit->id, 'unumber'=>$unumber, 'userid'=>$userid));
        }
        if ($unitattempt) {
            // unit attempt already exists (the usual case)
            $status = $unitattempt->status;
        } else {
            // unit attempt record not found - should not happen !
            $status = QUIZPORT_STATUS_INPROGRESS;
        }

        if ($status==QUIZPORT_STATUS_INPROGRESS && $thisuser && $this->get_lastattempt('quiz')) {
            $nextquizid = $this->get_available_quiz($this->lastquizattempt->quizid);
        } else {
            $nextquizid = 0;
        }

        if ($nextquizid==QUIZPORT_CONDITIONQUIZID_ENDOFUNIT) {
            // post-conditions specify end of quiz
            $status = QUIZPORT_STATUS_COMPLETED;
        } else if ($nextquizid) {
            // post-conditions specify different quiz (or menu)
            $status = QUIZPORT_STATUS_INPROGRESS;
        } else if ($status==QUIZPORT_STATUS_INPROGRESS) {
            $countquizzes = $units[$unit->id]->countquizzes;
            if ($unit->timelimit && $duration > $unit->timelimit) {
                // total time on quizzes exceeds unit time limit
                $status = QUIZPORT_STATUS_TIMEDOUT;
            } else if ($countquizzes && $countquizzes==$countquizscores && $minstatus==QUIZPORT_STATUS_COMPLETED && $maxstatus==QUIZPORT_STATUS_COMPLETED) {
                // all quizzes are completed
                $status = QUIZPORT_STATUS_COMPLETED;
            } else if ($thisuser) {
                if ($unit->allowresume==QUIZPORT_ALLOWRESUME_NO && $this->get_lastattempt('quiz') && $this->lastquizattempt->status==QUIZPORT_STATUS_ABANDONED) {
                    // unit may not be resumed and last quiz attempt was abandoned
                    $status = QUIZPORT_STATUS_ABANDONED;
                } else if ($countquizzes && $countquizzes==$countquizscores) {
                    // all quizzes have been attempted
                    if ($canresume==false && $canrestart==false && count($restartquizids)) {
                        // check to see if any of quizzes can be (re)started
                        $table = '{quizport_quiz_attempts}';
                        $fields = "quizid AS id, COUNT(*) AS countattempts";
                        $select = "userid=$userid AND unumber=$unumber AND quizid IN (".implode(',', $restartquizids).')';
                        $sql = "SELECT $fields FROM $table WHERE $select GROUP BY quizid";
                        if ($aggregates = $DB->get_records_sql($sql)) {
                            foreach ($aggregates as $quizid=>$aggregate) {
                                if ($aggregate->countattempts < $quizzes[$quizid]->attemptlimit) {
                                    $canrestart = true;
                                    break;
                                }
                            }
                        }
                    }
                    if ($canresume || $canrestart) {
                        // do nothing - at least one quiz can be resumed or restarted
                    } else if ($minstatus==QUIZPORT_STATUS_TIMEDOUT && $maxstatus==QUIZPORT_STATUS_TIMEDOUT) {
                        // quizzes are all timed out (and cannot be restarted)
                        $status = QUIZPORT_STATUS_TIMEDOUT;
                    } else {
                        // quizzes are a mix of in progress, timed out, abandoned and completed
                        // and no quiz can be restarted or resumed
                        $status = QUIZPORT_STATUS_ABANDONED;
                    }
                }
            }
        }

        if ($unitattempt) {
            $unitattempt->grade = intval(round($grade));
            $unitattempt->status = intval($status);
            $unitattempt->duration = intval($duration);
            if (! $DB->update_record('quizport_unit_attempts', $unitattempt)) {
                print_error('error_updaterecord', 'quizport', '', 'quizport_unit_attempts');
            }
        } else if ($countquizscores) {
            // unit attempt record not found - might happen first time the grade is calculated
            $this->create_unitattempt($grade, $status, $duration, $unit->id, $unumber, $userid);
        }
    }

    function regrade_unit() {
        $this->regrade_attempts('unit');
    }

    function regrade_quiz() {
        $this->regrade_attempts('quiz');
        $this->regrade_unitattempt();
        $this->regrade_attempts('unit');
    }

    function get_userlist() {
        global $CFG;

        static $userlist = array();

        if (count($userlist)) {
            return $userlist;
        }

        $str = (object)array(
            'groups'   => get_string('groups'),
            'students' => get_string('existingstudents'),
            'managers' => get_string('coursemanager', 'admin'),
            'others'   => get_string('other')
        );

        // get all users who have ever attempted this QuizPort
        $users = $this->get_users(true);

        if ($users) {
            $userlist[$str->groups]['users'] = get_string('allusers', 'quizport').' ('.count($users).')';
        } else {
            // no users with attempts, but we want to force the "groups" to the top of the drop down list
            // so we add a dummy option here (to create the option group), and then remove the dummy later
            $userlist[$str->groups]['dummy'] = 'dummy';
        }

        // keep a running total of students and managers with grades
        $count_participants = 0;

        // get teachers and enrolled students
        $managers = $this->get_managers();
        $students = $this->get_students();

        // current students
        if ($students) {
            $count = 0;
            foreach ($students as $user) {
                // exclude mangers
                if (empty($managers[$user->id])) {
                    $userlist[$str->students]["$user->id"] = fullname($user);
                    unset($users[$user->id]);
                    $count++;
                }
            }
            if ($count) {
                $userlist[$str->groups]['students'] = $str->students." ($count)";
                $count_participants += $count;
            }
        }

        // managers (teachers, course-creators, Moodle admins)
        if ($managers) {
            $count = 0;
            foreach ($managers as $user) {
                // only include managers who have attempted some of the quizzes
                if (isset($users[$user->id])) {
                    $userlist[$str->managers]["$user->id"] = fullname($user);
                    unset($users[$user->id]);
                    $count++;
                }
            }
            if ($count) {
                $userlist[$str->groups]['managers'] = $str->managers." ($count)";
                $count_participants += $count;
            }
        }

        if ($count_participants) {
            $userlist[$str->groups]['participants'] = get_string('allparticipants'). " ($count_participants)";
        }

        // groupings
        if ($g = $this->get_all_groupings()) {
            foreach ($g as $gid => $grouping) {
                if ($members = groups_get_grouping_members($gid)) {
                    $userlist[$str->groups]["grouping$gid"] = get_string('grouping', 'group').': '.format_string($grouping->name).' ('.count($members).')';
                }
            }
        }

        // groups
        if ($g = $this->get_all_groups()) {
            foreach ($g as $gid => $group) {
                if ($members = groups_get_members($gid)) {
                    if (isset($group->name)) {
                        $name = $group->name;
                    } else { // Moodle 1.8
                        $name = groups_get_group_name($gid);
                    }
                    $userlist[$str->groups]["group$gid"] = get_string('group').': '.format_string($name).' ('.count($members).')';
                }
            }
        }

        // remaining $users are probably former students
        if ($users) {
            $count = 0;
            foreach ($users as $user) {
                $userlist[$str->others]["$user->id"] = fullname($user);
                unset($users[$user->id]);
                $count++;
            }
            if ($count) {
                $userlist[$str->groups]['others'] = $str->others." ($count)";
            }
        } else {
            unset($userlist[$str->groups]['dummy']);
        }

        return $userlist;
    }

    function get_managers() {
        static $managers = null;
        if (is_null($managers)) {
            $groupids = $this->get_groupids(false);
            $managers = get_users_by_capability($this->coursecontext, 'mod/quizport:grade', 'u.id,u.firstname,u.lastname', 'u.lastname,u.firstname', '', '', $groupids);
        }
        return $managers;
    }

    function get_students() {
        static $students = null;
        if (is_null($students)) {
            $groupids = $this->get_groupids(false);
            $students = get_users_by_capability($this->coursecontext, 'mod/quizport:attempt', 'u.id,u.firstname,u.lastname', 'u.lastname,u.firstname', '', '', $groupids);
        }
        return $students;
    }

    function get_users($returnnames=false) {
        global $DB;

        if ($returnnames) {
            $fields = 'DISTINCT userid';
        } else {
            $fields = 'DISTINCT userid AS id, userid';
        }
        $sql = ''
            ."SELECT $fields FROM {quizport_unit_grades} "
            ."WHERE parenttype=0 AND parentid IN ("
                ."SELECT id FROM {quizport} "
                ."WHERE course={$this->courserecord->id}"
            .')'
            .$this->get_all_groups_sql()
        ;
        if ($returnnames) {
            $sql = ''
                ."SELECT u.id, u.firstname, u.lastname FROM {user} u "
                ."WHERE u.id IN ($sql) ORDER BY u.lastname,u.firstname"
            ;
        }
        return $DB->get_records_sql($sql);
    }

    function get_groupids($return_array=true) {
        if ($groups = $this->get_all_groups()) {
            $groupids = array_keys($groups);
        } else {
            $groupids = array();
        }
        if ($return_array) {
            return $groupids;
        }
        // prepare for get_users_by_capability()
        switch (count($groupids)) {
            case 0: $groupids = ''; break;
            case 1: $groupids = array_pop($groupids); break;
        }
        return $groupids;
    }

    function get_all_groups_sql($AND=' AND ', $field='userid') {
        if ($groupids = implode(',', $this->get_groupids())) {
            return $AND.$field.' IN (SELECT DISTINCT gm.userid FROM {groups_members} gm WHERE gm.groupid IN ('.$groupids.'))';
        } else {
            return '';
        }
    }

    function can_accessallgroups() {
        // get groupmode: 0=NOGROUPS, 1=VISIBLEGROUPS, 2=SEPARATEGROUPS
        if (empty($this->modulerecord)) {
            $groupmode = groups_get_course_groupmode($this->courserecord);
            $context = $this->coursecontext;
        } else {
            $groupmode = groups_get_activity_groupmode($this->modulerecord);
            $context = $this->modulecontext;
        }
        return ($groupmode==NOGROUPS || $groupmode==VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $context));
    }

    function get_all_groups() {
        if (empty($this->courserecord)) {
            return array(); // shouldn't happen !!
        }

        if (empty($this->courserecord->groupmode)) {
            return array(); // this course has no groups
        }

        if ($this->can_accessallgroups()) {
            // user can see any groups
            $userid = 0;
        } else {
            // user can only see own group(s) e.g. non-editing teacher
            $userid = $this->userid;
        }

        if (empty($this->modulerecord->groupingid)) {
            $groupingid = 0;
        } else {
            $groupingid = $this->modulerecord->groupingid;
        }

        return groups_get_all_groups($this->courserecord->id, $userid, $groupingid);
    }

    function get_all_groupings() {
        global $CFG, $DB;

        // groupings are ignored when not enabled
        if (empty($CFG->enablegroupings) || empty($this->courserecord) || empty($this->courserecord->groupmode)) {
            return false;
        }

        $ids = $this->get_groupids();
        if ($ids = implode(',', $ids)) {
            $select = 'id IN (SELECT groupingid FROM {groupings_groups} WHERE groupid IN ('.$ids. '))';
        } else {
            $select = 'courseid='.$this->courserecord->id;
        }

        return $DB->get_records_select('groupings', $select, null, 'name ASC');
    }

    function get_userfilter($AND=' AND ', $field='userid') {
        global $CFG;

        $userlist = optional_param('userlist', get_user_preferences('userlist', 'users'), PARAM_ALPHANUM);
        // group, grouping, users, participants, managers, students, others, specific userid

        // check for groups and groupings
        $gid = 0;
        if (substr($userlist, 0, 5)=='group') {
            if (substr($userlist, 5, 3)=='ing') {
                $g = $this->get_all_groupings();
                $id = intval(substr($userlist, 8));
                $userlist = 'grouping';
            } else {
                $g = $this->get_all_groups();
                $id = intval(substr($userlist, 5));
                $userlist = 'group';
            }
            if ($g && isset($g[$id])) {
                $gid = $id; // id is valid
            } else {
                $userlist = 'users'; // default
            }
        }

        $userids = array();
        switch ($userlist) {

            case 'users':
                // anyone who has ever attempted QuizPorts in this course
                if ($users = $this->get_users()) {
                    $userids = array_keys($users);
                }
                break;

            case 'grouping':
                // grouping members
                if ($gid && ($members = groups_get_grouping_members($gid))) {
                    $userids = array_keys($members);
                }
                break;

            case 'group':
                // group members
                if ($gid && ($members = groups_get_members($gid))) {
                    $userids = array_keys($members);
                }
                break;

            case 'participants':
                // all students + managers who have attempted a quiz
                if ($users = $this->get_users()) {
                    if ($students = $this->get_students()) {
                        $userids = array_keys($students);
                    }
                    if ($managers = $this->get_managers()) {
                        $userids = array_merge(
                            $userids , array_intersect(array_keys($managers), array_keys($users))
                        );
                    }
                }
                break;

            case 'managers':
                // all course managers who have attempted a quiz
                if ($users = $this->get_users()) {
                    if ($managers = $this->get_managers()) {
                        $userids = array_intersect(array_keys($managers), array_keys($users));
                    }
                }
                break;

            case 'students':
                // anyone currently allowed to attempt this QuizPort who is not a manager
                if ($students = $this->get_students()) {
                    $userids = array_keys($students);
                    if ($managers = $this->get_managers()) {
                        $userids = array_diff($userids, array_keys($managers));
                    }
                }
                break;

            case 'others':
                // anyone who has attempted the quiz, but is not currently a student or manager
                if ($users = $this->get_users()) {
                    $userids = array_keys($users);
                    if ($students = $this->get_students()) {
                        $userids = array_diff($userids, array_keys($students));
                    }
                    if ($managers = $this->get_managers()) {
                        $userids = array_diff($userids, array_keys($managers));
                    }
                }
                break;

            default: // specific user selected by teacher
                if (is_numeric($userlist)) {
                    $userids[] = $userlist;
                }
        } // end switch

        sort($userids);
        $userids = implode(',', array_unique($userids));

        if ($userids=='') {
            return ''; // no users
        } else if (strpos($userids, ',')===false) {
            return $AND."$field=$userids"; // one user
        } else {
            return $AND."$field IN ($userids)"; // many users
        }
    }

    function print_userlist($return=false, $printform=false) {
        $str = '';
        $name = 'userlist';

        if ($printform) {
            $params = array('id'=>$this->courserecord->id);
            $str .= $this->print_form_start('index.php', $params, false, true);
        }

        $str .= '<b>'.get_string('users').':</b> ';

        $userlist = $this->get_userlist();

        $default = get_user_preferences('quizport_'.$name, 'users');
        $selected = optional_param($name, $default, PARAM_ALPHANUM);

        $onclick = "if(this.form){if(this.form.elements['action'])this.form.elements['action'].options[0].selected=true;this.form.submit()}";
        $str .= choose_from_menu_nested($userlist, $name, $selected, '', $onclick, 0, true)."\n";

        if ($printform) {
            $str .= ''
                .'<div id="noscript'.$name.'" style="display: inline;">'
                .'<input type="submit" value="'.get_string('go').'" /></div>'."\n"
                .'<script type="text/javascript">'."\n"
                .'//<![CDATA['."\n"
                .'    document.getElementById("noscript'.$name.'").style.display = "none";'."\n"
                .'//]]>'."\n"
                .'</script>'."\n"
            ;
            $str .= $this->print_form_end(true);
        }

        $str = '<div id="userlist">'.$str.'</div>'."\n";

        if ($return) {
            return $str;
        } else {
            print $str;
        }
    }

    function print_menu_submit($options, $name, $quizportscriptname, $params, $return=false, $id='', $onchange='this.form.submit()') {

        if (isset($this->$name)) {
            $selected = $this->$name;
        } else {
            $selected = optional_param($name, '', PARAM_ALPHANUM);
        }

        $choose_from_menu = 'choose_from_menu';
        foreach ($options as $option) {
            if (is_array($option)) {
                $choose_from_menu = 'choose_from_menu_nested';
                break;
            }
        }

        $str = ''
            .$this->print_form_start($quizportscriptname, $params, false, true, array('id'=>$id))
            .$choose_from_menu($options, $name, $selected, '', $onchange, 0, true)."\n"
            .'<div id="noscript'.$name.'" style="display: inline;">'
                .'<input type="submit" value="'.get_string('go').'" /></div>'."\n"
                .'<script type="text/javascript">'."\n"
                .'//<![CDATA['."\n"
                .'    document.getElementById("noscript'.$name.'").style.display = "none";'."\n"
                .'//]]>'."\n"
                .'</script>'."\n"
            .$this->print_form_end(true)
        ;

        if ($return) {
            return $str;
        } else {
            print $str;
        }
    }

    function print_checkbox($name, $value, $checked=true, $label='', $alt='', $script='', $return=false) {
        global $CFG;
        if ($CFG->majorrelease<=1.5) {
            if ($script) {
                // Moodle 1.5 has no script parameter so add it to the value
                $value .= '" onclick="'.$script;
            }
            if ($return) {
                ob_start();
                print_checkbox ($name, $value, $checked, $label, $alt);
                return ob_get_clean();
            }
        }
        return print_checkbox($name, $value, $checked, $label, $alt, $script, $return);
    }

    function print_single_button($link, $options, $label='OK', $method='get', $target='_self', $return=false) {
        global $CFG;
        if ($CFG->majorrelease<=1.6 && $return) {
            ob_start();
            print_single_button($link, $options, $label, $method, $target);
            return ob_get_clean();
        }
        return print_single_button($link, $options, $label, $method, $target, $return);
    }

    function choose_from_radio($options, $name, $checked='', $return=false) {
        global $CFG;
        if ($CFG->majorrelease<=1.6 && $return) {
            ob_start();
            choose_from_radio($options, $name, $checked);
            return ob_get_clean();
        }
        return choose_from_radio($options, $name, $checked, $return);
    }

    function print_table($table, $return=false) {
        global $CFG;
        if ($CFG->majorrelease<=1.6 && $return) {
            ob_start();
            print_table($table);
            return ob_get_clean();
        }
        return print_table($table, $return);
    }
} // end class : mod_quizport

// usort functions used when sorting attempts during pre/post condition checks

function quizport_usort_duration_asc(&$a, &$b) {
    // shortest first, longest last
    if ($a->duration < $b->duration) {
        return -1; // $a before $b
    }
    if ($a->duration > $b->duration) {
        return 1; // $a after $b
    }
    // equal values
    return 0;
}

function quizport_usort_duration_desc(&$a, &$b) {
    // longest first, shortest last
    if ($a->duration > $b->duration) {
        return -1; // $a before $b
    }
    if ($a->duration < $b->duration) {
        return 1; // $a after $b
    }
    // equal values
    return 0;
}

function quizport_usort_time_desc(&$a, &$b) {
    // most recent first, oldest last
    $atime = max($a->timestart, $a->timefinish, $a->resumestart, $a->resumefinish);
    $btime = max($b->timestart, $b->timefinish, $b->resumestart, $b->resumefinish);
    if ($atime > $btime) {
        return -1; // $a before $b
    }
    if ($atime < $btime) {
        return 1; // $a after $b
    }
    // equal values
    return 0;
}

// usort function to sort quizzes by sortorder

function quizport_usort_sortorder_asc(&$a, &$b) {
    if ($a->sortorder < $b->sortorder) {
        return -1; // $a before $b
    }
    if ($a->sortorder > $b->sortorder) {
        return 1; // $a after $b
    }
    // equal values
    return 0;
}

function quizport_load_quiz_class($topclass, $subclass='') {
    global $CFG;
    $dirname = "$CFG->dirroot/mod/quizport";

    $classes = array($topclass);
    if ($subclass) {
        $classes = array_merge($classes, explode('_', $subclass));
    }
    foreach ($classes as $class) {

        $dirname = "$dirname/$class";
        $filepath = "$dirname/class.php";

        if (is_readable($filepath)) {
            require_once($filepath);
        } else {
            print_error('error_missingclassfile', 'quizport', '', $filepath);
        }
    }
}

// set page id
switch (QUIZPORT_PAGEID) {
    case 'mod-quizport-editcolumnlists':
    case 'mod-quizport-editunits':
    case 'mod-quizport-index':
        $id = $course->id;
        break;
    case 'mod-quizport-attempt':
    case 'mod-quizport-editcondition':
    case 'mod-quizport-editquiz':
    case 'mod-quizport-editquizzes':
    case 'mod-quizport-report':
    case 'mod-quizport-view':
        $id = $quizport->id;
        break;
    default:
        $id = 0;
}
if ($id) {
    $file = str_replace('-', '/', QUIZPORT_PAGEID); // e.g. mod/quizport/view
    $class = str_replace('-', '_', QUIZPORT_PAGEID); // e.g. mod_quizport_view

    // create main $QUIZPORT object
    require_once($CFG->dirroot.'/'.$file.'.class.php');
    $QUIZPORT = new $class();

    if ($CFG->majorrelease>=2.0) {
        if ($coursemodule) {
            $PAGE->set_cm($coursemodule, $course, $quizport);
        }
        $PAGE->set_url($file.'.php', array('id' => $id));
        require_once($CFG->dirroot.'/lib/completionlib.php');
    } else {
        // create $PAGE and set up $pageblocks
        $PAGE = page_create_object(QUIZPORT_PAGEID, $id);
        $pageblocks = blocks_setup($PAGE);
        $QUIZPORT->init_pageblocks($pageblocks);
    }
}
unset($file, $class, $id);
?>