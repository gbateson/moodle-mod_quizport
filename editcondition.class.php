<?php // $Id$

class mod_quizport_editcondition extends mod_quizport {
    var $pagehastabs = false;
    var $pagehascolumns = false;
    var $pagehasheader = false;
    var $pagehasfooter = false;

    function print_heading() {
        if ($this->conditionid==0) {
            $conditiontype = $this->conditiontype;
        } else {
            $conditiontype = $this->condition->conditiontype;
        }
        if ($conditiontype==QUIZPORT_CONDITIONTYPE_PRE) {
            $type = 'precondition';
        } else {
            $type = 'postcondition';
        }
        switch ($this->action) {
            case 'add':
                $subheading = get_string('addinganew', 'moodle', moodle_strtolower(get_string($type, 'quizport')));
                break;
            case 'edit':
                $subheading = get_string('updatinga', 'moodle', moodle_strtolower(get_string($type, 'quizport')));
                break;
            case 'delete':
                $subheading = get_string('delete'.$type, 'quizport');
                break;
            case 'deleteall':
                $subheading = get_string('deleteall'.$type.'s', 'quizport');
                break;
            default:
                $subheading = '';
        }

        print_heading(format_string($this->quiz->name));

        if ($subheading) {
            print '<h3 class="main">'.$subheading.'</h3>';
        }
    }

    function print_content() {
        global $mform;

        // initizialize data in form
        if ($this->conditionid) {
            // editing a condition ($conditionid was set up in mod/quizport/class.php)
            $defaults = (array)$this->condition;
        } else {
            // adding a new condition to this quiz
            $defaults = array('quizid'=>$this->quizid);
        }
        $mform->data_preprocessing($defaults);
        $mform->set_data($defaults);
        unset($defaults);

        // display the form
        $mform->display();
    }

    function print_js() {
        global $newdata;
        if (isset($newdata)) {
            $conditiontype = $newdata->conditiontype;
        } else {
            if (isset($this->condition)) {
                $conditiontype = $this->condition->conditiontype;
            } else {
                $conditiontype = $this->conditiontype;
            }
        }
        if ($conditiontype) {
            if ($conditiontype==QUIZPORT_CONDITIONTYPE_PRE) {
                $type = 'preconditions';
            } else {
                $type = 'postconditions';
            }
            print '<script type="text/javascript">'."\n";
            print '//<![CDATA['."\n";
            print '    if (window.opener) {'."\n";
            if ($this->quizid==get_user_preferences('quizport_quiz_'.$type, 0)) {
                print '        var obj = opener.document.getElementById("quizport_'.$type.'_default");'."\n";
                print '        if (obj) {'."\n";
                print '            obj.innerHTML = "'.$this->format_conditions($this->quizid, $conditiontype, false, true, false).'";'."\n";
                print '        }'."\n";
            }
            print '        var obj = opener.document.getElementById("quizport_'.$type.'_'.$this->quizid.'");'."\n";
            print '        if (obj) {'."\n";
            print '            obj.innerHTML = "'.$this->format_conditions($this->quizid, $conditiontype, false, true).'";'."\n";
            print '        }'."\n";
            print '        window.close()'."\n";
            print '    }'."\n";
            print '//]]>'."\n";
            print '</script>'."\n";
        }
    }
}
?>