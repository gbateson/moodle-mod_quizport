<?php // $Id$
/**
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

class mod_quizport_editquiz_form extends moodleform {
    // documentation on formslib.php here:
    // http://docs.moodle.org/en/Development:lib/formslib.php_Form_Definition

    function definition() {
        global $CFG, $QUIZPORT, $PAGE;

        // Moodle >= 2.0: Advanced buttons require lib/yui/yahoo/*.js
        if (method_exists($PAGE, 'get_requires')) {
            $requires = $PAGE->get_requires();
            $yui_lib = $requires->yui_lib('yahoo');
            $yui_lib->asap();
            // $PAGE->get_requires()->yui_lib('yahoo')->asap();
        }

        if (empty($QUIZPORT->quizid)) {
            $is_add = true;
        } else {
            $is_add = false;
        }

        $mform =&$this->_form;

//-----------------------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));
//-----------------------------------------------------------------------------------------------

// Name
        quizport_add_name_group($mform, 'quiz', $is_add, '', get_string('quizname', 'quizport'));

// Source file
        quizport_add_file_group($mform, 'quiz', 'source');

// Configuration file
        quizport_add_file_group($mform, 'quiz', 'config');
        $mform->setAdvanced('config_elements');

// Add chain
        quizport_add_quizchain($mform, 'quiz', $is_add);

// Intro source
        // adding this field allows us to use the standard "quizport_add_quizzes()" function in "mod/quizport/lib.php"
        $mform->addElement('hidden', 'introsource', QUIZPORT_TEXTSOURCE_SPECIFIC);

//-----------------------------------------------------------------------------------------------
        $mform->addElement('header', 'displayhdr', get_string('display', 'form'));
//-----------------------------------------------------------------------------------------------

// Output format
        $options = array();
        if ($QUIZPORT->quizid) {
            if ($types = $QUIZPORT->quiz->output->source->get_outputformats()) {
                foreach ($types as $type) {
                    $str = get_string('outputformat_'.$type, 'quizport');
                    if ($QUIZPORT->quiz->outputformat=='outputformat_'.$type) {
                        // always add the output format used by the current quiz
                        $options[$type] = $str;
                    } else if (substr($str, 0, 2)=='[[') {
                        // skip output formats for which no description string is defined on this Moodle site
                    } else {
                        // description string is defined, so add it to the list
                        $options[$type] = $str;
                    }
                }
                asort($options);
            }
        }
        // prepend "best" option
        array_unshift($options, get_string('outputformat_best', 'quizport'));

        $button = false; // $mform->createElement('button', 'popup', get_string('update'));

        $elements = array($mform->createElement('select', 'outputformat', '', $options));
        if ($button) {
            $elements[] = &$button;
        }
        $mform->addGroup($elements, 'outputformat_elements', get_string('outputformat', 'quizport'), array(' '), false);
        $mform->setDefault('outputformat', get_user_preferences('quizport_quiz_outputformat', 'best'));
        $mform->setHelpButton('outputformat_elements', array('outputformat', get_string('outputformat', 'quizport'), 'quizport'));
        if ($button) {
            // set button attributes to open new window
            $url = ''
                .$QUIZPORT->format_url('editquiz.php', '', array())
                ."&amp;sourcefile='+getObjValue(this.form.sourcefile)+'"
                ."&amp;sourcelocation='+getObjValue(this.form.sourcelocation)+'"
            ;
            $options = 'menubar=0,location=0,scrollbars,resizable,width=750,height=500';
            $attributes = array(
                'title'=>get_string('updateoutputformat', 'quizport'),
                'onclick'=>"return openpopup('$url', '".$button->getName()."', '$options', 0);"
            );
            $button->updateAttributes($attributes);
        }

// Navigation
        $elements = array();
        $options = quizport_format_navigation();
        $mform->addElement('select', 'navigation', get_string('navigation', 'quizport'), $options);
        $mform->setType('navigation', PARAM_INT);
        $mform->setDefault('navigation', get_user_preferences('quizport_quiz_navigation', QUIZPORT_NAVIGATION_BAR));
        $mform->setHelpButton('navigation', array('navigation', get_string('navigation', 'quizport'), 'quizport'));

// Title
        $elements = array();
        $options = quizport_format_title();
        $elements[] = $mform->createElement('select', 'title_textsource', '', $options);
        $elements[] = $mform->createElement('checkbox', 'title_prependunitname', '', get_string('title_prependunitname', 'quizport'));
        $elements[] = $mform->createElement('checkbox', 'title_appendsortorder', '', get_string('title_appendsortorder', 'quizport'));

        $mform->addGroup($elements, 'title_elements', get_string('title', 'quizport'), '<br />', false);
        $mform->setHelpButton('title_elements', array('title', get_string('title', 'quizport'), 'quizport'));
        $mform->setAdvanced('title_elements');

        $mform->setType('title_textsource', PARAM_INT);
        $mform->setType('title_prependunitname', PARAM_INT);
        $mform->setType('title_appendsortorder', PARAM_INT);

        $mform->setDefault('title_textsource', get_user_preferences('quizport_quiz_title_textsource', QUIZPORT_TEXTSOURCE_SPECIFIC));
        $mform->setDefault('title_prependunitname', get_user_preferences('quizport_quiz_title_prependunitname', QUIZPORT_NO));
        $mform->setDefault('title_appendsortorder', get_user_preferences('quizport_quiz_title_appendsortorder', QUIZPORT_NO));

// Show stop button
        $elements = array();
        $elements[] = $mform->createElement('selectyesno', 'stopbutton_yesno', '');
        $options = array(
            'quizport_giveup' => get_string('giveup', 'quizport'),
            'specific' => get_string('stopbutton_specific', 'quizport')
        );
        // $elements[] = $mform->createElement('static', '', '', '<br />');
        $elements[] = $mform->createElement('select', 'stopbutton_type', '', $options);
        $elements[] = $mform->createElement('text', 'stopbutton_text', '', array('size' => '20'));

        $mform->addGroup($elements, 'stopbutton_elements', get_string('stopbutton', 'quizport'), ' ', false);
        $mform->setHelpButton('stopbutton_elements', array('stopbutton', get_string('stopbutton', 'quizport'), 'quizport'));
        $mform->setAdvanced('stopbutton_elements');

        $mform->setType('stopbutton_yesno', PARAM_INT);
        $mform->setType('stopbutton_type', PARAM_ALPHAEXT);
        $mform->setType('stopbutton_text', PARAM_TEXT);

        $mform->disabledIf('stopbutton_elements', 'stopbutton_yesno', 'ne', '1');
        $mform->disabledIf('stopbutton_text', 'stopbutton_type', 'ne', 'specific');

// Use filters
        $mform->addElement('selectyesno', 'allowpaste', get_string('allowpaste', 'quizport'));
        $mform->setType('allowpaste', PARAM_INT);
        $mform->setDefault('allowpaste', get_user_preferences('quizport_quiz_allowpaste', QUIZPORT_YES));
        $mform->setHelpButton('allowpaste', array('allowpaste', get_string('allowpaste', 'quizport'), 'quizport'));
        $mform->setAdvanced('allowpaste');

// Use filters
        $mform->addElement('selectyesno', 'usefilters', get_string('usefilters', 'quizport'));
        $mform->setType('usefilters', PARAM_INT);
        $mform->setDefault('usefilters', get_user_preferences('quizport_quiz_usefilters', QUIZPORT_YES));
        $mform->setHelpButton('usefilters', array('usefilters', get_string('usefilters', 'quizport'), 'quizport'));
        $mform->setAdvanced('usefilters');

// Use glossary
        $mform->addElement('selectyesno', 'useglossary', get_string('useglossary', 'quizport'));
        $mform->setType('useglossary', PARAM_INT);
        $mform->setDefault('useglossary', get_user_preferences('quizport_quiz_useglossary', QUIZPORT_YES));
        $mform->setHelpButton('useglossary', array('useglossary', get_string('useglossary', 'quizport'), 'quizport'));
        $mform->setAdvanced('useglossary');

// Use media filters
        $plugins = get_list_of_plugins('mod/quizport/mediafilter'); // sorted

        if (in_array('moodle', $plugins)) {
            // make 'moodle' the first element in the plugins array
            unset($plugins[array_search('moodle', $plugins)]);
            array_unshift($plugins, 'moodle');
        }

        // define element type for list of mediafilters (select, radio, checkbox)
        $usemediafilter_type = 'select';

        if ($usemediafilter_type=='select') {
            // media players as a drop-down list
            $options = array('' => get_string('none'));
            foreach ($plugins as $plugin) {
                $options[$plugin] = get_string('mediafilter_'.$plugin, 'quizport');
            }
            $mform->addElement('select', 'usemediafilter', get_string('usemediafilter', 'quizport'), $options);
            $mform->setType('usemediafilter', PARAM_SAFEDIR); // [a-zA-Z0-9_-]
            $mform->setDefault('usemediafilter', get_user_preferences('quizport_quiz_usemediafilter', 'moodle'));
            $mform->setHelpButton('usemediafilter', array('usemediafilter', get_string('usemediafilter', 'quizport'), 'quizport'));
        }
        if ($usemediafilter_type=='radio') {
            // media players as a set of radio buttons
            $elements = array();
            $elements[] = &$mform->createElement('radio', 'usemediafilter', '', get_string('none'), '');
            foreach ($plugins as $plugin) {
                $str = get_string('mediafilter_'.$plugin, 'quizport');
                $str .= helpbutton ('mediafilter_'.$plugin, $str, 'quizport', true, false, '', true);
                $elements[] = &$mform->createElement('radio', 'usemediafilter', '', $str, $plugin);
            }
            $mform->addGroup($elements, 'usemediafilter_elements', get_string('usemediafilter', 'quizport'), array('<br />'), false);
            $mform->setType('usemediafilter', PARAM_SAFEDIR); // [a-zA-Z0-9_-]
            $mform->setDefault('usemediafilter', get_user_preferences('quizport_quiz_usemediafilter', 'moodle'));
            $mform->setHelpButton('usemediafilter_elements', array('usemediafilter', get_string('usemediafilter', 'quizport'), 'quizport'));
        }
        if ($usemediafilter_type=='checkbox') {
            // media players as a set of checkboxes
            $elements = array();
            $elements[0] = &$mform->createElement('checkbox', 'usemediafilter', '', get_string('none'));
            $elements[0]->updateAttributes(array('name'=>'usemediafilter[0]', 'value'=>'none'));
            foreach ($plugins as $i => $plugin) {
                $str = get_string('mediafilter_'.$plugin, 'quizport');
                $str .= helpbutton ('mediafilter_'.$plugin, $str, 'quizport', true, false, '', true);
                $elements[$i+1] = &$mform->createElement('checkbox', 'usemediafilter', '', $str);
                $elements[$i+1]->updateAttributes(array('name'=>'usemediafilter['.($i+1).']', 'value'=>$plugin));
            }
            $mform->addGroup($elements, 'usemediafilter_elements', get_string('usemediafilter', 'quizport'), array('<br />'), false);
            $mform->setType('usemediafilter', PARAM_SAFEDIR); // [a-zA-Z0-9_-]
            $mform->setDefault('usemediafilter', get_user_preferences('quizport_quiz_usemediafilter', 'moodle'));
            $mform->setHelpButton('usemediafilter_elements', array('usemediafilter', get_string('usemediafilter', 'quizport'), 'quizport'));
            foreach ($plugins as $i => $plugin) {
                $mform->disabledIf('usemediafilter['.($i+1).']', 'usemediafilter[0]', 'checked');
            }
            // this displays OK, but results are returned as an array, so need special post processing
            // in editquiz.php when the form data is saved to the database
        }
        $mform->setAdvanced('usemediafilter');

// Student feedback
        $elements = array();
        $options = quizport_format_studentfeedback();
        $elements[] = &$mform->createElement('select', 'studentfeedback', '', $options);
        $elements[] = &$mform->createElement('text', 'studentfeedbackurl', '', array('size'=>'40'));
        $mform->addGroup($elements, 'studentfeedback_elements', get_string('studentfeedback', 'quizport'), array(' '), false);
        $mform->disabledIf('studentfeedback_elements', 'studentfeedback', 'eq', QUIZPORT_FEEDBACK_NONE);
        $mform->disabledIf('studentfeedback_elements', 'studentfeedback', 'eq', QUIZPORT_FEEDBACK_MOODLEFORUM);
        $mform->disabledIf('studentfeedback_elements', 'studentfeedback', 'eq', QUIZPORT_FEEDBACK_MOODLEMESSAGING);
        $mform->setHelpButton('studentfeedback_elements', array('studentfeedback', get_string('studentfeedback', 'quizport'), 'quizport'));
        $mform->setAdvanced('studentfeedback_elements');
        $mform->setType('studentfeedback', PARAM_INT);
        $mform->setType('studentfeedbackurl', PARAM_URL);

//-----------------------------------------------------------------------------------------------
        $mform->addElement('header', 'accesscontrolhdr', get_string('accesscontrol', 'lesson'));
//-----------------------------------------------------------------------------------------------

// Open time
        $mform->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'quizport'), array('optional'=>true));
        $mform->setHelpButton('timeopen', array('timeopen', get_string('timeopen', 'quizport'), 'quiz'));

// Close time
        $mform->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'quizport'), array('optional'=>true));
        $mform->setHelpButton('timeclose', array('timeopen', get_string('timeclose', 'quizport'), 'quiz'));

// Time limit
        $options = array(
            QUIZPORT_TIMELIMIT_TEMPLATE => get_string('timelimittemplate', 'quizport'),
            QUIZPORT_TIMELIMIT_SPECIFIC => get_string('timelimitspecific', 'quizport'),
            QUIZPORT_TIMELIMIT_DISABLE => get_string('disable'),
        );
        quizport_add_timer_selector(
            $mform, 'timelimit', get_string('timelimitsummary', 'quizport'),
            true, true, true, false, array('disable' => $options), false // hours, mins, secs, disable, before, after
        );
        $mform->disabledIf('timelimit_elements', 'timelimitdisable', 'eq', QUIZPORT_TIMELIMIT_DISABLE); // 1
        $mform->disabledIf('timelimit_elements', 'timelimitdisable', 'eq', QUIZPORT_TIMELIMIT_TEMPLATE); // -1

//Delay after attempt 1
        quizport_add_timer_selector($mform, 'delay1', get_string('delay1summary', 'quizport'));

//Delay after attempt 2
        quizport_add_timer_selector($mform, 'delay2', get_string('delay2summary', 'quizport'));

//Delay after attempt 3
        // initialize values for $hours, $minutes and $seconds
        $options = array(
            '' => get_string('delay3specific', 'quizport'),
            QUIZPORT_DELAY3_TEMPLATE => get_string('delay3template', 'quizport'),
            QUIZPORT_DELAY3_AFTEROK => get_string('delay3afterok', 'quizport'),
            QUIZPORT_DELAY3_DISABLE => get_string('delay3disable', 'quizport'),
        );
        quizport_add_timer_selector(
            $mform, 'delay3', get_string('delay3summary', 'quizport'), // form, fieldname, description
            false, false, true, false, array('' => $options), false // hours, mins, secs, disable, before, after
        );
        $mform->disabledIf('delay3_elements', 'delay3', 'eq', QUIZPORT_DELAY3_TEMPLATE);
        $mform->disabledIf('delay3_elements', 'delay3', 'eq', QUIZPORT_DELAY3_AFTEROK);
        $mform->disabledIf('delay3_elements', 'delay3', 'eq', QUIZPORT_DELAY3_DISABLE);
        // $mform->setAdvanced('delay3_elements');

// Attempt limit
        quizport_add_attemptlimit_selector($mform, 'quiz');

// Allow resume ?
        quizport_add_allowresume($mform, 'quiz');

// Password
// Subnet
        quizport_add_password_and_subnet($mform, 'quiz');

//-----------------------------------------------------------------------------------------------
        $mform->addElement('header', 'reviewoptionshdr', get_string('reviewoptionsheading', 'quiz'));
        $mform->setHelpButton('reviewoptionshdr', array('reviewoptions', get_string('reviewoptionsheading','quiz'), 'quiz'));
//-----------------------------------------------------------------------------------------------

        if ($QUIZPORT->quizid && $QUIZPORT->quiz->output->provide_review()) {
            quizport_add_reviewoptions($mform, 'quiz');
        } else {
            $mform->addElement('static', '', get_string('noneavailable', 'quizport'), '');
            $mform->addElement('hidden', 'reviewoptions', get_user_preferences('quizport_quiz_reviewoptions', 0));
            $mform->setType('reviewoptions', PARAM_INT);
        }

//-----------------------------------------------------------------------------------------------
        $mform->addElement('header', 'assessmenthdr', get_string('assessment', 'quizport'));
        $mform->setHelpButton('assessmenthdr', array('assessment', get_string('assessment', 'quizport'), 'quizport'));
//-----------------------------------------------------------------------------------------------

// Quiz scoring method
        quizport_add_grademethod_selector($mform, 'quiz', 'scoremethod', QUIZPORT_GRADEMETHOD_HIGHEST, false);

// Score ignore
        quizport_add_gradeignore($mform, 'quiz', 'score');

// Maximum score
        quizport_add_grades_selector($mform, 'quiz');

// Score weighting
        quizport_add_weighting_selector($mform, 'quiz');

// Enable click reporting?
        // this doesn't really belong in the "Assessment" section,
        // but it is a bit of a wate to put it in its own "Reports" section
        // it is vaguely conntected with the "Review" settings
        $mform->addElement('selectyesno', 'clickreporting', get_string('clickreporting', 'quizport'));
        $mform->setDefault('clickreporting', get_user_preferences('quizport_clickreporting', QUIZPORT_NO));
        $mform->setHelpButton('clickreporting', array('clickreporting', get_string('clickreporting', 'quizport'), 'quizport'));
        $mform->setAdvanced('clickreporting');

//-----------------------------------------------------------------------------------------------
// Conditions
//-----------------------------------------------------------------------------------------------
        if ($QUIZPORT->quizid) {
            // pre-conditions
            $mform->addElement('header', 'preconditionshdr', get_string('preconditions', 'quizport'));
            $mform->addElement('static', '', '', $QUIZPORT->format_conditions($QUIZPORT->quizid, QUIZPORT_CONDITIONTYPE_PRE));

            // post-conditions
            $mform->addElement('header', 'postconditionshdr', get_string('postconditions', 'quizport'));
            $mform->addElement('static', '', '', $QUIZPORT->format_conditions($QUIZPORT->quizid, QUIZPORT_CONDITIONTYPE_POST));
        }

//-----------------------------------------------------------------------------------------------
// hidden fields
//-----------------------------------------------------------------------------------------------
        $params = array(
            'id'=>$QUIZPORT->quizid, 'quizid'=>0,
            'afterquizid'=>optional_param('afterquizid', 0, PARAM_INT)
        );
        quizport_add_hidden_fields($mform, $params);

//-----------------------------------------------------------------------------------------------
// standard submit buttons
//-----------------------------------------------------------------------------------------------
        $this->add_action_buttons();

//-----------------------------------------------------------------------------------------------
// adjust vertical white space on some elements
//-----------------------------------------------------------------------------------------------
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

        // get related unit record, if there is one
        if (isset($defaults['id'])) {
            if ($unit = $DB->get_record('quizport_units', array('parenttype'=>QUIZPORT_PARENTTYPE_ACTIVITY, 'parentid'=>$defaults['id']))) {
                $vars = get_object_vars($unit);
                foreach ($vars as $name => $value) {
                    if ($name != 'id') {
                        $defaults[$name] = $value;
                    }
                }
            }
        }

        // set title options
        if (! isset($defaults['title'])) {
            $defaults['title'] = get_user_preferences('quizport_quiz_title', QUIZPORT_TEXTSOURCE_SPECIFIC);
        }
        $defaults['title_textsource'] = $defaults['title'] & QUIZPORT_TITLE_SOURCE;
        if ($defaults['title'] & QUIZPORT_TITLE_UNITNAME) {
            $defaults['title_prependunitname'] = 1;
        } else {
            $defaults['title_prependunitname'] = 0;
        }
        if ($defaults['title'] & QUIZPORT_TITLE_SORTORDER) {
            $defaults['title_appendsortorder'] = 1;
        } else {
            $defaults['title_appendsortorder'] = 0;
        }

        // set stopbutton options
        if (! isset($defaults['stopbutton'])) {
            $defaults['stopbutton'] = get_user_preferences('quizport_quiz_stopbutton', QUIZPORT_NO);
        }
        if (! isset($defaults['stoptext'])) {
            $defaults['stoptext'] = get_user_preferences('quizport_quiz_stoptext', '');
        }
        switch ($defaults['stopbutton']) {
            case QUIZPORT_STOPBUTTON_SPECIFIC:
                $defaults['stopbutton_yesno'] = 1;
                $defaults['stopbutton_type'] = 'specific';
                $defaults['stopbutton_text'] = $defaults['stoptext'];
                break;
            case QUIZPORT_STOPBUTTON_LANGPACK:
                $defaults['stopbutton_yesno'] = 1;
                $defaults['stopbutton_type'] = $defaults['stoptext'];
                $defaults['stopbutton_text'] = '';
                break;
            case QUIZPORT_STOPBUTTON_NONE:
            default:
                $defaults['stopbutton_yesno'] = 0;
                $defaults['stopbutton_type'] = '';
                $defaults['stopbutton_text'] = '';
        }

        quizport_set_timer_defaults($defaults, 'quiz', 'timelimit', false, QUIZPORT_TIMELIMIT_TEMPLATE);
        quizport_set_timer_defaults($defaults, 'quiz', 'delay1');
        quizport_set_timer_defaults($defaults, 'quiz', 'delay2');

        if (! isset($defaults['delay3'])) {
            // default delay at end of quiz is 2 seconds (or whatever this user chose last time)
            $defaults['delay3'] = get_user_preferences('quizport_quiz_delay3', 2);
        }
        if ($defaults['delay3'] > 0) {
            $defaults['delay3seconds'] = $defaults['delay3'];
        } else {
            $defaults['delay3seconds'] = 0;
        }

        quizport_set_reviewoptions_defaults($defaults, 'quiz');

        if (isset($defaults['password'])) {
            $defaults['quizpassword'] = $defaults['password'];
            unset($defaults['password']);
        }
    }

    function validation(&$data) {
        // http://docs.moodle.org/en/Development:lib/formslib.php_Validation
        $errors = array();

        quizport_validate_file_group($data, $errors, 'quiz', 'source');
        quizport_validate_file_group($data, $errors, 'quiz', 'config');

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
?>