<?php // $Id$
/**
 * Library of standard functions for the quizport module
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
// standardize Moodle API to Moodle 2.0
require_once($CFG->dirroot.'/mod/quizport/legacy.php');

/// CONSTANTS ///////////////////////////////////////////////////////////////////

/**#@+ */

define ('QUIZPORT_PARENTTYPE_ACTIVITY', '0');
define ('QUIZPORT_PARENTTYPE_BLOCK', '1');

define('QUIZPORT_LOCATION_COURSEFILES', '0');
define('QUIZPORT_LOCATION_SITEFILES',   '1');
define('QUIZPORT_LOCATION_WWW',         '2');

define('QUIZPORT_STATUS_INPROGRESS', '1');
define('QUIZPORT_STATUS_TIMEDOUT', '2');
define('QUIZPORT_STATUS_ABANDONED', '3');
define('QUIZPORT_STATUS_COMPLETED', '4');
define('QUIZPORT_STATUS_PAUSED', '5');

/**#@-*/

/*
* Given an object containing all the necessary data,
* defined by the form in mod.html or mod_form.php,
* this function will create a new instance
* and return the id number of the new instance.
*
* This function is called from: {@link course/mod.php}
*
* @param object $form the form data from mod_form.php (or mod.html)
* @return mixed id number of new record, or error message or false
*/
function quizport_add_instance(&$form) {
    return quizport_add_or_update_instance($form);
}

/*
* Given an object containing all the necessary data,
* defined by the form in mod_form.php (or mod.html) ,
* this function will update an existing instance with new data.
*
* This function is called from: {@link course/mod.php}
*
* @param object $form the form data from mod_form.php (or mod.html)
* @return mixed id number of updated record, or error message or false
*/
function quizport_update_instance(&$form) {
    return quizport_add_or_update_instance($form);
}

/*
* Given an object containing all the necessary data,
* defined by the form in mod_form.php (or mod.html) ,
* this function will add or update an instance
*
* This function is called from: {@link quizport_add_instance} and {@link quizport_update_instance}
*
* @param object $form the form data from mod_form.php (or mod.html)
*         $form contains the values of the form in mod.html or mod_form.php
*         i.e. all the fields in the "quizport" and "quizport_units" tables, plus the following:
*             $quizport->mode : "add" or "update"
*             $quizport->course : an id in the "course" table
*             $quizport->coursemodule : an id in the "course_modules" table
*             $quizport->section : an id in the "course_sections" table
*             $quizport->module : an id in the "modules" table
*             $quizport->instance : an id in the "quizport" table (for update only)
*             $quizport->modulename : always "quizport"
*             $quizport->sesskey : unique string required for Moodle's session management
* @param int $parenttype
*             0 (=QUIZPORT_PARENTTYPE_ACTIVITY) : quizport
*             1 (=QUIZPORT_PARENTTYPE_BLOCK) : blocks_instance
* @return mixed
*         number>0: id number of updated record
*             continue to $quizport->redirect (if set)
*             OR quizport/view.php (to display quiz)
*         true: record was successfully added
*             continue to $quizport->redirect (if set)
*             OR quizport/view.php (to display quiz)
*         false: record could not be updated
*             display moderr.html (if exists)
*             OR display "Could not add" message and return to couse view
*         string: record could not be updated
*             display as error message and return to course view
*/
function quizport_add_or_update_instance(&$form, $parenttype=QUIZPORT_PARENTTYPE_ACTIVITY) {
    global $CFG, $DB, $QUIZPORT;

    if ($CFG->majorrelease<=1.7) {
        quizport_update_showadvanced_last($form, 'mod');
    }

    require_once($CFG->dirroot.'/mod/quizport/lib.forms.php');

    $time = time();
    $defaultname = get_string('modulename', 'quizport');
    $update_gradebook = false;

    // if we are adding, then set up the unit name
    if ($form->add) {

        $textfields = array('name', 'quiznames', 'entrytext', 'exittext');
        foreach($textfields as $textfield) {

            $textsource = $textfield.'source';
            if (! isset($form->$textsource)) {
                $form->$textsource = QUIZPORT_TEXTSOURCE_SPECIFIC;
            }

            switch ($form->$textsource) {
                case QUIZPORT_TEXTSOURCE_FILE:
                    $form->$textfield = ''; // will be set later (from $source->quizzes[0])
                    break;
                case QUIZPORT_TEXTSOURCE_FILENAME:
                    $form->$textfield = addslashes(basename($form->sourcefile));
                    break;
                case QUIZPORT_TEXTSOURCE_FILEPATH:
                    $form->$textfield = str_replace(array('/', '\\'), ' ', addslashes($form->sourcefile));
                    break;
                case QUIZPORT_TEXTSOURCE_SPECIFIC:
                default:
                    if (isset($form->$textfield)) {
                        $form->$textfield = trim($form->$textfield);
                    } else {
                        $form->$textfield = '';
                    }
                    if ($textfield=='name' && $form->$textfield=='') {
                        $form->$textfield = addslashes($defaultname);
                    }
            }
        }
    }

    switch ($parenttype) {

        case QUIZPORT_PARENTTYPE_ACTIVITY:
            // create new quizport
            $parenttable = 'quizport';
            if (empty($form->instance)) {
                $parent = new stdClass();
                $parent->course = $form->course;
                $parent->timecreated = $time;
            } else {
                if (! $parent = $DB->get_record($parenttable, array('id'=>$form->instance, 'course'=>$form->course))) {
                    return get_string('error_getrecord', 'quizport', $parenttable);
                }
                if ($CFG->majorrelease<=1.9) {
                    $parent->name = addslashes($parent->name);
                }
                if ($parent->name!=$form->name) {
                    $update_gradebook = true;
                }
            }
            if ($form->name=='') {
                $parent->name = addslashes($defaultname);
            } else {
                $parent->name = $form->name;
            }
            $parent->timemodified = $time;
            if (empty($form->removegradeitem)) {
                $parent->removegradeitem = false;
            } else {
                $parent->removegradeitem = true;
            }
            break;

        case QUIZPORT_PARENTTYPE_BLOCK:
            // create new block instance
            $parent = new stdClass();
            $parent->blockid = $form->blockid;
            $parent->pageid = $form->course;
            $parent->pagetype = PAGE_COURSE_VIEW;
            $parent->position = $form->position; // BLOCK_POS_LEFT or BLOCK_POS_RIGHT
            $parent->weight = $form->weight;
            $parent->visible = $form->visible;
            $parent->configdata = $form->configdata;
            $parenttable = 'block_instance';
            break;

        default:
            return get_string('error_invalidparenttype', 'quizport', $parenttype);

    } // end switch

    if (empty($form->instance)) {
        // add parent
        if (! $parent->id = $DB->insert_record($parenttable, $parent)) {
            return get_string('error_insertrecord', 'quizport', $parenttable);
        }
        // force creation of new unit
        $unit = false;
    } else {
        // update parent
        $parent->id = $form->instance;
        if (! $DB->update_record($parenttable, $parent)) {
            return get_string('error_updaterecord', 'quizport', $parenttable);
        }
        // get associated unit record
        $unit = $DB->get_record('quizport_units', array('parenttype'=>$parenttype, 'parentid'=>$parent->id));
    }

    // set flags to regrade unit and/or update grades
    $regrade_unitattempts = false;
    $regrade_unitgrades = false;
    if ($unit) {
        if ($unit->attemptgrademethod != $form->attemptgrademethod) {
            $regrade_unitattempts = true;
            $regrade_unitgrades = true;
        }
        if ($unit->grademethod != $form->grademethod || $unit->gradeignore != $form->gradeignore) {
            $regrade_unitgrades = true;
        } else if ($unit->gradelimit != $form->gradelimit || $unit->gradeweighting != $form->gradeweighting) {
            $regrade_unitgrades = true;
        }
    } else {
        // start a new unit
        $unit = new stdClass();
        $unit->parenttype = $parenttype;
        $unit->parentid = $parent->id;
    }

    // add/update unit fields

    $page_options = get_page_options();
    foreach ($page_options as $type=>$names) {

        // entrypage and exitpage
        $page = $type.'page';
        if (isset($form->$page)) {
            $unit->$page = $form->$page;
        } else {
            $unit->$page = 0;
        }

        // entrytext and exittext
        $text = $type.'text';
        if (isset($form->$text)) {
            // remove leading and trailing white space, empty html paragraphs (from IE) and blank lines (from Firefox)
            $form->$text = preg_replace('/^((<p>\s*<\/p>)|(<br[^>]*>)|\s)+/is', '', $form->$text);
            $form->$text = preg_replace('/((<p>\s*<\/p>)|(<br[^>]*>)|\s)+$/is', '', $form->$text);
            $unit->$text = $form->$text;
        } else {
            $unit->$text = '';
        }

        // entryoptions and exitoptions
        $options = $type.'options';
        if (isset($unit->$options)) {
            $value = $unit->$options;
        } else {
            $value = 0;
        }
        foreach ($names as $name=>$mask) {
            $option = $type.'_'.$name;
            if ($unit->$page) {
                if (empty($form->$option)) {
                    // disable this option
                    $value = $value & ~$mask;
                } else {
                    // enable this option
                    $value = $value | $mask;
                }
            }
            unset($form->$option);
        }
        $unit->$options = $value;
        $form->$options = $value;

        // entrycm and exitcm
        $cm = $type.'cm';
        if (isset($form->$cm)) {
            $unit->$cm = $form->$cm;
        } else {
            $unit->$cm = 0;
        }

        // entrygrade (and exitgrade ?!)
        $grade = $type.'grade';
        if (isset($form->$grade)) {
            $unit->$grade = $form->$grade;
        } else {
            $unit->$grade = 0;
        }

        // don't show exit page if no content is specified
        if ($type=='exit' && empty($unit->$options) && empty($unit->$text)) {
            $unit->$page = 0;
            $form->$page = 0;
        }
    }
    unset($page_options, $page, $text, $options, $value, $names, $name, $mask, $cm, $grade);

    if (empty($form->showpopup)) {
        $form->showpopup = 0;
    } else {
        $form->showpopup = 1;
    }
    $popupoptions = array();
    if ($form->showpopup) {
        $preferences = array();
        $window_options = get_window_options();
        foreach ($window_options as $option) {
            if (empty($form->$option)) {
                $form->$option = '';
            } else {
                if ($option=='width' || $option=='height') {
                    $popupoptions[] = $option.'='.$form->$option;
                } else {
                    $popupoptions[] = $option;
                }
            }
            $preferences['quizport_unit_popup_'.$option] = $form->$option;
            unset($form->$option);
        }
        set_user_preferences($preferences);
    }
    $form->popupoptions = strtoupper(implode(',', $popupoptions));

    $unit->showpopup = $form->showpopup;
    $unit->popupoptions = $form->popupoptions;

    $fields = array('timeopen', 'timeclose');
    foreach ($fields as $field) {
        $unit->$field = get_time_value($form, $field);
    }

    $fields = array('timelimit', 'delay1', 'delay2');
    foreach ($fields as $field) {
        $unit->$field = get_timer_value($form, $field);
    }

    $fields = array(
        // 'showpopup', 'popupoptions',
        'password', 'subnet', 'allowresume', 'allowfreeaccess', 'attemptlimit',
        'attemptgrademethod', 'grademethod', 'gradeignore', 'gradelimit', 'gradeweighting'
    );
    foreach ($fields as $field) {
        if (isset($form->$field)) {
            $unit->$field = $form->$field;
        } else if (isset($unit->$field)) {
            // do nothing
        } else {
            $unit->$field = get_user_preferences('quizport_unit_'.$field);
        }
    }

    // transfer gradelimit and gradeweighting to parent
    // ( required later in "quizport_get_user_grades()" )
    $parent->gradeweighting = $unit->gradeweighting;
    $parent->gradelimit = $unit->gradelimit;

    // make sure there are no missing fields
    quizport_set_missing_fields('quizport_units', $unit, $form);

    if (empty($unit->id)) {
        // add new unit
        if (! $unit->id = $DB->insert_record('quizport_units', $unit)) {
            return get_string('error_insertrecord', 'quizport', 'quizport_units');
        }
    } else {
        // update existing unit record
        if (! $DB->update_record('quizport_units', $unit)) {
            return get_string('error_updaterecord', 'quizport', 'quizport_units');
        }
    }

    // save unit settings as preferences
    quizport_set_preferences('unit', $form);

    if ($form->add) {
        // add quizzes, (may update $form->name too)
        quizport_add_quizzes($form, $unit);

        // update parent and unit fields, if necessary
        if ($form->namesource==QUIZPORT_TEXTSOURCE_FILE) {
            if ($form->name=='') {
                $form->name = addslashes($defaultname);
            }
            if (! $DB->set_field($parenttable, 'name', $form->name, array('id'=>$parent->id))) {
                return get_string('error_updaterecord', 'quizport', $parenttable);
            }
        }
        if ($form->entrytextsource==QUIZPORT_TEXTSOURCE_FILE && $form->entrytext) {
            if (! $DB->set_field('quizport_units', 'entrytext', $form->entrytext, array('id'=>$unit->id))) {
                return get_string('error_updaterecord', 'quizport', 'quizport_units');
            }
        }
        if ($form->exittextsource==QUIZPORT_TEXTSOURCE_FILE && $form->exittext) {
            if (! $DB->set_field('quizport_units', 'exittext', $form->exittext, array('id'=>$unit->id))) {
                return get_string('error_updaterecord', 'quizport', 'quizport_units');
            }
        }
        if ($parenttype==QUIZPORT_PARENTTYPE_ACTIVITY) {
            // add grade item to Moodle gradebook
            quizport_grade_item_update($parent);
        }
    } else {
        // updating a QuizPort
        if ($regrade_unitgrades) {

            // create and initialize $QUIZPORT object
            global $course, $coursemodule, $quizport, $unit;
            require_once($CFG->dirroot.'/mod/quizport/class.php');
            class mod_quizport_mod extends mod_quizport {}
            $QUIZPORT = new mod_quizport_mod();

            // regrade unit attempts
            if ($regrade_unitattempts) {
                if ($records = $DB->get_records('quizport_unit_attempts', array('unitid'=>$unit->id), '', 'id,unumber,userid')) {
                    foreach ($records as $record) {
                        $QUIZPORT->regrade_unitattempt($unit, $record->unumber, $record->userid);
                    }
                }
                unset($records);
            }

            // regrade unit grades
            if ($records = $DB->get_records('quizport_unit_grades', array('parenttype'=>$unit->parenttype, 'parentid'=>$unit->parentid), '', 'id,userid')) {
                foreach ($records as $record) {
                    $QUIZPORT->regrade_attempts('unit', $unit, 0, $record->userid);
                }
            }
            unset($records);
            $update_gradebook = true;
        }
        if ($update_gradebook && $parenttype==QUIZPORT_PARENTTYPE_ACTIVITY) {
            // update Moodle gradebook
            if ($CFG->majorrelease<=1.9) {
                $parent->name = stripslashes($parent->name);
            }
            quizport_update_grades($parent);
        }
    }

    // get old event ids so they can be reused
    if ($eventids = $DB->get_records_select('event', "modulename='$parenttable' AND instance=$parent->id", null, 'id', 'id, id AS eventid')) {
        $eventids = array_keys($eventids);
    } else {
        $eventids = array();
    }

    // add / update calendar events, if necessary
    quizport_update_events($parent, $unit, $eventids, true);

    // instance was successfully added / updated
    return intval($parent->id);
}

