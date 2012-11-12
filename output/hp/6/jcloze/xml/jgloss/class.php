<?php
class quizport_output_hp_6_jcloze_xml_jgloss extends quizport_output_hp_6_jcloze_xml {
    // source file types with which this output format can be used
    var $filetypes = array('hp_6_jcloze_xml');
    var $js_object_type = 'JClozeJGloss';

    // Glossary autolinking settings (override standard JCloze - (?:I\[\d+\]\[1\]\[\d+\]\[2\]))
    var $headcontent_strings = 'Feedback|Correct|Incorrect|GiveHint|YourScoreIs|Guesses|(?:I\[\d+\]\[2\])';
    var $headcontent_arrays = '';

    // constructor function
    function quizport_output_hp_6_jcloze_xml_jgloss(&$quiz) {
        parent::quizport_output_hp_6_jcloze_xml($quiz);

        // prepend templates for this output format
        array_unshift($this->templatesfolders, 'mod/quizport/output/hp/6/jcloze/xml/jgloss/templates');

        // replace standard jcloze.js with jgloss.js
        $this->javascripts = preg_grep('/jcloze.js/', $this->javascripts, PREG_GREP_INVERT);
        array_push($this->javascripts, 'mod/quizport/output/hp/6/jcloze/jgloss.js');
    }

    function fix_bodycontent() {
        $this->fix_bodycontent_rottmeier();
    }

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'Show_GlossContent,ShowElements,Add_GlossFunctionality';
        return $names;
    }

    function get_stop_function_name() {
        return 'HP.onunload';
    }

    function get_stop_function_args() {
        return QUIZPORT_STATUS_COMPLETED;
    }
}
?>