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

// get library of QuizPort functions to build common form elements
require_once($CFG->dirroot.'/mod/quizport/lib.forms.php');

// get parent form object (=moodleform_mod)
if ($CFG->majorrelease>=1.8) {
    require_once($CFG->dirroot.'/course/moodleform_mod.php');
} else {
    require_once($CFG->dirroot.'/mod/quizport/legacy/moodleform_mod.php');
}

if (! class_exists('MoodleQuickForm_selectgroups')) {
// Moodle 1.7
    MoodleQuickForm::registerElementType('selectgroups', "$CFG->dirroot/mod/quizport/legacy/lib/form/selectgroups.php", 'MoodleQuickForm_selectgroups');
}
if (! class_exists('MoodleQuickForm_passwordunmask')) {
// Moodle <= 1.8
    MoodleQuickForm::registerElementType('passwordunmask', "$CFG->dirroot/mod/quizport/legacy/lib/form/passwordunmask.php", 'MoodleQuickForm_passwordunmask');
}

if ($CFG->majorrelease >= 2.0) {
    class mod_quizport_mod_form extends moodleform_mod {
        function definition() {
            $name = 'cannotaddonmoodle2';
            $this->_form->addElement('static', $name, '', get_string($name, 'quizport'));

            // fix error on line 221 of "course/moodleform_mod.php"
            // nonexistent html element 'completionunlocked'
            $name = 'completionunlocked';
            $this->_form->addElement('hidden', $name, 0);
            $this->_form->setType($name, PARAM_INT);

            $this->standard_hidden_coursemodule_elements();
            $this->_form->addElement('cancel');
        }
    }
} else {
    class mod_quizport_mod_form extends moodleform_mod {
        // documentation on formslib.php here:
        // http://docs.moodle.org/en/Development:lib/formslib.php_Form_Definition

        function definition() {
            global $CFG, $COURSE, $course, $DB, $PAGE;

            if (empty($COURSE) && isset($course)) {
                $COURSE = &$course;
            }

            // Moodle >= 2.0: Advanced buttons require lib/yui/yahoo/*.js
            if (method_exists($PAGE, 'get_requires')) {
                $requires = $PAGE->get_requires();
                $yui_lib = $requires->yui_lib('yahoo');
                $yui_lib->asap();
                // $PAGE->get_requires()->yui_lib('yahoo')->asap();
            }

            global $form;
            if (isset($form->add) && empty($form->update)) {
                $is_add = true;
            } else {
                $is_add = false;
            }

            $mform = &$this->_form;

            //-----------------------------------------------------------------------------------
            $mform->addElement('header', 'general', get_string('general', 'form'));
            //-----------------------------------------------------------------------------------

            // Name
            quizport_add_name_group($mform, 'unit', $is_add, '', get_string('unitname', 'quizport'));

            // Source file
            quizport_add_file_group($mform, 'unit', 'source', $is_add);

            // Configuration file
            quizport_add_file_group($mform, 'unit', 'config', $is_add);
            $mform->setAdvanced('config_elements');

            // Add chain
            quizport_add_quizchain($mform, 'unit', $is_add);
            quizport_add_name_group($mform, 'unit', $is_add, 'quizname', get_string('quiznames', 'quizport'));

            //-----------------------------------------------------------------------------------
            $mform->addElement('header', 'displayhdr', get_string('display', 'form'));
            //-----------------------------------------------------------------------------------

            // Same window or new window (=popup) ?
            $options = array(
                0 => get_string('pagewindow', 'resource'),
                1 => get_string('newwindow', 'resource')
            );
            $mform->addElement('select', 'showpopup', get_string('display', 'resource'), $options);
            $mform->setType('showpopup', PARAM_INT);
            $mform->setDefault('showpopup', get_user_preferences('quizport_unit_showpopup', !empty($CFG->quizport_showpopup)));
            $mform->setHelpButton('showpopup', array('showpopup', get_string('display', 'resource'), 'quizport'));

            // New window options
            $window_options = get_window_options();

            $elements = array();
            foreach ($window_options as $option) {
                if (substr($option, 0, 6)=='moodle') {
                    $elements[] = $mform->createElement('checkbox', $option, '', get_string('new'.$option, 'quizport'));
                }
            }
            $name = 'popupoptions_moodle';
            $mform->addGroup($elements, $name, '', '<br />', false);
            $mform->disabledIf($name, 'showpopup', 'eq', 0);
            $mform->setAdvanced($name);

            $elements = array();
            foreach ($window_options as $option) {
                if ($option == 'height' || $option == 'width' || substr($option, 0, 6) == 'moodle') {
                    // do nothing
                } else {
                    $elements[] = $mform->createElement('checkbox', $option, '', get_string('new'.$option, 'resource'));
                }
            }
            $name = 'popupoptions_elements';
            $mform->addGroup($elements, $name, '', '<br />', false);
            $mform->disabledIf($name, 'showpopup', 'eq', 0);
            $mform->setAdvanced($name);

            foreach ($window_options as $option) {
                if ($option == 'height' || $option == 'width') {
                    $elements = array();
                    $elements[] = $mform->createElement('text', $option, '', array('size'=>'4'));
                    $elements[] = $mform->createElement('static', '', '', get_string('new'.$option, 'resource'));
                    $mform->addGroup($elements, $option.'_elements', '', ' ', false);
                    $mform->disabledIf($option.'_elements', 'showpopup', 'eq', 0);
                    $mform->setAdvanced($option.'_elements');
                }
            }

            // set defaults for window popup options
            foreach ($window_options as $option) {
                switch ($option) {
                    case 'height': $default = 450; break;
                    case 'width': $default = 620; break;
                    default: $default = 1; // checkbox
                }
                $mform->setType($option, PARAM_INT);
                $mform->setDefault($option, get_user_preferences('quizport_unit_popup_'.$option, $default));
            }

            // Entry page
            quizport_add_page_text($mform, 'entry', $is_add, QUIZPORT_NO);

            // Entry page options
            $elements = array();
            $page_options = get_page_options();
            foreach (array_keys($page_options['entry']) as $name) {
                $elements[] = $mform->createElement('checkbox', 'entry_'.$name, '', get_string('entry_'.$name, 'quizport'));
            }
            $mform->addGroup($elements, 'entryoptions_elements', get_string('entryoptions', 'quizport'), '<br />', false);
            $mform->setHelpButton('entryoptions_elements', array('entryoptions', get_string('entryoptions', 'quizport'), 'quizport'));
            $mform->disabledIf('entryoptions_elements', 'entrypage', 'eq', 0);
            $mform->setAdvanced('entryoptions_elements');


            // Exit page
            quizport_add_page_text($mform, 'exit', $is_add, QUIZPORT_YES);

            // Exit page feedback
            $elements = array();
            $elements[] = $mform->createElement('checkbox', 'exit_title', '', get_string('entry_title', 'quizport'));
            $elements[] = $mform->createElement('checkbox', 'exit_encouragement', '', get_string('exit_encouragement', 'quizport'));
            $elements[] = $mform->createElement('checkbox', 'exit_unitattempt', '', get_string('unitattemptgrade', 'quizport', '...'));
            $elements[] = $mform->createElement('checkbox', 'exit_unitgrade', '', get_string('unitgrade', 'quizport', '...'));
            $mform->addGroup($elements, 'exit_feedback', get_string('exit_feedback', 'quizport'), '<br />', false);
            $mform->setHelpButton('exit_feedback', array('exit_feedback', get_string('exit_feedback', 'quizport'), 'quizport'));
            $mform->disabledIf('exit_feedback', 'exitpage', 'eq', 0);
            $mform->setAdvanced('exit_feedback');

            // Exit page links
            $elements = array();
            $elements[] = $mform->createElement('checkbox', 'exit_retry', '', get_string('exit_retry', 'quizport').': '.get_string('exit_retry_text', 'quizport'));
            $elements[] = $mform->createElement('checkbox', 'exit_index', '', get_string('exit_index', 'quizport').': '.get_string('exit_index_text', 'quizport'));
            $elements[] = $mform->createElement('checkbox', 'exit_course', '', get_string('exit_course', 'quizport').': '.get_string('exit_course_text', 'quizport'));
            $elements[] = $mform->createElement('checkbox', 'exit_grades', '', get_string('exit_grades', 'quizport').': '.get_string('exit_grades_text', 'quizport'));
            $mform->addGroup($elements, 'exit_links', get_string('exit_links', 'quizport'), '<br />', false);
            $mform->setHelpButton('exit_links', array('exit_links', get_string('exit_links', 'quizport'), 'quizport'));
            $mform->disabledIf('exit_links', 'exitpage', 'eq', 0);
            $mform->setAdvanced('exit_links');

            // Previous and Next activity
            quizport_add_activity_list($mform, 'entry');
            quizport_add_activity_list($mform, 'exit');

            //-----------------------------------------------------------------------------------
            $mform->addElement('header', 'accesscontrolhdr', get_string('accesscontrol', 'lesson'));
            //-----------------------------------------------------------------------------------

            // Open time
            $mform->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'quizport'), array('optional'=>true));
            $mform->setHelpButton('timeopen', array('timeopen', get_string('timeopen', 'quizport'), 'quiz'));
            //$mform->setAdvanced('timeopen');

            // Close time
            $mform->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'quizport'), array('optional'=>true));
            $mform->setHelpButton('timeclose', array('timeopen', get_string('timeclose', 'quizport'), 'quiz'));
            //$mform->setAdvanced('timeclose');

            // Time limit
            quizport_add_timer_selector($mform, 'timelimit', get_string('timelimitsummary', 'quizport'));

            //Delay after attempt 1
            quizport_add_timer_selector($mform, 'delay1', get_string('delay1summary', 'quizport'));

            //Delay after attempt 2
            quizport_add_timer_selector($mform, 'delay2', get_string('delay2summary', 'quizport'));

            // Attempt limit
            quizport_add_attemptlimit_selector($mform, 'unit');

            // Allow resume ?
            quizport_add_allowresume($mform, 'unit');

            // Allow free access
            $optgroups = array(
                get_string('no') => array(0 => get_string('no')),
            );

            $options = array();
            $str = get_string('grade');
            for ($i=5; $i<=100; $i+=5) {
                $options[$i] = $str.' >= '.$i.'%';
            }
            $optgroups[get_string('yes').': '.$str] = $options;

            $options = array();
            $str = get_string('attempts', 'quiz');
            for ($i=-1; $i>=-5; $i--) {
                $options[$i] = $str.' >= '.abs($i);
            }
            $optgroups[get_string('yes').': '.$str] = $options;

            $mform->addElement('selectgroups', 'allowfreeaccess', get_string('allowfreeaccess', 'quizport'), $optgroups);
            $mform->setType('allowfreeaccess', PARAM_INT);
            $mform->setDefault('allowfreeaccess', get_user_preferences('quizport_unit_allowfreeaccess', QUIZPORT_NO));
            $mform->setHelpButton('allowfreeaccess', array('allowfreeaccess', get_string('allowfreeaccess', 'quizport'), 'quizport'));
            $mform->setAdvanced('allowfreeaccess');

            // Password
            // Subnet
            quizport_add_password_and_subnet($mform, 'unit');

            //-----------------------------------------------------------------------------------
            $mform->addElement('header', 'assessmenthdr', get_string('assessment', 'quizport'));
            //-----------------------------------------------------------------------------------

            // Unit grading method
            quizport_add_grademethod_selector($mform, 'unit', 'grademethod', QUIZPORT_GRADEMETHOD_HIGHEST, false);

            // Grade ignore
            quizport_add_gradeignore($mform, 'unit', 'grade');

            // Maximum grade
            quizport_add_grades_selector($mform, 'unit');

            // Grade weighting
            quizport_add_weighting_selector($mform, 'unit');

            // Unit attempt grading method
            quizport_add_grademethod_selector($mform, 'unitattempt', 'attemptgrademethod', QUIZPORT_GRADEMETHOD_TOTAL, true);

            // Remove grade item
            if ($CFG->majorrelease<1.9 || empty($this->_instance) || ! $DB->record_exists('grade_items', array('itemtype'=>'mod', 'itemmodule'=>'quizport', 'iteminstance'=>$this->_instance))) {
                // no grade item
                $mform->addElement('hidden', 'removegradeitem', 0);
            } else {
                // Moodle >= 1.9, editing QuizPort which already has a grade item
                $mform->addElement('selectyesno', 'removegradeitem', get_string('removegradeitem', 'quizport'));
                $mform->setHelpButton('removegradeitem', array('removegradeitem', get_string('removegradeitem', 'quizport'), 'quizport'));
                $mform->setAdvanced('removegradeitem');
                $mform->setType('removegradeitem', PARAM_INT);
                // element is not available if gradelimit==0 or gradeweighting==0
                $mform->disabledIf('removegradeitem', 'gradelimit', 'selected', 0);
                $mform->disabledIf('removegradeitem', 'gradeweighting', 'selected', 0);
            }

            //-----------------------------------------------------------------------------------
            // standard submit buttons, visibility and availability settings
            //-----------------------------------------------------------------------------------
            // see  http://docs.moodle.org/en/Groups  and  http://docs.moodle.org/en/Groupings
            $features = array(
                'groups'=>false, // not meaningful for QuizPorts
                'groupings'=>true,
                'groupmembersonly'=>true
            );
            if ($CFG->majorrelease<=1.7) {
                // Moodle 1.7 (and earlier) : disable unused settings
                $features['idnumber'] = false;
                $features['gradecat'] = false;
                // set the button text manually
                $submitlabel = get_string('savechanges');
                $submit2label = false;
            } else {
                // Moodle 1.8 (and later) : use default button text
                $submitlabel = null;
                $submit2label = null;
            }
            $this->standard_coursemodule_elements($features);
            $this->add_action_buttons(true, $submitlabel, $submit2label);

            //-----------------------------------------------------------------------------------
            // adjust vertical white space on some elements
            //-----------------------------------------------------------------------------------
            $src = $CFG->wwwroot.'/mod/quizport/mod_form.js';
            if (method_exists($PAGE, 'get_requires')) {
                // Moodle >= 2.0
                $requires = $PAGE->get_requires();
                $js = $requires->js($src, true);
                $js->asap();
                // $PAGE->get_requires()->js($src, true)->asap();
            } else {
                // Moodle <= 1.9
                $js = '<script type="text/javascript" src="'.$src.'"></script>';
                $mform->addElement('static', 'quizport_mod_form_js', '', $js);
            }
        }

        function data_preprocessing(&$defaults){
            global $DB;

            if (isset($defaults['id'])) {
                // edit mode, so get related unit data
                if ($unit = $DB->get_record('quizport_units', array('parenttype'=>QUIZPORT_PARENTTYPE_ACTIVITY, 'parentid'=>$defaults['id']))) {
                    $vars = get_object_vars($unit);
                    foreach ($vars as $name => $value) {
                        if ($name != 'id') {
                            $defaults[$name] = $value;
                        }
                    }
                }
            }

            quizport_pageoptions_defaults($defaults);
            quizport_popupoptions_defaults($defaults);

            quizport_set_timer_defaults($defaults, 'unit', 'timelimit');
            quizport_set_timer_defaults($defaults, 'unit', 'delay1');
            quizport_set_timer_defaults($defaults, 'unit', 'delay2');

            if (isset($defaults['password'])) {
                $defaults['unitpassword'] = $defaults['password'];
                unset($defaults['password']);
            }
        }

        function validation(&$data) {
            // http://docs.moodle.org/en/Development:lib/formslib.php_Validation
            $errors = array();

            if ($data['add']) {
                quizport_validate_file_group($data, $errors, 'unit', 'source');
                quizport_validate_file_group($data, $errors, 'unit', 'config');
            }

            if (count($errors)) {
                return $errors;
            } else {
                return true;
            }
        }

        function display() {
            if (function_exists('print_formslib_js_and_css')) {
                print_formslib_js_and_css($this->_form);
            }
            parent::display();
        }
    }
}
?>