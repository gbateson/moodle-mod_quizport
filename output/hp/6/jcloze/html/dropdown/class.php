<?php
class quizport_output_hp_6_jcloze_html_dropdown extends quizport_output_hp_6_jcloze_html {
    // source file types with which this output format can be used
    var $filetypes = array('hp_6_jcloze_html');
    var $js_object_type = 'JClozeDropDown';

    // constructor function
    function quizport_output_hp_6_jcloze_html_dropdown(&$quiz) {
        parent::quizport_output_hp_6_jcloze_html($quiz);

        // replace standard jcloze.js with dropdown.js
        $this->javascripts = preg_grep('/jcloze.js/', $this->javascripts, PREG_GREP_INVERT);
        array_push($this->javascripts, 'mod/quizport/output/hp/6/jcloze/dropdown.js');
    }

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'Show_Solution,Build_GapText';
        return $names;
    }

    function fix_bodycontent() {
        $this->fix_bodycontent_rottmeier(true);
    }
}
?>