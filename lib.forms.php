<?php // $Id$
/**
 * Library of functions for the quizport module forms
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

// get the moodleform class
if ($CFG->majorrelease<=1.6) {
    // Moodle 1.6 and earlier
    if (strpos(ini_get('include_path'), $CFG->legacylibdir.'/pear' )===false) {
        ini_set('include_path', $CFG->legacylibdir.'/pear' . PATH_SEPARATOR . ini_get('include_path'));
    }
    require_once($CFG->legacylibdir.'/formslib.php');
} else {
    // Moodle 1.7 and later
    require_once($CFG->libdir.'/formslib.php');
}

require_once($CFG->dirroot.'/mod/quizport/lib.local.php');

function quizport_add_name_group(&$mform, $type, $visible, $fieldname='', $label='') {
    global $CFG;

    if ($fieldname=='') {
        $fieldname = 'name';
    }
    if ($label=='') {
        $label = get_string($fieldname);
    }

    if ($visible) {
        // namesource is visible, so create a group of name_elements
        $elements = array();
        $options = array(
            QUIZPORT_TEXTSOURCE_FILE => get_string('textsourcefile', 'quizport'),
            QUIZPORT_TEXTSOURCE_FILENAME => get_string('textsourcefilename', 'quizport'),
            QUIZPORT_TEXTSOURCE_FILEPATH => get_string('textsourcefilepath', 'quizport'),
            QUIZPORT_TEXTSOURCE_SPECIFIC => get_string('textsourcespecific', 'quizport')
        );
        $elements[] = $mform->createElement('select', $fieldname.'source', '', $options);
        $elements[] = $mform->createElement('text', $fieldname, '', array('size' => '40'));
        $mform->addGroup($elements, $fieldname.'_elements', $label, array(' '), false);
        $mform->disabledIf($fieldname.'_elements', $fieldname.'source', 'ne', QUIZPORT_TEXTSOURCE_SPECIFIC);
        $mform->setDefault($fieldname.'source', get_user_preferences('quizport_'.$type.'_'.$fieldname.'source', QUIZPORT_TEXTSOURCE_FILE));
        // $mform->setAdvanced($fieldname.'_elements');
        $mform->setHelpButton($fieldname.'_elements', array($fieldname.'add', $label, 'quizport'));
    } else {
        // name source is hidden
        $mform->addElement('hidden', $fieldname.'source', 0);
        if ($fieldname=='name') {
            $mform->addElement('text', $fieldname, $label, array('size' => '40'));
            $mform->setHelpButton($fieldname, array('nameedit', $label, 'quizport'));
        } else {
            $mform->addElement('hidden', $fieldname, '');
        }
    }
    $mform->setType($fieldname.'source', PARAM_INT);
    if (empty($CFG->formatstringstriptags)) {
        $mform->setType($fieldname, PARAM_CLEAN);
    } else {
        $mform->setType($fieldname, PARAM_TEXT);
    }
}
function quizport_add_file_group(&$mform, $type, $fieldname, $visible=true) {
    global $CFG, $COURSE, $course;
    static $printed_theform = false;

    switch (true) {
        case isset($COURSE->id): $id = $COURSE->id; break; // Moodle >=1.7
        case isset($course->id): $id = $course->id; break; // Moodle <=1.6
        default: $id =0;
    }

    $sitecontext = get_context_instance(CONTEXT_COURSE, SITEID); // CONTEXT_SYSTEM ?
    $coursecontext = get_context_instance(CONTEXT_COURSE, $id);

    if ($visible) {
        // fields are visible
        if (has_capability('moodle/course:managefiles', $sitecontext)) {
            // administrator
            if ($id==SITEID) {
                // we're adding to the site page
                $courseid = SITEID;
                $contextid = $sitecontext->id;
                $location = QUIZPORT_LOCATION_SITEFILES;
            } else {
                // we're adding to a course page
                $courseid = "'+(getObjValue(this.form.".$fieldname."location)==".QUIZPORT_LOCATION_SITEFILES."?".SITEID.":".$id.")+'";
                $contextid = "'+(getObjValue(this.form.".$fieldname."location)==".QUIZPORT_LOCATION_SITEFILES."?".$sitecontext->id.":".$coursecontext->id.")+'";
                $location = '';
            }
        } else {
            // ordinary teacher or content creator has no choice of location
            $courseid = $id;
            $contextid = $coursecontext->id;
            $location = QUIZPORT_LOCATION_COURSEFILES;
        }

        if ($CFG->majorrelease<=1.1) {
            $files = get_directory_list($CFG->dataroot.'/'.$id);
            $options = array();
            if ($fieldname=='config') {
                $options[] = '';
            }
            foreach ($files as $file) {
                $options["$file"] = $file;
            }
            $mform->addElement('select', $fieldname.'file', get_string($fieldname.'file', 'quizport'), $options);
            $mform->addElement('hidden', $fieldname.'location', QUIZPORT_LOCATION_COURSEFILES);
        } else {
            // create buttons
            $choosefile_button = $mform->createElement('button', 'popup', get_string('chooseafile', 'resource') .' ...');
            if (empty($CFG->resource_websearch)) {
                $websearch_button = false;
            } else {
                $websearch_button = $mform->createElement('button', 'searchbutton', get_string('searchweb', 'resource').'...');
            }

            // create a group of form elements, comprising text box + buttons
            $elements = array();
            if ($location=='') {
                // allow admin to select from "site" or "course" files
                $options = array(
                    QUIZPORT_LOCATION_COURSEFILES => quizport_format_location(QUIZPORT_LOCATION_COURSEFILES),
                    QUIZPORT_LOCATION_SITEFILES => quizport_format_location(QUIZPORT_LOCATION_SITEFILES),
                );
                $elements[] = $mform->createElement('select', $fieldname.'location', '', $options);
                $defaultlocation = get_user_preferences('quizport_'.$type.'_'.$fieldname.'location', QUIZPORT_LOCATION_COURSEFILES);
            } else {
                // the user has no choice about the location
                $mform->addElement('hidden', $fieldname.'location', $location);
                $defaultlocation = $location;
            }

            $elements[] = $mform->createElement('text', $fieldname.'file', '', array('size'=>'40'));
            if ($choosefile_button || $websearch_button) {
                $elements[] = $mform->createElement('static', '', '', '<br />');
                if ($choosefile_button) {
                    $elements[] = &$choosefile_button;
                }
                if ($websearch_button) {
                    $elements[] = &$websearch_button;
                }
            }
            $mform->addGroup($elements, $fieldname.'_elements', get_string($fieldname.'file', 'quizport'), ' ', false);
            $mform->setHelpButton($fieldname.'_elements', array($fieldname.'file', get_string($fieldname.'file', 'quizport'), 'quizport'));

            // set attributes on the buttons
            if ($choosefile_button) {
				if ($CFG->version < 2004083125) {
					// up to and including Moodle 1.4.1 (version may need refining)
					$url = '/mod/resource/coursefiles.php';
                    $print_theform = true;
                } else {
                    $url = '/files/index.php';
                    $print_theform = false;
                }
                $dir = "'+getDir(this.form.".$fieldname."file.value)+'";
                if ($CFG->majorrelease>=2.0) {
                    $url .= '?contextid='.$contextid.'&filearea=course_content&itemid=0&filepath='.$dir.'/';
                    //$url .= '?contextid=2&filearea=course_content&itemid=0&filepath=/newsitefolder/&filename=';
                } else {
                    if ($CFG->majorrelease>=1.8) {
                        $choose = 'id_'.$fieldname.'file';
                    } else {
                        $choose = $mform->getAttribute('id').'.'.$fieldname.'file';
                    }
                    $url .= '?id='.$courseid.'&wdir='.$dir.'&choose='.$choose;
                }
                $options = 'menubar=0,location=0,scrollbars,resizable,width=750,height=500';
                $onclick = "openpopup('$url', '".$choosefile_button->getName()."', '$options', 0);";
                if ($print_theform) {
                    if (! $printed_theform) {
                        // Moodle <= 1.4.1: print dummy form to receive source/config file name
                        print '<form name="theform" style="display:none;"><fieldset><input type="hidden" name="reference"></fieldset></form>'."\n";
                        $printed_theform = true;
                    }
                    $onclick .= "window.theform_interval=setInterval('if (document.forms.theform.reference.value) {document.forms.mform1.".$fieldname."file.value=document.theform.reference.value;clearInterval(theform_interval);}',500);";
                }
                $attributes = array(
                    'title'=>get_string('chooseafile', 'resource'),
                    'onclick'=>$onclick.'return false;'
                );
                $choosefile_button->updateAttributes($attributes);
            }
            if ($websearch_button) {
                $url = $CFG->resource_websearch;
                $options = 'menubar=1,location=1,directories=1,toolbar=1,scrollbars,resizable,width=800,height=600';
                $attributes = array(
                    'title' => get_string('searchweb', 'resource'),
                    'onclick' => "return window.open('$url', '".$websearch_button->getName()."', '$options', 0);"
                );
                $websearch_button->updateAttributes($attributes);
            }
            $mform->setType($fieldname.'location', PARAM_INT);
            $mform->setDefault($fieldname.'location', $defaultlocation);
        }

        $default = get_user_preferences('quizport_'.$type.'_'.$fieldname.'file', '');
        if ($fieldname=='source') {
            if ($default=='' || substr($default, 0, 4)=='http' || substr($default, -1)=='/') {
                // do nothing
            } else {
                // a file or folder on the local file system
                if ($defaultlocation==QUIZPORT_LOCATION_SITEFILES) {
                    $courseid = SITEID;
                } else {
                    $courseid = $id;
                }
                $default = dirname($default);
                if ($default=='' || $default=='.' || ! is_dir($CFG->dataroot.'/'.$courseid.'/'.$default)) {
                    $default = '';
                } else {
                    $default .= '/';
                }
            }
        }
        $mform->setType($fieldname.'file', PARAM_TEXT);
        $mform->setDefault($fieldname.'file', $default);

    } else {

        // fields are hidden
        $mform->addElement('hidden', $fieldname.'file', '');
        $mform->addElement('hidden', $fieldname.'location', 0);

    } // end if $add
}
function quizport_validate_file_group(&$data, &$errors, $type, $fieldname) {
    global $CFG, $COURSE, $course;

    switch (true) {
        case isset($COURSE->id): $courseid = $COURSE->id; break; // Moodle >=1.7
        case isset($course->id): $courseid = $course->id; break; // Moodle <=1.6
        default: $courseid =0;
    }

// location
    if (preg_match('|^https?://|', $data[$fieldname.'file'])) {
        $location = QUIZPORT_LOCATION_WWW;
    } else {
        $location = QUIZPORT_LOCATION_COURSEFILES;
        if (isset($data[$fieldname.'location'])) {
            if ($data[$fieldname.'location']==QUIZPORT_LOCATION_SITEFILES) {
                // user wants to access site files, so do a capability check
                $sitecontext = get_context_instance(CONTEXT_SYSTEM, SITEID);
                if (has_capability('moodle/course:managefiles', $sitecontext)) {
                    // user is allowed to access sites files
                    $location = QUIZPORT_LOCATION_SITEFILES;
                    $courseid = SITEID;
                }
            }
        }
    }
    $data[$fieldname.'location'] = $location;

// file
    if (isset($data[$fieldname.'file'])) {
        $data[$fieldname.'file'] = trim($data[$fieldname.'file']);
    }
    if (empty($data[$fieldname.'file'])) {
        if ($type=='quiz' && $fieldname=='source') {
            // quiz sourcefile cannot be empty
            $errors[$fieldname.'_elements'] = get_string('error_nofilename', 'quizport');
        }
    } else {
        if ($location==QUIZPORT_LOCATION_WWW) {
           require_once($CFG->libdir.'/filelib.php');
           if (download_file_content($data[$fieldname.'file'])) {
                // do nothing
            } else {
                $errors[$fieldname.'_elements'] = get_string('urlnotaccessible', 'quizport');
            }
        } else {
            // Moodle data file/folder
            $path = $CFG->dataroot.'/'.$courseid.'/'.$data[$fieldname.'file'];
            if (! file_exists($path)) {
                $errors[$fieldname.'_elements'] = get_string('error_pathdoesnotexist', 'quizport', $path);
            }
        }
    }
}
function quizport_add_quizchain(&$mform, $type, $is_add) {
    if ($is_add) {
        $mform->addElement('selectyesno', 'quizchain', get_string('addquizchain', 'quizport'));
        $mform->setDefault('quizchain', get_user_preferences('quizport_'.$type.'_quizchain', QUIZPORT_TEXTSOURCE_FILE));
        $mform->setHelpButton('quizchain', array('addquizchain', get_string('addquizchain', 'quizport'), 'quizport'));
        // $mform->setAdvanced('quizchain');
    } else {
        $mform->addElement('hidden', 'quizchain', QUIZPORT_NO);
    }
}
function quizport_add_page_text(&$mform, $type, $is_add, $default) {
    global $CFG;

    if ($is_add) {
        // add new QuizPort
        $elements = array();
        $elements[] = $mform->createElement('selectyesno', $type.'page');
        $options = array(
            QUIZPORT_TEXTSOURCE_FILE => get_string('textsourcefile', 'quizport'),
            QUIZPORT_TEXTSOURCE_SPECIFIC => get_string('textsourcespecific', 'quizport')
        );
        $elements[] = $mform->createElement('select', $type.'textsource', '', $options);
        $mform->addGroup($elements, $type.'page_elements', get_string($type.'page', 'quizport'), array(' '), false);
        $mform->setHelpButton($type.'page_elements', array($type.'page', get_string($type.'page', 'quizport'), 'quizport'));
        $mform->disabledIf($type.'page_elements', $type.'page', 'ne', QUIZPORT_YES);
        $mform->setDefault($type.'textsource', get_user_preferences('quizport_unit_'.$type.'textsource', QUIZPORT_TEXTSOURCE_FILE));
    } else {
        // update existing QuizPort
        $mform->addElement('selectyesno', $type.'page', get_string($type.'page', 'quizport'));
        $mform->setHelpButton($type.'page', array($type.'page', get_string($type.'page', 'quizport'), 'quizport'));
        $mform->addElement('hidden', $type.'textsource', QUIZPORT_TEXTSOURCE_SPECIFIC);
    }
    $mform->setType($type.'textsource', PARAM_INT);
    $mform->setType($type.'page', PARAM_INT);
    $mform->setDefault($type.'page', get_user_preferences('quizport_unit_'.$type.'page', $default));

    $mform->addElement('htmleditor', $type.'text', get_string($type.'text', 'quizport'));
    $mform->setType($type.'text', PARAM_RAW);
    if ($CFG->majorrelease<=1.9) {
        // Moodle <= 1.9
        $mform->setHelpButton($type.'text', array('writing', 'questions', 'richtext'), false, 'editorhelpbutton');
    } else {
        // Moodle >= 2.0
        $mform->setHelpButton($type.'text', array('richtext2', get_string('helprichtext')));
    }
    $mform->disabledIf($type.'text', $type.'page', 'ne', QUIZPORT_YES);
    $mform->disabledIf($type.'text', $type.'textsource', 'ne', QUIZPORT_TEXTSOURCE_SPECIFIC);
    $mform->setAdvanced($type.'text');
}
function quizport_add_activity_list(&$mform, $type) {
    global $CFG, $COURSE, $THEME, $course, $form;

    if (empty($COURSE) && isset($course)) {
        $COURSE = &$course;
    }

    $optgroups = array(
        get_string('none') => array(
            QUIZPORT_ACTIVITY_NONE => get_string('none')
        ),
        get_string($type=='entry' ? 'previous' : 'next') => array(
            QUIZPORT_ACTIVITY_COURSE_ANY => get_string($type.'cmcourse', 'quizport'),
            QUIZPORT_ACTIVITY_SECTION_ANY => get_string($type.'cmsection', 'quizport'),
            QUIZPORT_ACTIVITY_COURSE_QUIZPORT => get_string($type.'quizportcourse', 'quizport'),
            QUIZPORT_ACTIVITY_SECTION_QUIZPORT => get_string($type.'quizportsection', 'quizport')
        )
    );
    if ($modinfo = unserialize($COURSE->modinfo)) {
        switch ($COURSE->format) {
            case 'weeks': $strsection = get_string('strftimedateshort'); break;
            case 'topics': $strsection = get_string('topic'); break;
            default: $strsection = get_string('section');
        }
        $section = -1;
        foreach ($modinfo as $cmid=>$mod) {
            if ($mod->mod=='label') {
                continue; // ignore labels
            }
            if ($type=='entry' && $mod->mod=='resource') {
                continue; // ignore resources as entry activities
            }
            if (isset($form->update) && $form->update==$cmid) {
                continue; // ignore this quizport
            }
            if ($section==$mod->section) {
                // do nothing (same section)
            } else {
                // start new optgroup for this course section
                $section = $mod->section;
                if ($section==0) {
                    $optgroup = get_string('activities');
                } else if ($COURSE->format=='weeks') {
                    $date = $COURSE->startdate + 7200 + ($section * 604800);
                    $optgroup = ''
                        .userdate($date, $strsection).' - '.userdate($date + 518400, $strsection)
                    ;
                } else {
                    $optgroup = $strsection.': '.$section;
                }
                if (empty($options[$optgroup])) {
                    $options[$optgroup] = array();
                }
            }
            $optgroups[$optgroup][$cmid] = format_string(urldecode($mod->name));
        }
    }
    $elements = array();
    $options = array();
    for ($i=100; $i>=0; $i--) {
        $options[$i] = $i.'%';
    }
    $list = &$mform->createElement('selectgroups', $type.'cm', '', $optgroups);
    $elements[] = &$list;
    $elements[] = &$mform->createElement('select', $type.'grade', '', $options);
    $mform->addGroup($elements, $type.'cm_elements', get_string($type.'cm', 'quizport'), array(' '), false);
    $mform->setHelpButton($type.'cm_elements', array($type.'cm', get_string($type.'cm', 'quizport'), 'quizport'));
    if ($type=='entry') {
        $defaultcm = QUIZPORT_ACTIVITY_NONE;
    } else { // exit
        $defaultcm = QUIZPORT_ACTIVITY_SECTION_QUIZPORT;
    }
    $mform->setDefault($type.'cm', get_user_preferences('quizport_unit_'.$type.'cm', $defaultcm));
    $mform->setDefault($type.'grade', get_user_preferences('quizport_unit_'.$type.'grade', 100));
    $mform->disabledIf($type.'cm_elements', $type.'cm', 'eq', 0);
    if ($type=='entry') {
        $mform->setAdvanced($type.'cm_elements');
    }

    // add module icons, if required
    if ($modinfo && empty($THEME->navmenuiconshide) && isset($CFG->modpixpath)) {
        for ($i=0; $i<count($list->_optGroups); $i++) {
            $optgroup = &$list->_optGroups[$i];
            for ($ii=0; $ii<count($optgroup['options']); $ii++) {
                $option = &$optgroup['options'][$ii];
                if (isset($option['attr']['value']) && $option['attr']['value']>0) {
                    $cmid = $option['attr']['value'];
                    $url = $CFG->modpixpath.'/'.$modinfo[$cmid]->mod.'/icon.gif';
                    $option['attr']['style'] = "background-image: url($url); background-repeat: no-repeat; background-position: 1px 2px; min-height: 20px;";
                }
            }
        }
    }
}
function quizport_set_timer_defaults(&$defaults, $type, $fieldname, $minmax=false, $default=0) {
    // set the default values for a time field (e.g. timelimit, delay1, delay2, delay3)
    // $type : "unit" or "quiz"

    static $formats = array(
        'hours'=>'H', 'minutes'=>'i', 'seconds'=>'s'
    );

    if (! isset($defaults[$fieldname])) {
        // adding a new record - get time from user preferences
        $defaults[$fieldname] = get_user_preferences('quizport_'.$type.'_'.$fieldname, $default);
    }

    if (empty($defaults[$fieldname])) {
        $defaults[$fieldname.'disable'] = 1;
    } else {
        $defaults[$fieldname.'disable'] = 0;
    }

    if ($minmax) {
        // $condition->attempttime
        if ($defaults[$fieldname] < 0) {
            $defaults[$fieldname.'minmax'] = QUIZPORT_MIN;
        } else {
            $defaults[$fieldname.'minmax'] = QUIZPORT_MAX;
        }
    } else {
        if ($defaults[$fieldname] < 0) {
            // $quiz->timelimit
            $defaults[$fieldname.'disable'] = $defaults[$fieldname];
        }
    }

    if ($defaults[$fieldname.'disable']==0) {
        $defaults[$fieldname] = abs($defaults[$fieldname]);
    } else {
        $defaults[$fieldname] = 0;
    }

    foreach ($formats as $time=>$format) {
        if ($defaults[$fieldname.'disable']) {
            $defaults[$fieldname.$time] = 0;
        } else {
            $defaults[$fieldname.$time] = gmdate($format, $defaults[$fieldname]);
        }
    }
}
function quizport_add_timer_selector(&$mform, $fieldname, $description='', $showhours=true, $showmins=true, $showsecs=true, $showdisable=true, $before=false, $after=false, $advanced=true) {
    static $hours = array();
    static $minutes = array();
    static $seconds = array();

    if (empty($hours)) {
        // initialize values for $hours, $minutes and $seconds
        for ($i=0; $i<60; $i++) {
            $str = sprintf('%02d', $i);
            if ($i<=24) {
                $hours[$i] = $str;
            }
            $minutes[$i] = $str;
            $seconds[$i] = $str;
        }
    }
    // defaults for these fields should have been set in "quizport_set_timer_defaults()" function

    $elements = array();
    if ($description) {
        $elements[] = $mform->createElement('static', '', '', $description.'<br />');
    }
    if ($before) {
        foreach ($before as $name => $options) {
            $elements[] = &$mform->createElement('select', $fieldname.$name, '', $options);
        }
    }
    if ($showhours) {
        $elements[] = &$mform->createElement('select', $fieldname.'hours', '', $hours);
    }
    if ($showmins) {
        $elements[] = &$mform->createElement('select', $fieldname.'minutes', '', $minutes);
    }
    if ($showsecs) {
        $elements[] = &$mform->createElement('select', $fieldname.'seconds', '', $seconds);
    }
    if ($after) {
        foreach ($after as $name => $options) {
            $elements[] = &$mform->createElement('select', $fieldname.$name, '', $options);
        }
    }
    if ($showdisable) {
        $elements[] = &$mform->createElement('checkbox', $fieldname.'disable', '', get_string('disable'));
    }
    $mform->addGroup($elements, $fieldname.'_elements', get_string($fieldname, 'quizport'), array(' '), false);
    if ($before) {
        foreach ($before as $name => $options) {
            $mform->setType($fieldname.$name, PARAM_INT);
        }
    }
    if ($showhours) {
        $mform->setType($fieldname.'hours', PARAM_INT);
    }
    if ($showmins) {
        $mform->setType($fieldname.'minutes', PARAM_INT);
    }
    if ($showsecs) {
        $mform->setType($fieldname.'seconds', PARAM_INT);
    }
    if ($after) {
        foreach ($after as $name => $options) {
            $mform->setType($fieldname.$name, PARAM_INT);
        }
    }
    if ($showdisable) {
        $mform->setType($fieldname.'disable', PARAM_INT);
        $mform->disabledIf($fieldname.'_elements', $fieldname.'disable', 'checked');
    }
    $mform->setHelpButton($fieldname.'_elements', array($fieldname, get_string($fieldname, 'quizport'), 'quizport'));
    if ($advanced) {
        $mform->setAdvanced($fieldname.'_elements');
    }
}
function get_time_value(&$form, $time) {
    $value = $form->$time;
    if (is_array($value)) {
        // Moodle 1.7 and earlier
        if (empty($value['off'])) {
            $value = make_timestamp(
                $value['year'], $value['month'], $value['day'], $value['hour'], $value['minute'], 0
            );
        } else {
            $value = 0;
        }
    }
    return $value;
}
function get_timer_value(&$form, $time) {
    static $fields = array(
        // HOURSECS and MINSECS are defined in "lib/moodlelib.php"
        'hours'=>HOURSECS, 'minutes'=>MINSECS, 'seconds'=>1
    );
    $totalsecs = 0;
    $timedisable = $time.'disable';
    if (empty($form->$timedisable)) {
        foreach ($fields as $field => $secs) {
            $timefield = $time.$field;
            if (isset($form->$timefield)) {
                $totalsecs += $form->$timefield * $secs;
            }
        }
    } else if ($form->$timedisable < 0) {
        // special values (e.g. timelimit = -1 : get from template)
        $totalsecs = $form->$timedisable;
    }
    return $totalsecs;
}
function quizport_add_attemptlimit_selector(&$mform, $type) {
    static $options = array();

    if (empty($options)) {
        $options = array(
            0 => get_string('attemptsunlimited', 'quiz'),
            1 => '1 '.moodle_strtolower(get_string('attempt', 'quiz'))
        );
        for ($i=2; $i<=10; $i++) {
            $options[$i] = "$i ".moodle_strtolower(get_string('attempts', 'quiz'));
        }
    }

    $mform->addElement('select', 'attemptlimit', get_string('attemptsallowed', 'quiz'), $options);
    $mform->setType('attemptlimit', PARAM_INT);
    $mform->setDefault('attemptlimit', get_user_preferences('quizport_'.$type.'_attemptlimit', 0));
    $mform->setHelpButton('attemptlimit', array('attempts', get_string('attemptsallowed', 'quiz'), 'quiz'));
    // $mform->setAdvanced('attemptlimit');
}
function quizport_add_allowresume($mform, $type) {
    //$mform->addElement('selectyesno', 'allowresume', get_string('allowresume', 'quizport'));
    $options = quizport_format_allowresume();
    $mform->addElement('select', 'allowresume', get_string('allowresume', 'quizport'), $options);
    $mform->setType('allowresume', PARAM_INT);
    $mform->setDefault('allowresume', get_user_preferences('quizport_'.$type.'_allowresume', QUIZPORT_YES));
    $mform->setHelpButton('allowresume', array('allowresume', get_string('allowresume', 'quizport'), 'quizport'));
    $mform->setAdvanced('allowresume');
}
function get_reviewoptions_timesitems() {
    return array(
        array('duringattempt','afterattempt','afterclose'), // times
        array('responses', 'answers', 'scores', 'feedback') // items
    );
}
function quizport_set_reviewoptions(&$quiz) {
    list($times, $items) = get_reviewoptions_timesitems();
    $quiz->reviewoptions = 0;
    foreach ($times as $time) {
        foreach ($items as $item) {
            $field = $item.$time;
            if (empty($quiz->$field)) {
                continue;
            }
            eval('$quiz->reviewoptions += ('.strtoupper("QUIZPORT_REVIEW_$item & QUIZPORT_REVIEW_$time").');');
        }
    }
}
function quizport_set_reviewoptions_defaults(&$defaults, $type) {
    if (empty($defaults['reviewoptions'])) {
        $default = 0;
    } else {
        $default = $defaults['reviewoptions'];
    }
    list($times, $items) = get_reviewoptions_timesitems();
    foreach ($times as $time) {
        foreach ($items as $item) {
            eval('$defaults[$item.$time] = min(1, $default & '.strtoupper("QUIZPORT_REVIEW_$item & QUIZPORT_REVIEW_$time").');');
        }
    }
}
function quizport_add_reviewoptions($mform, $type) {
    list($times, $items) = get_reviewoptions_timesitems();
    foreach ($times as $time) {
        $elements = array();
        foreach ($items as $item) {
            $elements[] = &$mform->createElement('checkbox', $item.$time, '', get_string($item, 'quiz'));
        }
        $mform->addGroup($elements, $time.'_elements', get_string('review'.$time, 'quizport'), null, false);
        if ($time=='afterclose') {
            $mform->disabledIf('afterclose_elements', 'timeclose[off]', 'checked');
        }
    }
}
function quizport_add_password_and_subnet(&$mform, $type) {
    $password = $type.'password'; // "unitpassword" or "quizpassword"

    // Password
    if (class_exists('MoodleQuickForm_passwordunmask')) {
        $password_element = 'passwordunmask'; // Moodle 1.9 and later
    } else {
        $password_element = 'password'; // Moodle 1.8 and earlier
    }
    $mform->addElement($password_element, $password, get_string('requirepassword', 'quiz'));
    $mform->setType($password, PARAM_TEXT);
    $mform->setDefault($password, '');
    $mform->setHelpButton($password, array('requirepassword', get_string('requirepassword', 'quiz'), 'quiz'));
    $mform->setAdvanced($password);
    // $mform->setAdvanced($password, $CFG->quiz_fix_password);

    // Subnet
    if (isset($CFG->quiz_subnet)) {
        // Moodle 1.9 and earlier
        $default_subnet = $CFG->quiz_subnet;
    } else {
        // Moodle 2.0 and later
        $default_subnet = '';
    }
    $mform->addElement('text', 'subnet', get_string('requiresubnet', 'quiz'));
    $mform->setType('subnet', PARAM_TEXT);
    $mform->setDefault('subnet', get_user_preferences('quizport_quiz_subnet', $default_subnet));
    $mform->setHelpButton('subnet', array('requiresubnet', get_string('requiresubnet', 'quiz'), 'quiz'));
    $mform->setAdvanced('subnet');
    // $mform->setAdvanced('subnet', $CFG->quiz_fix_subnet);
}
function quizport_add_grademethod_selector(&$mform, $type, $grademethod, $defaultmethod, $advanced) {
    $options = quizport_format_grademethod($type);
    $mform->addElement('select', $grademethod, get_string($grademethod, 'quizport'), $options);
    $mform->setType($grademethod, PARAM_INT);
    $mform->setDefault($grademethod, get_user_preferences('quizport_'.$type.'_'.$grademethod, $defaultmethod));
    $mform->setHelpButton($grademethod, array($grademethod, get_string($grademethod, 'quizport'), 'quizport'));
    if ($advanced) {
        $mform->setAdvanced($grademethod);
    }
}
function quizport_add_gradeignore(&$mform, $type, $grade) {
    $grademethod = $grade.'method';
    $gradeignore = $grade.'ignore';
    $mform->addElement('selectyesno', $gradeignore, get_string($gradeignore, 'quizport'));
    $mform->setType($gradeignore, PARAM_INT);
    $mform->setDefault($gradeignore, get_user_preferences('quizport_'.$type.'_'.$gradeignore, QUIZPORT_NO));
    $mform->setHelpButton($gradeignore, array($gradeignore, get_string($gradeignore, 'quizport'), 'quizport'));
    $mform->setAdvanced($gradeignore);
    $mform->disabledIf($gradeignore, $grademethod, 'eq', QUIZPORT_GRADEMETHOD_HIGHEST);
}
function quizport_add_grades_selector(&$mform, $type) {
    static $options = array();

    if ($type=='unit') {
        $grade = 'grade'; // QuizPort units
    } else {
        $grade = 'score'; // QuizPort quizzes
    }

    if (empty($options)) {
        for ($i=100; $i>=1; $i--) {
            $options[$i] = $i;
        }
    }

    $options[0] = get_string('no'.$grade, 'quizport');
    $mform->addElement('select', $grade.'limit', get_string('maximum'.$grade, 'quizport'), $options);
    $mform->setType($grade.'limit', PARAM_INT);
    $mform->setDefault($grade.'limit', get_user_preferences('quizport_'.$type.'_'.$grade.'limit', 100));
    $mform->setHelpButton($grade.'limit', array($grade.'limit', get_string($grade.'limit', 'quizport'), 'quizport'));
    $mform->setAdvanced($grade.'limit');
}
function quizport_add_weighting_selector(&$mform, $type) {
    static $options = array();

    if ($type=='unit') {
        $grade = 'grade'; // QuizPort units
    } else {
        $grade = 'score'; // QuizPort quizzes
    }

    if (empty($options)) {
        for ($i=100; $i>=1; $i--) {
            $options[$i] = $i;
        }
        $options[0] = get_string('weightingnone', 'quizport');
        if ($type=='quiz') {
            for ($i=1; $i<=10; $i++) {
                $options["-$i"] = get_string('weightingequal', 'quizport')." ($i)";
            }
        }
    }
    $mform->addElement('select', $grade.'weighting', get_string($grade.'weighting', 'quizport'), $options);
    $mform->setType($grade.'weighting', PARAM_INT);
    $mform->setDefault($grade.'weighting', get_user_preferences('quizport_'.$type.'_'.$grade.'weighting', 100));
    $mform->setHelpButton($grade.'weighting', array($grade.'weighting', get_string($grade.'weighting', 'quizport'), 'quizport'));
    $mform->setAdvanced($grade.'weighting');
}
function quizport_add_hidden_fields(&$mform, $params) {
    global $QUIZPORT;
    $params = $QUIZPORT->merge_params($params);
    foreach ($params as $name=>$value) {
        if ($value) {
            $mform->addElement('hidden', $name, $value);

            switch ($name) {
                case 'id':
                case 'course':
                case 'unitid':
                case 'unumber':
                case 'quizid':
                case 'qnumber':
                case 'conditionid':
                case 'conditiontype':
                    $mform->setType($name, PARAM_INT);
                    break;

                case 'tab':
                case 'mode':
                case 'columnlistid':
                case 'columnlisttype':
                default:
                    $mform->setType($name, PARAM_ALPHANUM);
                    break;
            }
        }
    }
}

function quizport_pageoptions_defaults(&$defaults) {
    $page_options = get_page_options();
    foreach ($page_options as $type=>$names) {
        if (! isset($defaults[$type.'options'])) {
            $defaults[$type.'options'] = get_user_preferences('quizport_unit_'.$type.'options', 0);
        }
        foreach ($names as $name=>$mask) {
            $defaults[$type.'_'.$name] = $defaults[$type.'options'] & $mask;
        }
    }
}

function quizport_popupoptions_defaults(&$defaults) {
    if (isset($defaults['showpopup'])) {
        $defaults['showpopup'] = $defaults['showpopup'];
    } else {
        $defaults['showpopup'] = get_user_preferences('quizport_unit_showpopup', 0);
    }
    if ($defaults['showpopup'] && isset($defaults['popupoptions'])) {
        $window_options = get_window_options();
        $popup_options = explode(',', strtolower($defaults['popupoptions']));
        foreach ($popup_options as $option) {
            if (preg_match('/^([a-z]+)(?:=(.*))?$/', strtolower($option), $matches)) {
                if (in_array($matches[1], $window_options)) {
                    if ($matches[1]=='width' || $matches[1]=='height') {
                        if (empty($matches[2])) {
                            $defaults[$matches[1]] = '';
                        } else {
                            $defaults[$matches[1]] = intval($matches[2]);
                        }
                    } else {
                        $defaults[$matches[1]] = 1; // enable check box
                    }
                }
            }
        }
    }
}

function get_window_options() {
    return array(
        'resizable', 'scrollbars', 'directories', 'location',
        'menubar', 'toolbar', 'status', 'width', 'height',
        'moodleheader','moodlenavbar','moodlefooter','moodlebutton'
    );
}

function get_page_options() {
    return array(
        'entry' => array(
            'title' => QUIZPORT_ENTRYOPTIONS_TITLE,
            'grading' => QUIZPORT_ENTRYOPTIONS_GRADING,
            'dates' => QUIZPORT_ENTRYOPTIONS_DATES,
            'attempts' => QUIZPORT_ENTRYOPTIONS_ATTEMPTS
        ),
        'exit' => array(
            'title' => QUIZPORT_ENTRYOPTIONS_TITLE,
            'encouragement' => QUIZPORT_EXITOPTIONS_ENCOURAGEMENT,
            'unitattempt' => QUIZPORT_EXITOPTIONS_UNITATTEMPT,
            'unitgrade' => QUIZPORT_EXITOPTIONS_UNITGRADE,
            'retry' => QUIZPORT_EXITOPTIONS_RETRY,
            'index' => QUIZPORT_EXITOPTIONS_INDEX,
            'course' => QUIZPORT_EXITOPTIONS_COURSE,
            'grades' => QUIZPORT_EXITOPTIONS_GRADES
        )
    );
}
?>