function quizport_preferences_fields($type) {
    if($type=='unit') {
        return array(
            // adding a unit
            'sourcelocation','sourcefile','namesource','quiznamesource','entrytextsource','exittextsource','quizchain',
            // adding / editing a unit
            'entrycm','entrygrade','entrypage','entrytext','entryoptions',
            'exitpage','exittext','exitoptions','exitcm','exitgrade',
            'showpopup','popupoptions',
            'timeopen','timeclose','timelimit','delay1','delay2',
            'subnet','attemptlimit','allowresume','allowfreeaccess',
            'attemptgrademethod','grademethod','gradeignore','gradelimit','gradeweighting',
            'preconditions','postconditions'
        );
    } else {
        return array(
            // adding a quiz
            'namesource','quizchain',
            // adding / editing a quiz
            'sourcelocation','sourcefile','configfile','configlocation',
            'outputformat','navigation','title','stopbutton','stoptext','usefilters','useglossary','usemediafilter',
            'studentfeedback','studentfeedbackurl','timeopen','timeclose','timelimit',
            'delay1','delay2','delay3','password','subnet','allowresume','reviewoptions','attemptlimit',
            'scoremethod','scoreignore','scorelimit','scoreweighting','clickreporting','discarddetails',
            'preconditions','postconditions'
        );
    }
}

function quizport_set_preferences($type, &$record) {
    $fields = quizport_preferences_fields($type);
    if (isset($record->sourcetype) && isset($record->outputformat)) {
        // set default outputformat for this (quiz) source file type
        // e.g. quizport_quiz_outputformat_html, quizport_quiz_outputformat_hp_6_jquiz_xml
        $field = 'outputformat_'.$record->sourcetype;
        $record->$field = $record->outputformat;
        $fields[] = $field;
    }
    $preferences = array();
    foreach ($fields as $field) {
        if ($field=='preconditions' || $field=='postconditions') {
            if (isset($record->id)) {
                $preferences['quizport_'.$type.'_'.$field] = $record->id;
            }
        } else if (isset($record->$field)) {
            $preferences['quizport_'.$type.'_'.$field] = $record->$field;
        }
    }
    set_user_preferences($preferences);
}

function quizport_get_preferences($type) {
    $fields = quizport_preferences_fields($type);
    $preferences = array();
    foreach ($fields as $field) {
        $preferences[$field] = get_user_preferences('quizport_'.$type.'_'.$field, '');
    }
    $prefix = 'quizport_'.$type.'_';
    $strlen = strlen($prefix);
    $names = preg_grep('/'.$prefix.'outputformat_/', array_keys($USER->preference));
    foreach ($names as $name) {
        $preferences[substr($name, $strlen)] = get_user_preferences($name, '');
    }
    return $preferences;
}

function quizport_update_showadvanced_last(&$form, $page) {
    if (isset($form->mform_showadvanced_last)) {
        $name = 'mod_quizport_'.$page.'_form_showadvanced';
        set_user_preference($name, intval($form->mform_showadvanced_last));
    }
}

function quizport_add_quizzes(&$form, &$unit, $afterquizid=0) {
    global $CFG, $DB, $QUIZPORT;

    require_once($CFG->dirroot.'/mod/quizport/lib.local.php');
    require_once($CFG->dirroot.'/mod/quizport/file/class.php');

    $sortorder = 0;
    $quizids = array();
    if ($quizzes = $DB->get_records('quizport_quizzes', array('unitid'=>$unit->id), 'sortorder', 'id,sortorder')) {
        foreach ($quizzes as $quiz) {
            $sortorder = $quiz->sortorder;
            $quizids[] = $quiz->id;
        }
        unset($quizzes);
    }
    $sortorder++;

    $source = new quizport_file($form->sourcefile, $form->sourcelocation, true, $form->quizchain);
    $config = new quizport_file($form->configfile, $form->configlocation);

    // set default quiz name and, if necessary, unit text fields
    $quizname = '';
    if (isset($form->quiznamesource)) {
        $fields = array('name', 'entrytext', 'exittext');
        foreach ($fields as $field) {
            $unitfield = 'unit'.$field;
            $fieldsource = $field.'source';
            if ($form->$fieldsource==QUIZPORT_TEXTSOURCE_FILE && isset($source->$unitfield)) {
                if ($CFG->majorrelease<=1.9) {
                    $source->$unitfield = addslashes($source->$unitfield);
                }
                if (empty($CFG->formatstringstriptags)) {
                    $form->$field = clean_param($source->$unitfield, PARAM_CLEAN);
                } else {
                    $form->$field = clean_param($source->$unitfield, PARAM_TEXT);
                }
            }
        }
        $quiznamesource = $form->quiznamesource;
        if (isset($form->quizname)) {
            $quizname = trim($form->quizname);
        }
    } else {
        $quiznamesource = $form->namesource;
        if (isset($form->name)) {
            $quizname = trim($form->name);
        }
    }
    if ($quizname=='') {
        $quizname = get_string('quiz', 'quizport');
    }
    if (empty($CFG->formatstringstriptags)) {
        $quizname = clean_param($quizname, PARAM_CLEAN);
    } else {
        $quizname = clean_param($quizname, PARAM_TEXT);
    }

    $newquizids = array();

    if (empty($source->quizfiles)) {
        $source->quizfiles = array();
    }

   foreach ($source->quizfiles as $quizfile) {

        $quiz = new stdClass();
        $quiz->unitid = $unit->id;

        // set quiz name
        switch ($quiznamesource) {
            case QUIZPORT_TEXTSOURCE_FILE:
                if (! $quiz->name = $quizfile->get_name()) {
                    $quiz->name = get_string('quiz', 'quizport')." ($sortorder)";
                }
                if ($CFG->majorrelease<=1.9) {
                    $quiz->name = addslashes($quiz->name);
                }
                $is_clean_name = false;
                break;
            case QUIZPORT_TEXTSOURCE_FILENAME:
                $quiz->name = clean_param(basename($quizfile->filepath), PARAM_FILE);
                $is_clean_name = true;
                break;
            case QUIZPORT_TEXTSOURCE_FILEPATH:
                $quiz->name = str_replace('/', ' ', clean_param($quizfile->filepath, PARAM_PATH));
                $is_clean_name = true;
                break;
            case QUIZPORT_TEXTSOURCE_SPECIFIC:
            default:
                $quiz->name = '';
                $is_clean_name = true;
        }

        if ($quiz->name=='') {
            // $quizname has already been cleaned
            $quiz->name = $quizname." ($sortorder)";
        } else if ($is_clean_name) {
            // quiz name is already clean
        } else if (empty($CFG->formatstringstriptags)) {
            $quiz->name = clean_param($quiz->name, PARAM_CLEAN);
        } else {
            $quiz->name = clean_param($quiz->name, PARAM_TEXT);
        }

        // set source/config file type, path and location
        $quiz->sourcetype     = $quizfile->get_type();
        if ($quizfile->location==QUIZPORT_LOCATION_WWW) {
            $quiz->sourcefile = $quizfile->url;
        } else {
            $quiz->sourcefile = $quizfile->filepath;
        }
        $quiz->sourcelocation = $quizfile->location;

        if ($config->location==QUIZPORT_LOCATION_WWW) {
            $quiz->configfile = $config->url;
        } else {
            $quiz->configfile = $config->filepath;
        }
        $quiz->configlocation = $config->location;

        // set default field values (for this teacher)
        $quiz->outputformat = get_user_preferences('quizport_quiz_outputformat_'.$quiz->sourcetype, '');
        $quiz->navigation   = get_user_preferences('quizport_quiz_navigation', QUIZPORT_NAVIGATION_BAR);
        $quiz->title        = get_user_preferences('quizport_quiz_title', QUIZPORT_TEXTSOURCE_SPECIFIC);
        $quiz->stopbutton   = get_user_preferences('quizport_quiz_stopbutton', QUIZPORT_STOPBUTTON_NONE);
        $quiz->stoptext     = get_user_preferences('quizport_quiz_stoptext', '');
        $quiz->usefilters   = get_user_preferences('quizport_quiz_usefilters', QUIZPORT_NO);
        $quiz->useglossary  = get_user_preferences('quizport_quiz_useglossary', QUIZPORT_NO);
        $quiz->usemediafilter = get_user_preferences('quizport_quiz_usemediafilter', QUIZPORT_NO);
        $quiz->studentfeedback = get_user_preferences('quizport_quiz_studentfeedback', QUIZPORT_FEEDBACK_NONE);
        $quiz->studentfeedbackurl = get_user_preferences('quizport_quiz_studentfeedbackurl', '');
        $quiz->timeopen     = get_user_preferences('quizport_quiz_timeopen', 0);
        $quiz->timeclose    = get_user_preferences('quizport_quiz_timeclose', 0);
        $quiz->timelimit    = get_user_preferences('quizport_quiz_timelimit', 0);
        $quiz->delay1       = get_user_preferences('quizport_quiz_delay1', 0);
        $quiz->delay2       = get_user_preferences('quizport_quiz_delay2', 0);
        $quiz->delay3       = get_user_preferences('quizport_quiz_delay3', 2);
        $quiz->password     = get_user_preferences('quizport_quiz_password', '');
        $quiz->subnet       = get_user_preferences('quizport_quiz_subnet', '');
        $quiz->allowresume  = get_user_preferences('quizport_quiz_allowresume', 0);
        $quiz->reviewoptions = get_user_preferences('quizport_quiz_reviewoptions', 0);
        $quiz->attemptlimit = get_user_preferences('quizport_quiz_attemptlimit', 0);
        $quiz->scoremethod  = get_user_preferences('quizport_quiz_scoremethod', QUIZPORT_GRADEMETHOD_HIGHEST);
        $quiz->scoreignore  = get_user_preferences('quizport_quiz_scoreignore', QUIZPORT_NO);
        $quiz->scorelimit   = get_user_preferences('quizport_quiz_scorelimit', 100);
        $quiz->scoreweighting = get_user_preferences('quizport_quiz_scoreweighting', 100);
        $quiz->sortorder    = $sortorder++;
        $quiz->clickreporting = get_user_preferences('quizport_quiz_clickreporting', QUIZPORT_NO);
        $quiz->discarddetails = get_user_preferences('quizport_quiz_discarddetails', QUIZPORT_NO);

        if (! $quiz->id = $DB->insert_record('quizport_quizzes', $quiz)) {
            print_error('error_insertrecord', 'quizport', '', 'quizport_quizzes');
        }

        $newquizids[] = $quiz->id;
    }

    switch ($afterquizid) {
        case -1:
            // insert new quizzes at start of unit
            $quizids = array_merge($newquizids, $quizids);
            $reorder = true;
            break;
        case 0:
            // insert new quizzes at end of unit
            $reorder = false;
            break;
        default:
            // insert new quizzes after specific quiz
            if (($i = array_search($afterquizid, $quizids))===false) {
                // $afterquizid is invalid - shouldn't happen !!
                $reorder = false;
            } else {
                $quizids = array_merge(
                    array_slice($quizids, 0, ($i+1)), $newquizids, array_slice($quizids, ($i+1))
                );
                $reorder = true;
            }
    }
    if ($reorder) {
        $sortorder = 0;
        foreach ($quizids as $quizid) {
            $sortorder++;
            $DB->set_field('quizport_quizzes', 'sortorder', $sortorder, array('id'=>$quizid));
        }
    }
}

