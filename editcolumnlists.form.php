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

class mod_quizport_editcolumnlists_form extends moodleform {
    // documentation on formslib.php here:
    // http://docs.moodle.org/en/Development:lib/formslib.php_Form_Definition

    var $columnlist = null;

    function definition() {
        global $CFG, $QUIZPORT, $PAGE;

        $mform =&$this->_form;

        $mform->addElement('header', 'columnlistshdr', '');

        $elements = array();
        $options = array_merge(
            array('0' => get_string('add').' ...'),
            $QUIZPORT->get_columnlists($QUIZPORT->columnlisttype)
        );
        $elements[] = $mform->createElement('select', 'columnlistid', '', $options);
        $elements[] = $mform->createElement('text', 'columnlistname', '', array('size' => '10'));
        $elements[] = $mform->createElement('static', 'onchangecolumnlistid', '', ''
            .'<script type="text/javascript">'."\n"
            .'//<![CDATA['."\n"
            .'var obj = document.getElementById("id_columnlistid");'."\n"
            .'if (obj) {'."\n"
            .'    obj.onchange = function () {'."\n"
            .'        var href = self.location.href.replace(new RegExp("columnlistid=\\\\w+&?"), "");'."\n"
            .'        if (this.selectedIndex) {'."\n"
            .'            var char = href.charAt(href.length-1);'."\n"
            .'            if (char!="?" && char!="&") {'."\n"
            .'                if (href.indexOf("?")<0) {'."\n"
            .'                    href += "?";'."\n"
            .'                } else {'."\n"
            .'                    href += "&";'."\n"
            .'                }'."\n"
            .'            }'."\n"
            .'            href += "columnlistid=" + this.options[this.selectedIndex].value;'."\n"
            .'        }'."\n"
            .'        self.location.href = href;'."\n"
            .'    }'."\n"
            .'}'."\n"
            .'//]]>'."\n"
            .'</script>'."\n"
        );
        $mform->addGroup($elements, 'columnlists_elements', '', array(' '), false);
        $mform->disabledIf('columnlists_elements', 'columnlists', 'ne', 0);
        $mform->setDefault('columnlists', get_user_preferences('quizport_'.$QUIZPORT->columnlisttype.'_columnlists', 0));

        $sections = $this->quizport_columnlists_sections();

        foreach ($sections as $section => $fields) {
            switch ($section) {

                case 'actions':
                    $mform->addElement('header', $section.'hdr', '');
                    $elements = array();
                    foreach ($fields as $field=>$str) {
                        if ($field=='cancel') {
                            $elements[] = &$mform->createElement('cancel');
                        } else {
                            $elements[] = &$mform->createElement('submit', $field, get_string($str ? $str : $field));
                        }
                    }
                    $mform->addGroup($elements, 'buttons_elements', '', array(' '), false);
                    break;

                default:
                    switch ($section) {
                        case 'general':
                        case 'display':
                            $mform->addElement('header', $section.'hdr', get_string($section, 'form'));
                            break;
                        case 'access':
                            $mform->addElement('header', 'accesscontrolhdr', get_string('accesscontrol', 'lesson'));
                            break;
                        default:
                            $mform->addElement('header', $section.'hdr', get_string($section, 'quizport'));
                    }
                    foreach ($fields as $field) {
                        switch ($field) {
                            case 'name':
                                $label = get_string('name');
                                break;
                            case 'password':
                                $label = get_string('requirepassword', 'quiz');
                                break;
                            case 'reviewoptions':
                                $label = get_string('reviewoptionsheading', 'quiz');
                                break;
                            case 'showpopup':
                                $label = get_string('display', 'resource');
                                break;
                            default:
                                $label = get_string($field, 'quizport');
                        }
                        $mform->addElement('checkbox', $field, $label);
                    }
            } // end switch $section
        }

        $params = array(
            'id' => $QUIZPORT->courserecord->id,
            'columnlistid' => 0,
        );
        quizport_add_hidden_fields($mform, $params);
    }

    function quizport_columnlists_sections() {
        global $QUIZPORT;

        if ($QUIZPORT->columnlisttype=='quiz') {
            return array(
                'general' => array(
                    'name','sourcelocation','sourcefile','sourcetype','configlocation','configfile'
                ),
                'display' => array(
                    'outputformat','navigation','title','stopbutton','stoptext','usefilters','useglossary','usemediafilter','studentfeedback'
                ),
                'access' => array(
                    'timeopen','timeclose','timelimit','delay1','delay2','delay3','attemptlimit','allowresume','password','subnet','reviewoptions'

                ),
                'assessment' => array(
                    'scoremethod','scoreignore','scorelimit','scoreweighting','clickreporting','discarddetails'
                ),
                'conditions' => array(
                    'preconditions','postconditions'

                ),
                'actions' => array(
                    'update' => 'savechanges', 'cancel' => '', 'delete' => '', 'deleteall' => ''
                )
            );
        }

        if ($QUIZPORT->columnlisttype=='unit') {
            return array(
                'general' => array(
                    'name','quizzes','entrycm','entrygrade','exitcm','exitgrade'
                ),
                'display' => array(
                    'entrypage','entrytext','entryoptions','exitpage','exittext','exitoptions','showpopup','popupoptions'
                ),
                'access' => array(
                    'timeopen','timeclose','timelimit','delay1','delay2','password','subnet','allowresume','allowfreeaccess','attemptlimit'
                ),
                'assessment' => array(
                    'attemptgrademethod','grademethod','gradeignore','gradelimit','gradeweighting'
                ),
                'actions' => array(
                    'update' => 'savechanges', 'cancel' => '', 'delete' => '', 'deleteall' => ''
                ),
            );
        }

        // not 'unit' or 'quiz'
        return array();
    }

    function data_preprocessing(&$defaults){
        global $QUIZPORT;
        if ($QUIZPORT->columnlistid) {
            $columnlists = $QUIZPORT->get_columnlists($QUIZPORT->columnlisttype, true);
            if (array_key_exists($QUIZPORT->columnlistid, $columnlists)) {
                foreach ($columnlists[$QUIZPORT->columnlistid] as $column) {
                    $defaults[$column] = 1;
                }
            }
        }
    }

    function validation(&$data) {
        // http://docs.moodle.org/en/Development:lib/formslib.php_Validation
        $errors = array();

        if (! $this->createcolumnlist($data)) {
            $errors['columnlists'] = get_string('error_nocolumns', 'quizport');
        }

        if (count($errors)) {
            return $errors;
        } else {
            return true;
        }
    }

    function createcolumnlist(&$data) {
        if (is_null($this->columnlist)) {
            $this->columnlist = array();

            $sections = $this->quizport_columnlists_sections();
            foreach ($sections as $section => $fields) {

                if ($section=='hidden' || $section=='actions') {
                    continue;
                }

                foreach ($fields as $field) {
                    if (empty($data[$field])) {
                        continue;
                    }
                    $this->columnlist[] = $field;
                }
            }
        }
        return count($this->columnlist);
    }

    function display() {
        if (function_exists('print_formslib_js_and_css')) {
            print_formslib_js_and_css($this->_form);
        }
        parent::display();
    }
}
?>