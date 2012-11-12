<?php
class quizport_output_hp_6_jcloze_xml_anctscan extends quizport_output_hp_6_jcloze_xml {
    // source file types with which this output format can be used
    var $filetypes = array('hp_6_jcloze_xml');
    var $js_object_type = 'JCloze_ANCT_Scan';

    // constructor function
    function quizport_output_hp_6_jcloze_xml_anctscan(&$quiz) {
        parent::quizport_output_hp_6_jcloze_xml($quiz);

        // prepend templates for this output format
        array_unshift($this->templatesfolders, 'mod/quizport/output/hp/6/jcloze/xml/anctscan/templates');

        // replace standard jcloze.js with dropdown.js
        $this->javascripts = preg_grep('/jcloze.js/', $this->javascripts, PREG_GREP_INVERT);
        array_push($this->javascripts, 'mod/quizport/output/hp/6/jcloze/anctscan.js');
    }

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'CheckExStatus,DownTime,TimesUp';
        return $names;
    }

    function fix_js_CheckExStatus(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // add changes as per CheckAnswers in other type of HP quiz
        $this->fix_js_CheckAnswers($substr, 0, $length);

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_DownTime(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        $substr = str_replace('TimesUp();', 'CheckExStatus(2);', $substr);

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_TimesUp(&$str, $start, $length) {
        $this->remove_js_function($str, $start, $length, 'TimesUp');
    }

    function get_stop_function_name() {
        return 'CheckExStatus';
    }

    function get_stop_function_search() {
        return '/\s*if \((ExStatus)\)({.*?)}/s';
    }

    function get_stop_function_intercept() {
        // do not add standard onclickCheck()
        return '';
    }

    function fix_bodycontent() {
        $this->fix_bodycontent_rottmeier(true);
    }
}
?>