function quizport_update_events(&$quizport, &$unit, &$eventids, $delete) {
    global $CFG, $DB;

    static $stropens = '';
    static $strcloses = '';
    static $maxduration = null;

    if ($CFG->majorrelease>=2.0) {
        require_once($CFG->dirroot.'/calendar/lib.php');
    }

    // cache text strings and max duration (first time only)
    if (is_null($maxduration)) {
        if (isset($CFG->quizport_maxeventlength)) {
            $maxeventlength = $CFG->quizport_maxeventlength;
        } else {
            $maxeventlength = 5; // 5 days is default
        }
        // set $maxduration (secs) from $maxeventlength (days)
        $maxduration = $maxeventlength * 24 * 60 * 60;

        $stropens = get_string('quizportopens', 'quizport');
        $strcloses = get_string('quizportcloses', 'quizport');
    }

    // array to hold events for this quizport
    $events = array();

    // set duration
    if ($unit->timeclose && $unit->timeopen) {
        $duration = max(0, $unit->timeclose - $unit->timeopen);
    } else {
        $duration = 0;
    }

    if ($duration > $maxduration) {
        // long duration, two events
        $events[] = (object)array(
            'name' => $quizport->name.' ('.$stropens.')',
            'eventtype' => 'open',
            'timestart' => $unit->timeopen,
            'timeduration' => 0
        );
        $events[] = (object)array(
            'name' => $quizport->name.' ('.$strcloses.')',
            'eventtype' => 'close',
            'timestart' => $unit->timeclose,
            'timeduration' => 0
        );
    } else if ($duration) {
        // short duration, just a single event
        $events[] = (object)array(
            'name' => $quizport->name,
            'eventtype' => 'open',
            'timestart' => $unit->timeopen,
            'timeduration' => $duration,
        );
    } else if ($unit->timeopen) {
        // only an open date
        $events[] = (object)array(
            'name' => $quizport->name.' ('.$stropens.')',
            'eventtype' => 'open',
            'timestart' => $unit->timeopen,
            'timeduration' => 0,
        );
    } else if ($unit->timeclose) {
        // only a closing date
        $events[] = (object)array(
            'name' => $quizport->name.' ('.$strcloses.')',
            'eventtype' => 'close',
            'timestart' => $unit->timeclose,
            'timeduration' => 0,
        );
    }

    // cache description and visiblity (saves doing it twice for long events)
    if (empty($unit->entrytext)) {
        $description = '';
    } else if ($CFG->majorrelease<=1.9) {
        $description = addslashes($unit->entrytext);
    } else {
        $description = $unit->entrytext;
    }
    $visible = instance_is_visible('quizport', $quizport);

    foreach ($events as $event) {
        if ($CFG->majorrelease<=1.9) {
            $event->name = addslashes($event->name);
        }
        $event->groupid = 0;
        $event->userid = 0;
        $event->courseid = $quizport->course;
        $event->modulename = 'quizport';
        $event->instance = $quizport->id;
        $event->description = $description;
        $event->visible = $visible;
        if (count($eventids)) {
            if ($CFG->majorrelease<=1.9) {
                $event->id = array_shift($eventids);
                update_event($event);
            } else {
                $event->id = array_shift($eventids);
                $calendarevent = calendar_event::load($event->id);
                $calendarevent->update($event);
            }
        } else {
            if ($CFG->majorrelease<=1.9) {
                add_event($event);
            } else {
                calendar_event::create($event);
            }
        }
    }

    // delete surplus events, if required
    if ($delete) {
        while (count($eventids)) {
            $id = array_shift($eventids);
            if ($CFG->majorrelease<=1.9) {
                delete_event($id);
            } else {
                $event = calendar_event::load($id);
                $event->delete();
            }
        }
    }
}

/*
* Given an ID of an instance of this module, this function will
* permanently delete the instance and any data that depends on it.
*
* This function is called from:
*         {@link course/mod.php}
*         {@link lib/moodlelib.php}::{@link remove_course_contents()}

* @param integer $id the id, from the "quizport" table, of the quizport to be deleted
* @return boolean true if the quizport data was successfully deleted, false otherwise
*/
function quizport_delete_instance($quizportid) {
    global $CFG, $DB;

    if ($CFG->majorrelease>=2.0) {
        require_once($CFG->dirroot.'/calendar/lib.php');
    }

    // check $quizportid is valid
    if (! $quizport = $DB->get_record('quizport', array('id'=>$quizportid))) {
    //    return false;
    }

    // delete $quizport record
    if (! $DB->delete_records('quizport', array('id'=>$quizportid))) {
    //    return false;
    }

    // delete related grades, attempts, unit and quizzes
    $DB->delete_records('quizport_unit_grades', array('parenttype'=>QUIZPORT_PARENTTYPE_ACTIVITY, 'parentid'=>$quizportid));

    if ($unit = $DB->get_record('quizport_units', array('parenttype'=>QUIZPORT_PARENTTYPE_ACTIVITY, 'parentid'=>$quizportid))) {

        $DB->delete_records('quizport_units', array('id'=>$unit->id));
        $DB->delete_records('quizport_unit_attempts', array('unitid'=>$unit->id));

        if ($quizzes = $DB->get_records('quizport_quizzes', array('unitid'=>$unit->id), '', 'id, id AS quizid')) {
            quizport_delete_quizzes(implode(',', array_keys($quizzes)));
        }
    }

    // delete blocks (Moodle >= 1.6)
    if (function_exists('page_import_types') && $CFG->majorrelease<=1.9) {
        $select = "pageid=$quizportid AND pagetype ".$DB->sql_ilike()." 'mod-quizport%'";
        if ($instances = $DB->get_records_select('block_instance', $select)) {
            foreach ($instances as $instance) {
                delete_context(CONTEXT_BLOCK, $instance->id);
            }
        }
        $DB->delete_records_select('block_instance', $select);
    }

    // delete files (Moodle >= 2.0)
    if (function_exists('get_file_storage')) {
        $fs = get_file_storage();
        if ($cm = get_coursemodule_from_instance('quizport', $quizportid)) {
            $context = get_context_instance(CONTEXT_MODULE, $cm->id);
            $fs->delete_area_files($context->id);
        }
    }

    // delete calendar events
    if ($events = $DB->get_records('event', array('modulename'=>'quizport', 'instance'=>$quizportid), '', 'id, id AS eventid')) {
        foreach ($events as $event) {
            if ($CFG->majorrelease<=1.9) {
                delete_event($event->id);
            } else {
                $event = calendar_event::load($event->id);
                $event->delete();
            }
        }
    }

    // delete Moodle grade
    if ($quizport) {
        quizport_grade_item_delete($quizport);
    }

    // all done
    return true;
}

function quizport_delete_quizzes($quizids) {
    global $DB;

    if (strpos($quizids, ',')) {
        // a  list of quiz ids
        $IN = 'IN';
        $quizids = "($quizids)";
    } else {
        // a single quiz id
        $IN = '=';
    }
    $DB->delete_records_select('quizport_quizzes', "id $IN $quizids");
    $DB->delete_records_select('quizport_quiz_scores', "quizid $IN $quizids");
    $DB->delete_records_select('quizport_cache', "quizid $IN $quizids");
    $DB->delete_records_select('quizport_conditions', "quizid $IN $quizids OR conditionquizid $IN $quizids OR nextquizid $IN $quizids");
    $DB->delete_records_select('quizport_questions', "quizid $IN $quizids");

    if ($attempts = $DB->get_records_select('quizport_quiz_attempts', "quizid $IN $quizids", null, '', 'id, id AS quizattemptid')) {
        $attemptids = implode(',', array_keys($attempts));
        $DB->delete_records_select('quizport_quiz_attempts', "id IN ($attemptids)");
        $DB->delete_records_select('quizport_details',  "attemptid IN ($attemptids)");
        $DB->delete_records_select('quizport_responses', "attemptid IN ($attemptids)");
    }
}

