<?php
class quizport_output_hp_6_jcloze_xml_findit_a extends quizport_output_hp_6_jcloze_xml_findit {
    // source file types with which this output format can be used
    var $filetypes = array('hp_6_jcloze_xml');
    var $js_object_type = 'JClozeFindItA';

    // constructor function
    function quizport_output_hp_6_jcloze_xml_findit_a(&$quiz) {
        parent::quizport_output_hp_6_jcloze_xml_findit($quiz);

        // prepend templates for this output format
        array_unshift($this->templatesfolders, 'mod/quizport/output/hp/6/jcloze/xml/findit/a/templates');

        // replace standard jcloze.js with findit.a.js
        $this->javascripts = preg_grep('/jcloze.js/', $this->javascripts, PREG_GREP_INVERT);
        array_push($this->javascripts, 'mod/quizport/output/hp/6/jcloze/findit.a.js');
    }

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'CorrectChoice';
        return $names;
    }

    function get_stop_function_name() {
        return 'CorrectChoice';
    }

    function get_stop_function_args() {
        // the arguments required by CorrectChoice
        return 'null,'.QUIZPORT_STATUS_ABANDONED;
    }

    function get_stop_function_intercept() {
        // standard call to HP.onclickCheck() is not needed in CorrectChoice
        return '';
    }
}
?>