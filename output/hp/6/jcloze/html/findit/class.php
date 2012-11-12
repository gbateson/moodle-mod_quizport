<?php
class quizport_output_hp_6_jcloze_html_findit extends quizport_output_hp_6_jcloze_html {
    // constructor function
    function quizport_output_hp_6_jcloze_html_findit(&$quiz) {
        parent::quizport_output_hp_6_jcloze_html($quiz);
    }

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'Markup_Text,CheckText,Build_GapText,ShowSolution,Get_WrongGapContent,TimesUp';
        return $names;
    }

    function get_stop_function_search() {
        return '/\s*if \((CheckExStatus\(\)) == true\)({.*?)setTimeout.*?}/s';
    }

    function fix_headcontent() {
        $this->fix_headcontent_rottmeier('findit');
    }

    function fix_bodycontent() {
        $this->fix_bodycontent_rottmeier(true);
    }
}
?>