/*
* Print a detailed representation of what a given user
* has done with a particular quizport, for user activity reports.
*
* This function is called from: {@link course/user.php}
*
* @param $course object from the "course" table
* @param $user object from the "user" table
* @param $module object from the "modules" table
* @param $quizport object from the "quizport" table
* @return no return value is required
*/
function quizport_user_complete($course, $user, $module, $quizport) {
    global $CFG, $DB;

    $quizport_filter = "parenttype=".QUIZPORT_PARENTTYPE_ACTIVITY." AND parentid=$quizport->id";
    if ($unitgrade = $DB->get_record_select('quizport_unit_grades', "$quizport_filter AND userid=$user->id")) {

        $href = $CFG->wwwroot.'/mod/quizport/report.php?unitgradeid='.$unitgrade->id;
        $link = '<a href="'.$href.'">'.$unitgrade->grade.'%</a>';
        print get_string('grade', 'quizport').': '.$link.' '.quizport_format_status($unitgrade->status, true);

        $start_table = false;
        $unitid = "(SELECT id FROM {quizport_units} WHERE $quizport_filter)";
        if ($unitattempts = $DB->get_records_select('quizport_unit_attempts', "unitid=$unitid AND userid=$user->id", null, 'unumber')) {

            $unumbers = array();

            $quizids = "(SELECT id FROM {quizport_quizzes} WHERE unitid=$unitid ORDER BY sortorder)";
            if ($quizscores = $DB->get_records_select('quizport_quiz_scores', "quizid IN $quizids AND userid=$user->id", null, 'unumber')) {

                foreach (array_keys($quizscores) as $id) {
                    $unumber = $quizscores[$id]->unumber;
                    if (! array_key_exists($unumber, $unumbers)) {
                        $unumbers[$unumber] = array();
                    }
                    $unumbers[$unumber][] = &$quizscores[$id];
                }
            }

            foreach ($unitattempts as $unitattempt) {
                if ($start_table==false) {
                    $start_table =  true;
                    print '<table border="1" cellpadding="4" cellspacing="4"><tbody>'."\n";
                }

                $href = $CFG->wwwroot.'/mod/quizport/report.php?unitattemptid='.$unitattempt->id;
                $grade = '<a href="'.$href.'">'.$unitattempt->grade.'%</a>';
                $time = userdate($unitattempt->timemodified, get_string('strftimerecentfull'));
                $duration = quizport_format_time($unitattempt->duration);
                $status = quizport_format_status($unitattempt->status);
                $unumber = get_string('attemptnumber', 'quizport', $unitattempt->unumber);
                print '<tr><td colspan="2">'."$unumber: $grade ($status) $time ($duration)".'</td></tr>'."\n";

                //foreach ($unumbers[$unitattempt->unumber] as $quizscore) {
                //    $href = $CFG->wwwroot.'/mod/quizport/report.php?quizscoreid='.$quizscore->id;
                //    $score = '<a href="'.$href.'">'.$quizscore->score.'%</a>';
                //    $time = userdate($quizscore->timemodified, get_string('strftimerecentfull'));
                //    $duration = quizport_format_time($quizscore->duration);
                //    $status = quizport_format_status($quizscore->status);
                //    $unumber = get_string('attemptnumber', 'quizport', $unitattempt->unumber);
                //    print '<tr><td align="center" valign="middle" rowspan="2">icon</td><td>Quiz name</td></tr>'."\n";
                //    print '<tr><td>'.get_string('score', 'quizport').": $score".'</td></tr>'."\n";
                //}
            }
        }
        if ($start_table) {
            print '</tbody></table>'."\n";
        }
    } else {
        print get_string('notattemptedyet', 'quizport');
    }
}

/*
* Return a small object with summary information about what a
* user has done with a particular instance of this module (=quizport).
* Used for user activity reports (course/user.php).
*
* This function is called from: {@link course/user.php}
*
* @param object $course a record from the "course" table
* @param object $user a record from the "user" table
* @param object $coursemodule a record from the "course_modules" table
* @param object $quizport a record from the "quizport" table
* @return mixed
*         object $report
*             $report->info = a short text description of what was done
*             $report->time = the time it was done
*         false : there is no user activity to report
*/
function quizport_user_outline($course, $user, $coursemodule, $quizport) {
    global $DB;
    if (! $grade = $DB->get_record('quizport_unit_grades', array('parenttype'=>QUIZPORT_PARENTTYPE_ACTIVITY, 'parentid'=>$quizport->id, 'userid'=>$user->id))) {
        return false;
    }

    return (object)array(
        'info' => get_string('grade').': '.$grade->grade.'% '.quizport_format_status($grade->status, true),
        'time' => $grade->timemodified
    );
}

/*
* Given a course_module object, this function returns any
* "extra" information that may be needed when printing
* this activity in a course listing.
*
* This function is called from: {@link course/lib.php}::{@link get_array_of_activities()}
*
* @param object $m information about this course module
*         $coursemodule->cm : id in the "course_modules" table
*         $coursemodule->section : the number of the course section (e.g. week or topic)
*         $coursemodule->mod : the name of the module (always "quizport")
*         $coursemodule->instance : id in the "quizport" table
*         $coursemodule->name : the name of this quizport
*         $coursemodule->visible : is the quizport visible (=1) or hidden (=0)
*         $coursemodule->extra : ""
* @return object $info
*         $info->extra : extra string to include in any link
*                 (e.g. target="_blank" or class="quizport_completed")
*         $info->icon : an icon for this course module
*                 allows a different icon for different subtypes of the module
*                 allows a different icon depending on the status of a quizport
*/
function quizport_get_coursemodule_info($coursemodule) {
/// See get_array_of_activities() in course/lib.php

    global $CFG, $DB;
    if (! $unit = $DB->get_record('quizport_units', array('parenttype'=>QUIZPORT_PARENTTYPE_ACTIVITY, 'parentid'=>$coursemodule->instance))) {
        // invalid $coursemodule->instance - shouldn't happen !!
        return false;
    }

    $info = (object)array(
        'extra' => 'id="quizport-'.$coursemodule->instance.'"'
    );

    // create popup link, if necessary
    if ($unit->showpopup) {
        $popupoptions = implode(',', preg_grep('/^moodle/i', explode(',', $unit->popupoptions), PREG_GREP_INVERT));
        $info->extra .= urlencode(' onclick="this.target='."'quizport{$coursemodule->instance}'; return openpopup('/mod/quizport/view.php?inpopup=true&amp;id={$coursemodule->id}','quizport{$coursemodule->instance}','{$popupoptions}'".');"');
    }

    if ($CFG->majorrelease<=1.2) {
        return $info->extra;
    } else {
        return $info;
    }
}

/*
* Given a course and a start time, this function finds recent activity (from the Moodle logs)
* that has occurred in quizport activities and prints it out.
*
* This function is called from: {@link course/lib.php}::{@link print_recent_activity()}
* which in turn is called from the blocks/recent_activity block
* when the "recent activity" block is enabled on a Moodle course page
*
* @param object $course a record from the "course" table
* @param boolean $viewfullnames (formerly $isteacher)
*         true : user is alowed to see full names of students (moodle/site:viewfullnames)
*         false : user is not allowed to see full names of students
*         (this parameter is not used)
* @param integer $timestart the start time as a UNIX time stamp
* @return boolean true if there was output, or false is there was none.
*/

function quizport_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $DB;
    $result = false;

    // the Moodle "logs" table contains the following fields:
    //     time, userid, course, ip, module, cmid, action, url, info

    // this function utilitizes the following index on the log table
    //     log_timcoumodact_ix : time, course, module, action

    // log records are added by the following function in "lib/datalib.php":
    //     add_to_log($courseid, $module, $action, $url='', $info='', $cm=0, $user=0)

    // log records are added by the following QuizPort scripts:
    //     (scriptname : log action)
    //     editcolumnlists.php : editcolumnlists
    //     editcondition.php : editcondition
    //     editquiz.php : editquiz
    //     editquizzes.php : editquizzes
    //     editunits.php : editunits
    //     index.php : viewindex
    //     report.php : report
    //     view.php : view, submit
    // except for editunits and viewindex, all these actions have a record in the "log_display" table

    $select = "time>$timestart AND course=$course->id AND module='quizport' AND (action='add' OR action='update' OR action='view' OR action='submit')";
    if ($logs = $DB->get_records_select('log', $select, null, 'time ASC')) {

        $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
        $viewhiddensections = has_capability('moodle/course:viewhiddensections', $coursecontext);

        if ($modinfo = unserialize($course->modinfo)) {
            $coursemoduleids = array_keys($modinfo);
        } else {
            $coursemoduleids = array();
        }

        $stats = array();
        foreach ($logs as $log) {
            if (isset($log->cmid)) {
                // Moodle >= 1.2
                $cmid = $log->cmid;
            } else {
                // Moodle <= 1.1
                $cmid = $log->info;
            }
            if (! array_key_exists($cmid, $modinfo)) {
                // invalid $cmid !!
                continue;
            }
            if (! $viewhiddensections && ! $modinfo[$cmid]->visible) {
                // coursemodule is hidden from user
                continue;
            }
            $sortorder = array_search($cmid, $coursemoduleids);
            if (! array_key_exists($sortorder, $stats)) {
                $stats[$sortorder] = (object)array(
                    'name' => format_string(urldecode($modinfo[$cmid]->name)),
                    'cmid' => $cmid, 'add' => 0, 'update' => 0, 'view' => 0, 'submit' => 0,
                    'viewreport' => has_capability('mod/quizport:viewreports', get_context_instance(CONTEXT_MODULE, $cmid)),
                    'users' => array()
                );
            }
            $action = $log->action;
            switch ($action) {
                case 'add' :
                case 'update' :
                    // store most recent time
                    $stats[$sortorder]->$action = $log->time;
                    break;
                case 'view' :
                case 'submit' :
                    // increment counter
                    $stats[$sortorder]->$action ++;
                    break;
            }
            $stats[$sortorder]->users[$log->userid] = true;
        }

        $strusers = '<b>'.get_string('users').'</b>';
        $stradded = '<b>'.get_string('added', 'quizport').'</b>';
        $strupdated = '<b>'.get_string('updated', 'quizport').'</b>';
        $strviewed = '<b>'.get_string('viewed', 'quizport').'</b>';
        $strsubmitted = '<b>'.get_string('submitted', 'quizport').'</b>';

        $print_headline = true;
        ksort($stats);
        foreach ($stats as $stat) {
            $li = array();
            if ($stat->add) {
                $li[] = $stradded.': '.userdate($stat->add);
            }
            if ($stat->update) {
                $li[] = $strupdated.': '.userdate($stat->update);
            }
            if ($stat->viewreport) {
                // link to a detailed report of recent activity for this quizport
                $href = "$CFG->wwwroot/course/recent.php?id=$course->id&amp;modid=$stat->cmid"; // &amp;date=$timestart
                if ($count = count($stat->users)) {
                    $li[] = $strusers.': <a href="'.$href.'">'.$count.'</a>';
                }
                if ($stat->view) {
                    $li[] = $strviewed.': <a href="'.$href.'">'.$stat->view.'</a>';
                }
                if ($stat->submit) {
                    $li[] = $strsubmitted.': <a href="'.$href.'">'.$stat->submit.'</a>';
                }
            }
            if (count($li)) {
                if ($print_headline) {
                    $print_headline = false;
                    print_headline(get_string('modulenameplural', 'quizport').':', 3);
                }
                print '<div class="quizportrecentactivity">';
                print '<p><a href="'.$CFG->wwwroot.'/mod/quizport/view.php?id='.$stat->cmid.'">'.format_string($stat->name).'</a></p>';
                print '<ul><li>'.implode('</li><li>', $li).'</li></ul>';
                print "</div>\n";
                $result = true;
            }
        }
    }
    return $result;
}

