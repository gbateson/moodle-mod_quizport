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

class mod_quizport_editcondition_form extends moodleform {
    // documentation on formslib.php here:
    // http://docs.moodle.org/en/Development:lib/formslib.php_Form_Definition

    function definition() {
        global $CFG, $QUIZPORT;

        if (empty($QUIZPORT->conditionid)) {
            $is_add = true;
        } else {
            $is_add = false;
        }

        $mform =&$this->_form;

        $minmax_options = array (
            QUIZPORT_MIN => get_string('minimum', 'quizport'),
            QUIZPORT_MAX => get_string('maximum', 'quizport')
        );

// group id
        if ($groups = $QUIZPORT->get_all_groups()) {
            $options = array(
                '0' => get_string('anygroup', 'quizport')
            );
            foreach($groups as $group) {
                if (isset($group->name)) {
                    $options[$group->id] = format_string($group->name);
                } else { // Moodle 1.8
                    $options[$group->id] = groups_get_group_name($group->id);
                }
            }
            $mform->addElement('select', 'groupid', get_string('groupid', 'quizport'), $options);
            $mform->setType('groupid', PARAM_INT);
            $mform->setDefault('groupid', get_user_preferences('quizport_condition_groupid', 0));
            $mform->setHelpButton('groupid', array('groupid', get_string('groupid', 'quizport'), 'quizport'));
        } else {
            $mform->addElement('hidden', 'groupid', '0');
            $mform->setType('groupid', PARAM_INT);
        }

// condition type
        if ($QUIZPORT->conditionid) {
            // updating a condition
            $conditiontype = $QUIZPORT->condition->conditiontype;
        } else {
            // adding a new condition
            $conditiontype = $QUIZPORT->conditiontype;
        }
        $mform->addElement('hidden', 'conditiontype', $conditiontype);
        $mform->setType('conditiontype', PARAM_INT);

// sortorder
        if ($conditiontype==QUIZPORT_CONDITIONTYPE_PRE) {
            $options = array();
            for ($i=0; $i<8; $i++) {
                $options[$i] = $i;
            }
            $mform->addElement('select', 'sortorder', get_string('sortorder', 'quizport'), $options);
            $mform->setType('sortorder', PARAM_INT);
            $mform->setDefault('sortorder', get_user_preferences('quizport_condition_sortorder', 0));
            $mform->setHelpButton('sortorder', array('conditionsortorder', get_string('sortorder', 'quizport'), 'quizport'));
        } else {
            // post-conditions don't use the sort order field (but they could)
            $mform->addElement('hidden', 'sortorder', 0);
            $mform->setType('sortorder', PARAM_INT);
        }

// condition quiz
        if ($conditiontype==QUIZPORT_CONDITIONTYPE_PRE) {
            // pre-conditions allow teacher to specify the quiz to which this condition refers
            $options = array(
                QUIZPORT_CONDITIONQUIZID_PREVIOUS => get_string('previousquiz', 'quizport')
            );
            if ($QUIZPORT->get_quizzes()) {
                // a specific QuizPort quiz from this unit
                foreach ($QUIZPORT->quizzes as $quiz) {
                    $options[$quiz->id] = format_string($quiz->name).' ('.$quiz->sortorder.')';
                }
            }
            $mform->addElement('select', 'conditionquizid', get_string('conditionquizid', 'quizport'), $options);
            $mform->setType('conditionquizid', PARAM_INT);
            $mform->setDefault('conditionquizid', get_user_preferences('quizport_condition_conditionquizid', 0));
            $mform->setHelpButton('conditionquizid', array('conditionquizid', get_string('conditionquizid', 'quizport'), 'quizport'));
        } else {
            // post-conditions always use the current quiz as the condition quiz
            $mform->addElement('hidden', 'conditionquizid', $QUIZPORT->quiz->id);
            $mform->setType('conditionquizid', PARAM_INT);
        }

// condition score
        $elements = array(
            $mform->createElement('select', 'conditionscoreminmax', '', $minmax_options),
            $mform->createElement('text', 'conditionscore', '', array('size' => '4')),
            $mform->createElement('checkbox', 'conditionscoredisable', '', get_string('disable'))
        );
        $mform->addGroup($elements, 'conditionscore_elements', get_string('conditionscore', 'quizport'), array(' '), false);
        $mform->setType('conditionscoreminmax', PARAM_INT);
        $mform->setType('conditionscore', PARAM_INT);
        $mform->setType('conditionscoredisable', PARAM_INT);
        $mform->disabledIf('conditionscore_elements', 'conditionscoredisable', 'checked');
        $mform->setHelpButton('conditionscore_elements', array('conditionscore', get_string('conditionscore', 'quizport'), 'quizport'));

// attempt count
        $options = array('0'=>'');
        for ($i=1; $i<=20; $i++) {
            $options[$i] = $i;
        }
        $elements = array(
            $mform->createElement('select', 'attemptcountminmax', '', $minmax_options),
            $mform->createElement('text', 'attemptcount', '', array('size' => '4')),
            $mform->createElement('checkbox', 'attemptcountdisable', '', get_string('disable'))
        );
        $mform->addGroup($elements, 'attemptcount_elements', get_string('attemptcount', 'quizport'), array(' '), false);
        $mform->setType('attemptcountminmax', PARAM_INT);
        $mform->setType('attemptcount', PARAM_INT);
        $mform->setType('attemptcountdisable', PARAM_INT);
        $mform->disabledIf('attemptcount_elements', 'attemptcountdisable', 'checked');
        $mform->setHelpButton('attemptcount_elements', array('attemptcount', get_string('attemptcount', 'quizport'), 'quizport'));

// attempt type
        $options = array (
            QUIZPORT_ATTEMPTTYPE_ANY => get_string('anyattempts', 'quizport'),
            QUIZPORT_ATTEMPTTYPE_RECENT => get_string('recentattempts', 'quizport'),
            QUIZPORT_ATTEMPTTYPE_CONSECUTIVE => get_string('consecutiveattempts', 'quizport'),
        );
        $mform->addElement('select', 'attempttype', get_string('attempttype', 'quizport'), $options);
        $mform->setType('attempttype', PARAM_INT);
        $mform->setDefault('attempttype', get_user_preferences('quizport_condition_attempttype', QUIZPORT_ATTEMPTTYPE_ANY));
        $mform->disabledIf('attempttype', 'attemptcount', 'eq', 0);
        $mform->disabledIf('attempttype', 'attemptcountdisable', 'checked');
        $mform->setHelpButton('attempttype', array('attempttype', get_string('attempttype', 'quizport'), 'quizport'));

// attempt time
        quizport_add_timer_selector($mform, 'attemptduration', '', true, true, true, true, array('minmax' => $minmax_options), false, false);

// delay time
        if ($conditiontype==QUIZPORT_CONDITIONTYPE_PRE) {
            quizport_add_timer_selector($mform, 'attemptdelay', '', true, true, true, true, array('minmax' => $minmax_options), false, false);
        } else {
            // post-conditions: time elapsed is always 0?
            $mform->addElement('hidden', 'attemptdelay', 0);
            $mform->setType('attemptdelay', PARAM_INT);
        }

// next quiz
        if ($conditiontype==QUIZPORT_CONDITIONTYPE_PRE) {
            // pre-conditions always have the current quiz as the next quiz
            $mform->addElement('hidden', 'nextquizid', $QUIZPORT->quiz->id);
            $mform->setType('nextquizid', PARAM_INT);
        } else {
            // post-conditions allow teacher to specify next quiz
            $options = array(
                QUIZPORT_CONDITIONQUIZID_SAME => get_string('samequiz', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_PREVIOUS => get_string('previousquiz', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_NEXT1 => get_string('next1quiz', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_NEXT2 => get_string('next2quiz', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_NEXT3 => get_string('next3quiz', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_NEXT4 => get_string('next4quiz', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_NEXT5 => get_string('next5quiz', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_UNSEEN => get_string('unseenquiz', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_UNANSWERED => get_string('unansweredquiz', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_INCORRECT => get_string('incorrectquiz', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_RANDOM => get_string('randomquiz', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_MENUNEXT => get_string('menuofnextquizzes', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_MENUNEXTONE => get_string('menuofnextquizzesone', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_MENUALL => get_string('menuofallquizzes', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_MENUALLONE => get_string('menuofallquizzesone', 'quizport'),
                QUIZPORT_CONDITIONQUIZID_ENDOFUNIT => get_string('endofunit', 'quizport')
            );
            if ($QUIZPORT->get_quizzes()) {
                foreach ($QUIZPORT->quizzes as $quiz) {
                    $options[$quiz->id] = format_string($quiz->name).' ('.$quiz->sortorder.')';
                }
            }
            $mform->addElement('select', 'nextquizid', get_string('nextquizid', 'quizport'), $options);
            $mform->setType('nextquizid', PARAM_INT);
            $mform->setDefault('nextquizid', get_user_preferences('quizport_condition_nextquizid', 0));
            $mform->setHelpButton('nextquizid', array('nextquizid', get_string('nextquizid', 'quizport'), 'quizport'));
        }

//-----------------------------------------------------------------------------------------------
// hidden fields
//-----------------------------------------------------------------------------------------------
        $params = array(
            'id'=>$QUIZPORT->conditionid, 'conditionid'=>0
        );
        quizport_add_hidden_fields($mform, $params);

//-----------------------------------------------------------------------------------------------
// standard submit buttons
//-----------------------------------------------------------------------------------------------
        $this->add_action_buttons();
    }

    function data_preprocessing(&$defaults){
        $fields = array('attemptduration','attemptdelay');
        foreach ($fields as $field) {
            quizport_set_timer_defaults($defaults, 'condition', $field, true);
        }

        $fields = array('conditionscore','attemptcount');
        foreach ($fields as $field) {

            if (! isset($defaults[$field])) {
                // adding a new record - get default from user preferences
                $defaults[$field] = get_user_preferences('quizport_condition_'.$field, 0);
            }

            if (empty($defaults[$field])) {
                $defaults[$field.'disable'] = 1;
            } else {
                $defaults[$field.'disable'] = 0;
            }

            if ($defaults[$field] <= 0) {
                $defaults[$field.'minmax'] = QUIZPORT_MIN;
            } else {
                $defaults[$field.'minmax'] = QUIZPORT_MAX;
            }

            $defaults[$field] = abs($defaults[$field]);
        }
    }

    function validation(&$data) {
        // http://docs.moodle.org/en/Development:lib/formslib.php_Validation
        $errors = array();

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