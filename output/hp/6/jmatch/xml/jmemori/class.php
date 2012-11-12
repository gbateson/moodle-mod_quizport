<?php
class quizport_output_hp_6_jmatch_xml_jmemori extends quizport_output_hp_6_jmatch_xml {
    var $filetypes = array('hp_6_jmatch_xml');
    var $js_object_type = 'JMemori';
    var $templatefile = 'djmatch6.ht_';

    // constructor function
    function quizport_output_hp_6_jmatch_xml_jmemori(&$quiz) {
        parent::quizport_output_hp_6_jmatch_xml($quiz);

        // prepend templates for this output format
        array_unshift($this->templatesfolders, 'mod/quizport/output/hp/6/jmatch/xml/jmemori/templates');

        // replace standard jcloze.js with jmemori.js
        $this->javascripts = preg_grep('/jmatch.js/', $this->javascripts, PREG_GREP_INVERT);
        array_push($this->javascripts, 'mod/quizport/output/hp/6/jmatch/jmemori.js');
    }

    function fix_headcontent() {
        $this->fix_headcontent_rottmeier('jmemori');
    }

    function fix_bodycontent() {
        $this->fix_bodycontent_rottmeier();
        parent::fix_bodycontent();
    }

    function fix_title() {
        $this->fix_title_rottmeier_JMemori();
    }

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'ShowSolution,CheckPair,WriteFeedback';
        return $names;
    }

    function fix_js_WriteFeedback(&$str, $start, $length) {
        $this->fix_js_WriteFeedback_JMemori($str, $start, $length);
    }

    function fix_js_HideFeedback(&$str, $start, $length) {
        $this->fix_js_HideFeedback_JMemori($str, $start, $length);
    }

    function fix_js_ShowSolution(&$str, $start, $length) {
        $this->fix_js_ShowSolution_JMemori($str, $start, $length);
    }

    function fix_js_CheckPair(&$str, $start, $length) {
        $this->fix_js_CheckPair_JMemori($str, $start, $length);
    }

    function get_stop_function_name() {
        return 'CheckPair';
    }

    function get_stop_function_search() {
        return '/\s*if \((Pairs == F\.length)\)({.*?)setTimeout.*?}/s';
    }

    function get_stop_function_args() {
        // the arguments required by CheckPair
        return '-1,'.QUIZPORT_STATUS_ABANDONED;
    }

    function get_stop_function_intercept() {
        return "\n"
            ."	// intercept this Check\n"
            ."	if (id>=0) HP.onclickCheck(id);\n"
        ;
    }
}
?>