/*
* This function  returns activity for all quizports since a given time.
* It is initiated from the "Full report of recent activity" link in the "Recent Activity" block.
* Using the "Advanced Search" page (cousre/recent.php?id=99&advancedfilter=1),
* results may be restricted to a particular course module, user or group
*
* This function is called from: {@link course/recent.php}
*
* @param array(object) $activities information about course module acitivities
* @param integer $index length of the $activities array
* @param integer $date start date, as a UNIX date
* @param integer $courseid id in the "course" table
* @param integer $coursemoduleid id in the "course_modules" table
* @param integer $userid id in the "users" table
* @param integer $groupid id in the "groups" table
* @return no return value is required, but $activities and $index are updated
*         for each quizport attempt, an $activity object is appended
*         to the $activities array and the $index is incremented
*         $activity->type : module type (always "quizport")
*         $activity->defaultindex : index of this object in the $activities array
*         $activity->instance : id in the "quizport" table;
*         $activity->name : name of this quizport
*         $activity->section : section number in which this quizport appears in the course
*         $activity->content : array(object) containing information about quizport attempts to be printed by {@link print_recent_mod_activity()}
*                 $activity->content->attemptid : id in the "quizport_quiz_attempts" table
*                 $activity->content->attempt : the number of this attempt at this quiz by this user
*                 $activity->content->score : the score for this attempt
*                 $activity->content->timestart : the server time at which this attempt started
*                 $activity->content->timefinish : the server time at which this attempt finished
*         $activity->user : object containing user information
*                 $activity->user->userid : id in the "user" table
*                 $activity->user->fullname : the full name of the user (see {@link lib/moodlelib.php}::{@link fullname()})
*                 $activity->user->picture : $record->picture;
*         $activity->timestamp : the time that the content was recorded in the database
*/
function quizport_get_recent_mod_activity(&$activities, &$index, $date, $courseid, $coursemoduleid=0, $userid=0, $groupid=0) {
    global $CFG, $DB;
    if (! $course = $DB->get_record('course', array('id'=>$courseid))) {
        // invalid course id !!
        return;
    }
    if (! $modinfo = unserialize($course->modinfo)) {
        // no activity mods !!
        return;
    }
    $quizports = array();
    foreach (array_keys($modinfo) as $cmid) {
        if ($modinfo[$cmid]->mod=='quizport' && ($coursemoduleid==0 || $coursemoduleid==$cmid)) {
            // save mapping from quizportid => coursemoduleid
            if (isset($modinfo[$cmid]->id)) {
                // Moodle 1.7 and later
                $quizports[$modinfo[$cmid]->id] = $cmid;
            }
            // initialize array of users who have recently attempted this QuizPort
            $modinfo[$cmid]->users = array();
        } else {
            // we are not interested in this mod
            unset($modinfo[$cmid]);
        }
    }
    if (count($modinfo)==0) {
        // no quizports
        return;
    }
    if (count($quizports)==0) {
        // Moodle 1.6 and earlier
        $select = "id IN (".implode(',', array_keys($modinfo)).')';
        $quizports = $DB->get_records_select_menu('course_modules', $select, null, 'id', 'instance AS id, id AS cmid');
    }

    $tables = "{quizport_units} qu, {quizport_unit_attempts} qua, {user} u";
    $fields = 'qua.*, qu.parentid AS quizportid, u.firstname, u.lastname, u.picture';
    $select = ''
        .'qu.parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY
        .' AND qu.parentid IN ('.implode(',', array_keys($quizports)).')'
        .' AND qua.unitid=qu.id'
        .' AND qua.userid=u.id'
    ;
    if ($groupid) {
        // restrict search to a users from a particular group
        $tables .= ", {groups_members} gm";
        $select .= " AND qua.userid=gm.userid AND gm.id=$groupid";
    }
    if ($userid) {
        // restrict search to a single user
        $select = ' AND qua.userid='.$userid;
    }
    $select .= ' AND qua.timemodified>'.$date;

    if (! $attempts = $DB->get_records_sql("SELECT $fields FROM $tables WHERE $select ORDER BY qua.userid, qua.unumber")) {
        // no recent attempts at these quizports
        return;
    }

    foreach (array_keys($attempts) as $attemptid) {
        $attempt = &$attempts[$attemptid];

        $cmid = $quizports[$attempt->quizportid];
        $mod = &$modinfo[$cmid];

        $userid = $attempt->userid;
        if (! array_key_exists($userid, $mod->users)) {
            $mod->users[$userid] = (object)array(
                'userid' => $userid,
                'fullname' => fullname($attempt),
                'picture' => $attempt->picture,
                'attempts' => array(),
            );
        }
        // add this attempt by this user at this course module
        $mod->users[$userid]->attempts[$attempt->unumber] = &$attempt;
    }

    foreach (array_keys($modinfo) as $cmid) {
        $mod =&$modinfo[$cmid];
        if (empty($mod->users)) {
            continue;
        }
        // add an activity object for each user's attempts at this quizport
        foreach (array_keys($mod->users) as $userid) {
            $user =&$mod->users[$userid];

            // get index of last (=most recent) attempt
            $max_unumber = max(array_keys($user->attempts));

            $activities[$index++] = (object)array(
                'type' => 'quizport',
                'cmid' => $cmid,
                'name' => format_string(urldecode($mod->name)),
                'user' => (object)array(
                    'userid' => $user->userid,
                    'fullname' => $user->fullname,
                    'picture' => $user->picture
                ),
                'attempts' => $user->attempts,
                'timestamp' => $user->attempts[$max_unumber]->timemodified
            );
        }
    }
}

/*
* This function prints an $activity object which was generated by {@link get_recent_mod_activity()}
*
* This function is called from: {@link course/recent.php}
*
* @param object $activity an object created by {@link get_recent_mod_activity()}
* @param integer $courseid id in the "course" table
* @param boolean $detail
*         true : print a link to the quizport activity
*         false : do no print a link to the quizport activity
* @return no return value is required
*/
function quizport_print_recent_mod_activity($activity, $courseid, $detail=false) {
/// Basically, this function prints the results of "get_recent_activity"
    global $CFG;

    static $dateformat = null;
    if (is_null($dateformat)) {
        $dateformat = get_string('strftimerecentfull');
    }

    print '<table border="0" cellpadding="3" cellspacing="0">'."\n";

    if ($detail) {
        print '<tr><td width="15">&nbsp;</td><td colspan="5">';

        // activity icon and link to activity
        $src = "$CFG->modpixpath/$activity->type/icon.gif";
        print '<img src="'.$src.'" class="icon" alt="'.$activity->name.'" /> ';

        // link to activity
        $href = "$CFG->wwwroot/mod/quizport/view.php?id=$activity->cmid";
        print '<a href="'.$href.'">'.$activity->name.'</a>';

        print '</td></tr>'."\n";
    }

    $rowspan = count($activity->attempts) + 1;
    print '<tr><td width="15" rowspan="'.$rowspan.'">&nbsp;</td>';
    print '<td class="forumpostpicture" width="35" valign="top" rowspan="'.$rowspan.'">';
    print_user_picture($activity->user->userid, $courseid, $activity->user->picture);
    $href = $CFG->wwwroot.'/user/view.php?id='.$activity->user->userid.'&amp;course='.$courseid;
    print '</td><td colspan="4"><a href="'.$href.'">'.$activity->user->fullname.'</a></td></tr>'."\n";

    foreach ($activity->attempts as $attempt) {
        $href = "$CFG->wwwroot/mod/quizport/report.php?unitattemptid=".$attempt->id;
        if ($attempt->duration) {
            $duration = '('.quizport_format_time($attempt->duration).')';
        } else {
            $duration = '&nbsp;';
        }
        print '<tr><td>'.$attempt->unumber.'. </td>';
        print '<td>'.$attempt->grade.'%</td>';
        print '<td>'.quizport_format_status($attempt->status, true).'</td>';
        print '<td><a href="'.$href.'">'.userdate($attempt->timemodified, $dateformat).'</a></td>';
        print '<td>'.$duration.'</td></tr>'."\n";
    }

    print '</table>'."\n";
}

/*
* This function is run periodically according to the moodle
* It searches for things that need to be done,
* such as sending out mail, toggling flags etc ...
*
* This function is called from: {@link admin/cron.php}
*
* @return boolean
*         true : the function completed successfully
*         false : the function did not complete successfuly
*                 NOTE: even if this function fails, "admin/cron.php"  will not print an error message
*                 to report an error, use: mtrace("Error: there was a problem in the QuizPort cron script");
*/
function quizport_cron() {
    global $CFG, $DB, $QUIZPORT;

    if (empty($CFG->quizport_enablecron)) {
        return true; // QuizPort cron is not enabled
    }

    if (! preg_match('/\b'.gmdate('G').'\b/', $CFG->quizport_enablecron)) {
        return true; // QuizPort cron may not run at this time
    }

    if ($CFG->quizport_enablecache) {
        global $course, $module, $coursemodule, $quizport, $unit, $quiz;

        // start quizport messages on a new line
        mtrace('');

        // get QuizPort object class
        require_once($CFG->dirroot.'/mod/quizport/class.php');

        // get names of PHP classes of all QuizPort output formats
        $classes = quizport_get_classes('output');

        // select only output formats that use the QuizPort cache
        $outputformats = array();
        foreach ($classes as $class) {
            if (substr($class, 0, 16)=='quizport_output_') {
                if (quizport_get_class_constant($class, 'use_quizport_cache')) {
                    $outputformats[] = substr($class, 16);
                }
            }
        }
        unset($classes, $class);
        $outputformats = implode("','", $outputformats);

        // SQL to extract quizzes whose cache is missing
        $tables = '{quizport_quizzes} qq LEFT JOIN {quizport_cache} qc ON qq.id=qc.quizid';
        $fields = 'qq.id, qq.unitid, qq.outputformat, qq.sourcetype, qc.quizid';
        $select = ''
            ."((qq.outputformat<>'' AND qq.outputformat IN ('$outputformats'))"
            ." OR (qq.outputformat='' AND qq.sourcetype IN ('$outputformats')))"
            ." AND qc.quizid IS NULL"
        ;

        // select first 10 quizzes whose cache is missing
        if ($quizzes = $DB->get_records_sql("SELECT $fields FROM $tables WHERE $select", null, 0, 10)) {

            // create sql to get a quiz and all its associated records
            $select = array(
                'qq.unitid=qu.id',
                'qu.parenttype=0 AND qu.parentid=q.id',
                'q.id=cm.instance AND cm.module=m.id',
                "m.name='quizport'",
                'cm.course=c.id'
            );
            if ($CFG->majorrelease<=1.4) {
                $select[] = 'cm.deleted=0';
            }
            $tablenames = array(
                'quizport_quizzes' => 'qq',
                'quizport_units' => 'qu',
                'quizport' => 'q',
                'modules' => 'm',
                'course_modules' => 'cm',
                'course' => 'c',
            );

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
            $tables = implode(',', $tables);
            $fields = implode(',', $fields);
            $select = implode(' AND ', $select);

            // sanity check
            if (empty($tables) || empty($fields)) {
                return false;
            }

            foreach ($quizzes as $quiz) {
                if (isset($unit) && $unit->id==$quiz->unitid) {
                    // quiz is from the same unit as the previous quiz, so just get the new quiz record
                    if (! $quiz = $DB->get_record('quizport_quizzes', array('id'=>$quiz->id))) {
                        continue;
                    }
                } else {
                    // get quiz, unit, quizport, coursemodule, module, course records
                    if (! $record = $DB->get_record_sql("SELECT $fields FROM $tables WHERE qq.id=$quiz->id AND $select")) {
                        continue;
                    }
                    foreach(get_object_vars($record) as $field=>$value) {
                        list($tablealias, $field) = explode('_', $field, 2);
                        switch ($tablealias) {
                            case 'qq': $quiz->$field = $value; break;
                            case 'qu': $unit->$field = $value; break;
                            case 'q': $quizport->$field = $value; break;
                            case 'cm': $coursemodule->$field = $value; break;
                            case 'm': $module->$field = $value; break;
                            case 'c': $course->$field = $value; break;
                        }
                    }
                }

                // create new cache content for this quiz
                mtrace(' - creating QuizPort cache: quizid='.$quiz->id.' ...');
                $QUIZPORT = new mod_quizport();
                $QUIZPORT->quiz->output->generate(true);
                $QUIZPORT = null;
            }
        }
    }

    return true;
}

/*
* This function returns an array of grades, indexed by user, for a given quizport activity.
* It also returns a maximum allowed grade for the quizport activity.
*
* This function is called (in Moodle <= 1.8) from:
*         {@link course/grade.php}
*         {@link course/grades.php}
*         {@link grade/lib.php}
*                 {@link grade_set_uncategorized()}
*                 {@link grade_download()}
*                 {@link grade_set_categories()}
*                 {@link print_student_grade()}
*
* in Moodle 1.9 we need to hide this function
* so that is is not used in "lib/gradelib.php"
*
* @param integer $quizportid id in the "quizport" table
* @return object $quizportgrades
*         $quizportgrades->grades : array of grades indexed by user id
*         $quizportgrades->maxgrade : the maximum grade for this quizport
*/
if ($CFG->majorrelease<=1.8) {
    function quizport_grades($quizportid) {
        global $DB;
        $select = 'parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY.' AND parentid='.$quizportid;
        return (object)array(
            'maxgrade' => $DB->get_field_select('quizport_units', 'gradelimit', $select),
            'grades' => $DB->get_records_select_menu('quizport_unit_grades', $select, null, 'userid', 'userid AS id, grade')
        );
    }
}

/*
* This function returns an array of user ids who are participants for a given quizport.
* Must include every user involved in the instance, independent of their role (student, teacher, admin...)
*
* This function is called from: {@link backup/backuplib.php}
*
* @param integer $quizportid id in the "quizport" table
* @return array of user ids who are participants in this quizport
*/
function quizport_get_participants($quizportid) {
    global $DB;
    $select = 'parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY.' AND parentid='.$quizportid;
    return $DB->get_records_select('quizport_unit_grades', $select, null, 'userid', 'userid AS id, userid');
}

/*
 * This function checks if a scale is being used by an quizport
 * It used by {@link course/scales.php} for displaying scales
 * and by the backup code to decide whether to back up a scale
 *
* This function is called from: {@link lib/moodlelib.php}:: {@link course_scale_used()}
*
 * @param  integer $quizportid id in the "quizport" table
 * @param integer $scaleid id in the "scale" table
 * @return boolean
 *         true : the scale is used by the quizport
 *         false : the scale is not used by the quizport
*/
function quizport_scale_used($quizportid, $scaleid) {
    return false;
}

/**
 * Checks if scale is being used by any instance of quizport
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any quizport
 */
function quizport_scale_used_anywhere($scaleid) {
    return false;
}

/*
* This function defines what log actions will be selected from the Moodle logs
* and displayed for course -> report -> activity module -> Hot Potatoes Quiz -> View OR All actions
*
* This function is called from: {@link course/report/participation/index.php}
* @return array(string) of text strings used to log QuizPort view actions
*/
function quizport_get_view_actions() {
    return array('view', 'viewindex', 'report', 'review');
}

/*
* This function defines what log actions will be selected from the Moodle logs
* and displayed for course -> report -> activity module -> Hot Potatoes Quiz -> Post OR All actions
*
* This function is called from: {@link course/report/participation/index.php}
* @return array(string) of text strings used to log QuizPort post actions
*/
function quizport_get_post_actions() {
    return array('submit');
}

/*
* For the given list of courses, this function creates an HTML report
* of which QuizPort activities have been completed and which have not

* This function is called from: {@link course/lib.php}
*
* @param array(object) $courses records from the "course" table
* @param array(array(string)) $htmlarray array, indexed by courseid, of arrays, indexed by module name (e,g, "quizport), of HTML strings
*         each HTML string shows a list of the following information about each open QuizPort in the course
*                 QuizPort name and link to the activity
*                 close date, if any
*                 for teachers:
*                         how many students have / haven't attempted the QuizPort
*                 for students:
*                         which QuizPorts have been completed
*                         which QuizPorts have not been completed yet
*                         the time remaining for incomplete QuizPorts
* @return no return value is required, but $htmlarray may be updated
*/
function quizport_print_overview($courses, &$htmlarray) {
    global $CFG, $DB, $USER;

    if (empty($CFG->quizport_enablemymoodle)) {
        return; // QuizPorts are not shown on MyMoodle on this site
    }

    if (! isset($courses) || ! is_array($courses) || ! count($courses)) {
        return; // no courses
    }

    if (! $instances = get_all_instances_in_courses('quizport', $courses)) {
        return; // no quizports
    }

    $strquizport = get_string('modulename', 'quizport');
    $strunitopen = get_string('unitopen', 'quizport');
    $strunitclose = get_string('unitclose', 'quizport');
    $strdateformat = get_string('strftimerecentfull'); // strftimedaydatetime, strftimedatetime
    $strattempted = get_string('attempted', 'quizport');
    $strcompleted = get_string('completed', 'quizport');
    $strnotattemptedyet = get_string('notattemptedyet', 'quizport');

    $quizports = array();
    foreach ($instances as $i=>$instance) {
        $quizports[$instance->id] = &$instances[$i];
    }

    // get related unit records - we especially want the time open/close and the grade limit/weighting
    $select = ''
        .'parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY
        .' AND parentid IN ('.implode(',', array_keys($quizports)).')'
    ;
    if (! $units = $DB->get_records_select('quizport_units', $select, null, '', 'id,parentid,parenttype,timeopen,timeclose,gradelimit,gradeweighting')) {
        return; // no units - shouldn't happen !!
    }
    foreach ($units as $id=>$unit) {
        $quizports[$unit->parentid]->unit = &$units[$id];
    }

    // get all grades for this user - saves getting them individually for students later on
    if (! $unitgrades = $DB->get_records_select('quizport_unit_grades', $select.' AND userid='.$USER->id)) {
        $unitgrades = array();
    }

    // map quizports onto grades for this user
    $unitgrades_by_quizport = array();
    foreach ($unitgrades as $id=>$unitgrade) {
        if (! isset($quizports[$unitgrade->parentid])) {
            continue; // shouldn't happen !!
        }
        $quizports[$unitgrade->parentid]->unitgrade = &$unitgrades[$id];
    }

    foreach ($quizports as $quizport) {
        $str = ''
            .'<div class="quizport overview">'
            .'<div class="name">'.$strquizport. ': '
            .'<a '.($quizport->visible ? '':' class="dimmed"')
            .'title="'.$strquizport.'" href="'.$CFG->wwwroot
            .'/mod/quizport/view.php?id='.$quizport->coursemodule.'">'
            .format_string($quizport->name).'</a></div>'
        ;
        if ($quizport->unit->timeopen) {
            $str .= '<div class="info">'.$strunitopen.': '.userdate($quizport->unit->timeopen, $strdateformat).'</div>';
        }
        if ($quizport->unit->timeclose) {
            $str .= '<div class="info">'.$strunitclose.': '.userdate($quizport->unit->timeclose, $strdateformat).'</div>';
        }

        $modulecontext = get_context_instance(CONTEXT_MODULE, $quizport->coursemodule);
        if (has_capability('mod/quizport:grade', $modulecontext)) {
            // manager: show class grades stats
            // attempted: 99/99, completed: 99/99
            if ($students = get_users_by_capability($modulecontext, 'mod/quizport:attempt', 'u.id,u.id', 'u.id', '', '', 0, '', false)) {
                $count = count($students);
                $attempted = 0;
                $completed = 0;
                if ($unitgrades = $DB->get_records_select('quizport_unit_grades', 'parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY.' AND parentid='.$quizport->id.' AND userid IN ('.implode(',', array_keys($students)).')')) {
                    $attempted = count($unitgrades);
                    foreach ($unitgrades as $unitgrade) {
                        if ($unitgrade->status==QUIZPORT_STATUS_COMPLETED) {
                            $completed++;
                        }
                    }
                    unset($unitgrades);
                }
                unset($students);
                $str .= '<div class="info">'.$strattempted.': '.$attempted.' / '.$count.', '.$strcompleted.': '.$completed.' / '.$count.'</div>';
            }
        } else {
            // student: show grade and status e.g. 90% (completed)
            if (empty($quizport->unitgrade)) {
                $str .= '<div class="info">'.$strnotattemptedyet.'</div>';
            } else {
                $href = $CFG->wwwroot.'/mod/quizport/report.php?unitgradeid='.$quizport->unitgrade->id;
                if ($quizport->unit->gradelimit && $quizport->unit->gradeweighting) {
                    $str .= '<div class="info">'.get_string('grade', 'quizport').': '.'<a href="'.$href.'">'.$quizport->unitgrade->grade.'%</a></div>';
                }
                $str .= '<div class="info">'.get_string('status', 'quizport').': '.'<a href="'.$href.'">'.quizport_format_status($quizport->unitgrade->status).'</a></div>';
            }
        }
        $str .= "</div>\n";

        if (empty($htmlarray[$quizport->course]['quizport'])) {
            $htmlarray[$quizport->course]['quizport'] = $str;
        } else {
            $htmlarray[$quizport->course]['quizport'] .= $str;
        }
    }
}

/**
 * add gradelimit and gradeweighting to quizport record
 *
 * @param object $quizport (passed by reference)
 * @return none, (but $quizport object may be modified)
 */
function quizport_add_grade_settings(&$quizport) {
    global $DB;

    // cache the quizport unit record to save multiple fetches of same settings from DB
    static $unit = false;

    if (isset($quizport->id) && (! isset($quizport->gradelimit) || ! isset($quizport->gradeweighting))) {
        if ($unit && $unit->parentid==$quizport->id) {
            // use previously fetched settings
        } else {
            // fetch new settings from DB
            $params = array('parentid'=>$quizport->id, 'parenttype'=>QUIZPORT_PARENTTYPE_ACTIVITY);
            $fields = 'id, parentid, parenttype, gradelimit, gradeweighting';
            $unit = $DB->get_record('quizport_units', $params, $fields);
        }
        if ($unit) {
            $quizport->gradelimit = $unit->gradelimit;
            $quizport->gradeweighting = $unit->gradeweighting;
        }
    }
}

/**
 * Return grade for given user or all users.
 *
 * @param object $quizport
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function quizport_get_user_grades($quizport, $userid=0) {
    global $DB;

    quizport_add_grade_settings($quizport);

    if ($quizport->gradelimit && $quizport->gradeweighting) {
        if ($quizport->gradeweighting>=100) {
            $precision = 0;
        } else if ($quizport->gradeweighting>=10) {
            $precision = 1;
        } else { // 1 - 10
            $precision = 2;
        }
        $rawgrade = "ROUND(grade * ($quizport->gradeweighting / $quizport->gradelimit), $precision)";
    } else {
        $rawgrade = '0';
    }

    $table = '{quizport_unit_grades}';
    $fields = "userid AS id, userid, $rawgrade AS rawgrade, timemodified AS datesubmitted";
    $select = 'parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY." AND parentid=$quizport->id";
    if ($userid) {
        $select .= " AND userid=$userid";
    }
    return $DB->get_records_sql("SELECT $fields FROM $table WHERE $select GROUP BY userid, grade, timemodified");
}

/**
 * Update grades in central gradebook
 * this function is called from db/upgrade.php
 *     it is initially called with no arguments, which forces it to get a list of all quizports
 *     it then iterates through the quizports, calling itself to create a grade record for each quizport
 *
 * @param object $quizport null means all quizports
 * @param int $userid specific user only, 0 means all users
 */
function quizport_update_grades($quizport=null, $userid=0, $nullifnone=true) {
    global $CFG, $DB;

    if ($CFG->majorrelease > 1.8) {
        require_once($CFG->dirroot.'/lib/gradelib.php');
    }

    quizport_add_grade_settings($quizport);

    if (is_null($quizport)) {
        // update/create grades for all quizports

        // set up sql strings
        $strupdating = get_string('updatinggrades', 'quizport');
        $fields = 'q.*, cm.idnumber AS cmidnumber, qu.gradelimit AS gradelimit, qu.gradeweighting AS gradeweighting';
        $tables = '{quizport_units} qu, {quizport} q, {course_modules} cm, {modules} m';
        $select = "m.name='quizport' AND m.id=cm.module AND cm.instance=q.id AND q.id=qu.parentid AND qu.parenttype=".QUIZPORT_PARENTTYPE_ACTIVITY;

        // get previous record index (if any)
        if (! $config = $DB->get_record('config', array('name'=>'quizport_update_grades'))) {
            $config = (object)array('id'=>0, 'name'=>'quizport_update_grades', 'value'=>'0');
        }
        $i_min = intval($config->value);

        $i_max = $DB->count_records_sql("SELECT COUNT('x') FROM $tables WHERE $select");
        if ($rs = $DB->get_recordset_sql("SELECT $fields FROM $tables WHERE $select")) {
            if ($CFG->majorrelease<=1.9) {
                $next = 'rs_fetch_next_record';
            } else {
                $next = 'next';
            }
            $bar = new progress_bar('quizportupgradegrades', 500, true);
            $i = 0;
            while ($quizport = $next($rs)) {

                // update grade
                if ($i >= $i_min) {
                    upgrade_set_timeout(60 * 5); // another 5 minutes
                    quizport_update_grades($quizport, 0, false);
                }

                // update progress bar
                $i++;
                $bar->update($i, $i_max, $strupdating.": ($i/$i_max)");

                // update record index
                if ($i > $i_min) {
                    $config->value = "$i";
                    if ($config->id) {
                        if (! $DB->update_record('config', $config)) {
                            print_error('error_updaterecord', 'quizport', '', 'config (id='.$config->id.')');
                        }
                    } else {
                        if (! $config->id = $DB->insert_record('config', $config)) {
                            print_error('error_insertrecord', 'quizport', '', 'config (name='.$config->name.')');
                        }
                    }
                }
            }
            if ($CFG->majorrelease<=1.9) {
                $rs->Close(); // Moodle 1.x
            } else {
                $rs->close(); // Moodle 2.x
            }
        }

        // delete the record index
        if ($config->id && ! $DB->delete_records('config', array('id'=>$config->id))) {
            print_error('error_deleterecords', 'quizport', '', 'config (id='.$config->id.')');
        }

    } else {
        // update/create grade for a single quizport
        if ($grades = quizport_get_user_grades($quizport, $userid)) {
            quizport_grade_item_update($quizport, $grades);

        } else if ($userid && $nullifnone) {
            // no grades for this user, but we must force the creation of a "null" grade record
            quizport_grade_item_update($quizport, (object)array('userid'=>$userid, 'rawgrade'=>null));

        } else {
            // no grades and no userid
            quizport_grade_item_update($quizport);
        }
    }
}

/**
 * Update/create grade item for given quizport
 *
 * @param object $quizport object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return object grade_item
 */
function quizport_grade_item_update($quizport, $grades=null) {
    global $CFG, $DB;

    if ($CFG->majorrelease > 1.8) {
        require_once($CFG->dirroot.'/lib/gradelib.php');
    }

    // maintain a cache of the maximum grade for each quizport
    static $maxgrades = array();
    if (array_key_exists($quizport->id, $maxgrades)) {
        $grademax = $maxgrades[$quizport->id];
    } else {
        $unit = $DB->get_record('quizport_units', array('parenttype'=>QUIZPORT_PARENTTYPE_ACTIVITY, 'parentid'=>$quizport->id), 'id,gradelimit,gradeweighting');
        if ($unit) {
            $grademax = $unit->gradelimit * ($unit->gradeweighting/100);
        } else {
            $grademax = 0;
        }
        $maxgrades[$quizport->id] = $grademax;
    }

    // set up params for grade_update()
    $params = array(
        'itemname' => $quizport->name
    );
    if (isset($quizport->cmidnumber)) {
        //cmidnumber may not be always present
        $params['idnumber'] = $quizport->cmidnumber;
    }
    if ($grademax) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $grademax;
        $params['grademin']  = 0;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
        // Note: when adding a new activity, a gradeitem will *not*
        // be created in the grade book if gradetype==GRADE_TYPE_NONE
        // A gradeitem will be created later if gradetype changes to GRADE_TYPE_VALUE
        // However, the gradeitem will *not* be deleted if the activity's
        // gradetype changes back from GRADE_TYPE_VALUE to GRADE_TYPE_NONE
        // Therefore, we give the user the ability to force the removal of empty gradeitems
        if (! empty($quizport->removegradeitem)) {
            $params['deleted'] = true;
        }
    }
    return grade_update('mod/quizport', $quizport->course, 'mod', 'quizport', $quizport->id, 0, $grades, $params);
}

/**
 * Delete grade item for given quizport
 *
 * @param object $quizport object
 * @return object grade_item
 */
function quizport_grade_item_delete($quizport) {
    global $CFG;
    if ($CFG->majorrelease > 1.8) {
        require_once($CFG->dirroot.'/lib/gradelib.php');
    }
    return grade_update('mod/quizport', $quizport->course, 'mod', 'quizport', $quizport->id, 0, null, array('deleted'=>1));
}

function quizport_get_question_name($question) {
    if ($name = quizport_strings($question->text)) {
        return $name;
    } else {
        return $question->name;
    }
}

function quizport_strings($ids) {
    global $DB;
    static $strings_cache = array();

    if (empty($ids)) {
        return '';
    }

    $all_ids = explode(',', $ids);
    $new_ids = array_diff($all_ids, array_keys($strings_cache));

    if (count($new_ids)) {
        if ($records = $DB->get_records_select('quizport_strings', "id IN ($ids)")) {
            foreach ($records as $id => $record) {
                $strings_cache[$id] = trim($record->string);
            }
        }
    }

    $strings = array();
    foreach ($all_ids as $id) {
        if (array_key_exists($id, $strings_cache) && $strings_cache[$id]!=='') {
            $strings[] = $strings_cache[$id];
        }
    }
    return implode(', ', $strings);
}

function quizport_string_ids($field_value, $max_field_length=255) {
    $ids = array();

    $strings = explode(',', $field_value);
    foreach($strings as $str) {
        if ($id = quizport_string_id($str)) {
            $ids[] = $id;
        }
    }
    $ids = implode(',', $ids);

    // we have to make sure that the list of $ids is no longer
    // than the maximum allowable length for this field
    if (strlen($ids) > $max_field_length) {

        // truncate $ids just before last comma in allowable field length
        // Note: largest possible id is something like 9223372036854775808
        //       so we must leave space for that in the $ids string
        $ids = substr($ids, 0, $max_field_length - 20);
        $ids = substr($ids, 0, strrpos($ids, ','));

        // create single $str(ing) containing all $strings not included in $ids
        $str = implode(',', array_slice($strings, substr_count($ids, ',') + 1));

        // append the id of the string containing all the strings not yet in $ids
        if ($id = quizport_string_id($str)) {
            $ids .= ','.$id;
        }
    }

    // return comma separated list of string $ids
    return $ids;
}

function quizport_string_id($str) {
    global $CFG, $DB;

    if (! isset($str) || ! is_string($str) || trim($str)=='') {
        // invalid input string
        return false;
    }

    // create md5 key
    $md5key = md5($str);

    if ($id = $DB->get_field('quizport_strings', 'id', array('md5key'=>$md5key))) {
        // string already exists
        return $id;
    }

    if ($CFG->majorrelease<=1.9) {
        $str = addslashes($str);
    }

    // create and insert a string record
    $record = (object)array(
        'string' => $str,
        'md5key' => $md5key
    );
    if (! $id = $DB->insert_record('quizport_strings', $record)) {
        print_error('error_insertrecord', 'quizport', '', 'quizport_strings');
    }

    // new string was successfully added
    return $id;
}

/*
* Given a relative folder name (below $CFG->dirroot),
* this function will search the folder and its subfolders
* and "include" any files called "class.php".
* The function returns a list of classes (=types) that were included
*
* @param string $folder : relative path (below $CFG->dirroot) of folder holding class definitions
*/
function quizport_get_classes($topfolder, $subfolder='') {
    global $CFG;

    static $cache = array();

    if ($subfolder=='') {
        if (isset($cache[$topfolder])) {
            // $cache for this folder has already been set, so just return that
            return $cache[$topfolder];
        }
        $cache[$topfolder] = array();
    }

    $folder = 'mod/quizport/'.$topfolder;
    if ($subfolder) {
        $folder .= "/$subfolder";
    }

    $filepath = "$CFG->dirroot/$folder/class.php";
    if (is_readable($filepath)) {

        require_once($filepath);

        // extract class name, e.g. quizport_file_hp_6_jcloze_xml
        // from $folder, e.g. mod/quizport/file/h6/6/jcloze/xml
        // by removing leading "mod/" and converting all "/" to "_"
        $cache[$topfolder][] = str_replace('/', '_', substr($folder, 4));

        // get subtypes, if any, for this plugin
        if ($plugins = get_list_of_plugins($folder)) {
            foreach ($plugins as $plugin) {
                if ($subfolder) {
                    $pluginfolder = "$subfolder/$plugin";
                } else {
                    $pluginfolder = $plugin;
                }
                quizport_get_classes($topfolder, $pluginfolder);
            }
        }
    }

    if ($subfolder=='') {
        return $cache[$topfolder];
    }
}

// simulate $class::$constant which is available from PHP 5.3
function quizport_get_class_constant($class, $constant) {
    if (class_exists($class)) {
        $vars = get_class_vars($class);
        if (isset($vars[$constant])) {
            return $vars[$constant];
        }
    }
    return '';
}

function quizport_get_cm(&$course, &$thiscm, $cmid, $type) {
    // gets the next, previous or specific Moodle activity
    global $CFG, $DB;

    if ($cmid==QUIZPORT_ACTIVITY_NONE) {
        return false;
    }
    if (! $modinfo = unserialize($course->modinfo)) {
        return false;
    }
    if (! isset($modinfo[$thiscm->id])) {
        return false;
    }

    // set default search values
    $id = 0;
    $module = '';
    $graded = 0;
    $section = -1;

    // restrict search values
    if ($cmid>0) {
        $id = $cmid;
    } else {
        if ($cmid==QUIZPORT_ACTIVITY_COURSE_QUIZPORT  || $cmid==QUIZPORT_ACTIVITY_SECTION_QUIZPORT) {
            $module = 'quizport';
        }
        if ($cmid==QUIZPORT_ACTIVITY_COURSE_GRADED  || $cmid==QUIZPORT_ACTIVITY_SECTION_GRADED) {
            $graded = ($CFG->majorrelease >= 1.9); // no grades before Moodle 1.9
        }
        if ($cmid==QUIZPORT_ACTIVITY_SECTION_ANY || $cmid==QUIZPORT_ACTIVITY_SECTION_GRADED || $cmid==QUIZPORT_ACTIVITY_SECTION_QUIZPORT) {
            $section = $modinfo[$thiscm->id]->section;
        }
    }

    // get cm ids for Moodle <= 1.6
    if ($CFG->majorrelease<=1.8) {
        $select = "course=$course->id";
        if ($section>=0) {
            $select .= " AND section=$section";
        }
        if ($module) {
            $select .= " AND module=(SELECT id FROM {modules} WHERE name='$module')";
        }
        $coursemodules = $DB->get_records_select('course_modules', $select, null, 'id', 'id,instance');
    }

    if ($graded) {
        $graded = array();
        $select = "courseid=$course->id AND itemtype='mod' AND gradetype<>0"; // 0 = GRADE_TYPE_NONE
        if ($items = $DB->get_records_select('grade_items', $select, null, 'id', 'id,itemmodule,iteminstance,gradetype')) {
            foreach ($items as $item) {
                if (empty($graded[$item->itemmodule])) {
                    $graded[$item->itemmodule] = array();
                }
                $graded[$item->itemmodule][$item->iteminstance] = true;
            }
        }
        unset($items);
    }

    // get cm ids (reverse order if necessary)
    $cmids = array_keys($modinfo);
    if ($type=='entry') {
        $cmids = array_reverse($cmids);
    }

    // search for next, previous or specific course module
    $found = false;
    foreach ($cmids as $cmid) {
        $cm = &$modinfo[$cmid];
        if ($id && $cm->cm!=$id) {
            continue; // wrong activity
        }
        if ($section>=0) {
            if ($type=='entry') {
                if ($cm->section>$section) {
                    continue; // later section
                }
                if ($cm->section<$section) {
                    return false; // previous section
                }
            } else { // exit (=next)
                if ($cm->section<$section) {
                    continue; // earlier section
                }
                if ($cm->section>$section) {
                    return false; // later section
                }
            }
        }

        if ($graded && empty($graded[$cm->mod][$cm->id])) {
            continue; // cm is not graded
        }
        if ($module && $cm->mod!=$module) {
            continue; // wrong module
        } else if ($cm->mod=='label') {
            continue; // skip labels
        }
        if ($found || $cm->cm==$id) {
            // a quick fix so that $cm is set up like an actual course_modules record
            // required for get_context_instance (in lib/accesslib.php)
            if (isset($cm->id)) {
                $cm->instance = $cm->id;
                $cm->id = $cm->cm;
            } else {
                $cm->instance = $coursemodules[$cm->cm]->instance;
                $cm->id = $cm->cm;
            }
            $cm->course = $course->id;
            if (coursemodule_visible_for_user($cm)) {
                return $cm;
            } else if ($cm->cm==$id) {
                // required cm is not visible to this user
                return false;
            }
        } else if ($cmid==$thiscm->id) {
            $found = true;
        }
    }

    // next cm not found
    return false;
}

function quizport_format_time($time, $str=null, $notime='&nbsp;') {
    if ($time>0) {
        return format_time($time, $str);
    } else {
        return $notime;
    }
}

function quizport_format_status($status=null, $return_brackets=false) {
    static $str = null;
    if (is_null($str)) {
        $str = array(
            QUIZPORT_STATUS_INPROGRESS => get_string('inprogress', 'quizport'),
            QUIZPORT_STATUS_TIMEDOUT => get_string('timedout', 'quizport'),
            QUIZPORT_STATUS_ABANDONED => get_string('abandoned', 'quizport'),
            QUIZPORT_STATUS_COMPLETED => get_string('completed', 'quizport')
        );
    }
    if (is_null($status)) {
        return $str;
    }
    if (array_key_exists($status, $str)) {
        $status = $str[$status];
    }
    if ($return_brackets) {
        $status = '('.moodle_strtolower($status).')';
    }
    return $status;
}

function quizport_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

function quizport_quizport_delete_userdata($data, $showfeedback=true) {
    $status = quizport_reset_userdata($data);
    if ($showfeedback) {
        foreach ($status as $item) {
            $str = $item['component'].': '.$item['item'].': ';
            if ($item['error']) {
                $str .= $item['error'];
                $class = 'notifyproblem';
            } else {
                $str .= get_string('ok');
                $class = 'notifysuccess';
            }
            notify($str, $class);
        }
    }
}

function quizport_reset_course_form($course) {
    print_checkbox('reset_quizport_deleteallattempts', 1, true, get_string('deleteallattempts', 'quizport'), '', '');  echo '<br />';
}

function quizport_reset_gradebook($courseid, $type='') {
    global $DB;
    $sql = ''
        .'SELECT q.*, cm.idnumber AS cmidnumber, cm.course AS courseid '
        .'FROM {quizport} q, {course_modules} cm, {modules} m '
        ."WHERE m.name='quizport' AND m.id=cm.module AND cm.instance=q.id AND q.course=?"
    ;
    if ($quizports = $DB->get_records_sql($sql, array($courseid))) {
        foreach ($quizports as $quizport) {
            quizport_grade_item_update($quizport, 'reset');
        }
    }
}

function quizport_reset_userdata($data) {
    global $DB;

    if (empty($data->reset_quizport_deleteallattempts)) {
        return array();
    }

    $quizportids = 'SELECT id FROM {quizport} WHERE course='.$data->courseid;
    $parentids = 'parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY.' AND parentid IN ('.$quizportids.')';

    // since there may be a large number of records in the quizport_quiz_attempts table,
    // we proceed unit by unit to try and limit effect of timeouts and memory overloads

    // $state determines what data is deleted
    //   0 : delete quizport_details
    //   1 : delete quizport_responses
    //   2 : delete quizport_quiz_attempts // not deleted !!
    //   3 : delete quizport_quiz_scores   // not deleted !!
    //   4 : delete quizport_unit_attempts
    //   5 : delete quizport_unit_grades

    if ($units = $DB->get_records_select('quizport_units', $parentids, null, 'id', 'id, parenttype, parentid')) {
        for ($state=0; $state<=5; $state++) {
            foreach ($units as $unit) {
                if ($state<=3) {
                    if ($quizzes = $DB->get_records('quizport_quizzes', array('unitid' => $unit->id), 'id', 'id, id')) {
                        foreach ($quizzes as $quiz) {
                            if ($state<=1) {
                                if ($attempts = $DB->get_records('quizport_quiz_attempts', array('quizid' => $quiz->id), 'id', 'id, id')) {
                                    $attemptids = implode(',', array_keys($attempts));
                                    if ($state==0) {
                                        $DB->delete_records_select('quizport_details', "attemptid IN ($attemptids)");
                                    }
                                    if ($state==1) {
                                        $DB->delete_records_select('quizport_responses', "attemptid IN ($attemptids)");
                                    }
                                }
                            }
                            if ($state==2) {
                                $DB->delete_records('quizport_quiz_attempts', array('quizid' => $quiz->id));
                            }
                            if ($state==3) {
                                $DB->delete_records('quizport_quiz_scores', array('quizid' => $quiz->id));
                            }
                        }
                    }
                }
                if ($state==4) {
                    $DB->delete_records('quizport_unit_attempts', array('unitid' => $unit->id));
                }
                if ($state==5) {
                    $DB->delete_records('quizport_unit_grades', array('parenttype' => $unit->parenttype, 'parentid' => $unit->parentid));
                }
            }
        }
    }


    return array(array(
        'component' => get_string('modulenameplural', 'quizport'),
        'item' => get_string('deleteallattempts', 'quizport'),
        'error' => false
    ));
}

function quizport_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'quizportheader', get_string('modulenameplural', 'quizport'));
    $mform->addElement('checkbox', 'reset_quizport_deleteallattempts', get_string('deleteallattempts', 'quizport'));
}

function quizport_reset_course_form_defaults($course) {
    return array('reset_quizport_deleteallattempts' => 1);
}

/*
* This standard function will check all instances of this module
* and make sure there are up-to-date events created for each of them.
* If courseid = 0, then every quizport event in the site is checked, else
* only quizport events belonging to the course specified are checked.
* This function is used, in its new format, by restore_refresh_events()
* in backup/backuplib.php
*
* @param int $courseid : relative path (below $CFG->dirroot) of folder holding class definitions
*/
function quizport_refresh_events($courseid=0) {
    global $CFG, $DB;

    if ($CFG->majorrelease>=2.0) {
        require_once($CFG->dirroot.'/calendar/lib.php');
    }

    if ($courseid) {
        $params = array('course'=>$courseid);
    } else {
        $params = array();
    }
    if (! $quizports = $DB->get_records('quizport', $params)) {
        return true; // no quizports
    }
    $quizportids = implode(',', array_keys($quizports));

    $select = 'parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY." AND parentid IN ($quizportids)";
    if (! $units = $DB->get_records_select('quizport_units', $select)) {
        return true; // no units - shouldn't happen !!
    }

    // get previous ids for events for these quizports
    if ($eventids = $DB->get_records_select('event', "modulename='quizport' AND instance IN ($quizportids)", null, 'id', 'id, id AS eventid')) {
        $eventids = array_keys($eventids);
    } else {
        $eventids = array();
    }

    // add events for these quizport units
    // eventids will be reused where possible
    foreach ($units as $unit) {
        quizport_update_events($quizports[$unit->parentid], $unit, $eventids, false);
    }

    // delete surplus events
    while (count($eventids)) {
        $id = array_shift($eventids);
        if ($CFG->majorrelease<=1.9) {
            delete_event($id);
        } else {
            $event = calendar_event::load($id);
            $event->delete();
        }
    }

    // all done
    return true;
}

// called from admin/module.php (Moodle 1.5 - 1.8)
function quizport_process_options(&$config) {
    global $CFG;
    if ($CFG->majorrelease<=1.5) {
        unset($config->sesskey);
        unset($config->module);
    }
    if (isset($config->enablecron) && is_array($config->enablecron)) {
        $config->enablecron = implode(',', $config->enablecron);
    }
}

/**
 * Tells if files in moddata are trusted and can be served without XSS protection.
 * @return bool true if file can be submitted by teacher only (trusted), false otherwise
 */
function quizport_is_moddata_trusted() {
    return true;
}
/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return bool True if quiz supports feature
 */
function quizport_supports($feature) {
    switch($feature) {
        case FEATURE_GRADE_HAS_GRADE;
        case FEATURE_GROUPINGS:
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        //case FEATURE_GROUPS:
        //case FEATURE_GRADE_OUTCOMES:
        //case FEATURE_COMPLETION_TRACKS_VIEWS:
        //case FEATURE_COMPLETION_HAS_RULES:
        //case FEATURE_IDNUMBER:
        //case FEATURE_MOD_INTRO: // entrytext?
        //case FEATURE_MODEDIT_DEFAULT_COMPLETION:
        default:
            return false;
    }
}
function quizport_set_missing_fields($table, &$record, &$form) {
    global $DB;

    // get info about table columns
    static $columns = array();
    if (empty($columns[$table])) {
        $columns[$table] = $DB->get_columns($table);
    }

    // set all empty fields (except "id")
    foreach ($columns[$table] as $column) {
        $name = $column->name;
        if ($name=='id' || isset($record->$name)) {
            // do nothing
        } else if (isset($form->$name)) {
            $record->$name = $form->$name;
        } else if (isset($column->default_value)) {
            $record->$name = $column->default_value;
        } else {
            if (isset($column->meta_type)) {
                $is_num = preg_match('/[INTD]/', $column->meta_type); // Moodle >= 2.0
            } else {
                $is_num = preg_match('/int|decimal|double|float|time|year/i', $column->type);
            }
            if ($is_num) {
                $record->$name = 0;
            } else {
                $record->$name = '';
            }
        }
    }
}
?>
