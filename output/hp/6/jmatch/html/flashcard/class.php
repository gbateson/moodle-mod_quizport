<?php
class quizport_output_hp_6_jmatch_html_flashcard extends quizport_output_hp_6_jmatch_html {
    var $filetypes = array('hp_6_jmatch_html_flashcard');
    var $js_object_type = 'JMatchFlashcard';

    // constructor function
    function quizport_output_hp_6_jmatch_html_flashcard(&$quiz) {
        parent::quizport_output_hp_6_jmatch_html($quiz);

        // replace standard jmatch.js with flashcard.js
        $this->javascripts = preg_grep('/jmatch.js/', $this->javascripts, PREG_GREP_INVERT);
        array_push($this->javascripts, 'mod/quizport/output/hp/6/jmatch/flashcard.js');
    }

    function fix_js_StartUp_DragAndDrop(&$substr) {
        $this->fix_js_StartUp_DragAndDrop_Flashcard($substr);
    }

    function fix_mediafilter_onload_extra() {
        // automatically show first item
        return $this->fix_mediafilter_onload_extra_Flashcard();
    }

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'DeleteItem,ShowItem';
        return $names;
    }

    function fix_js_DeleteItem(&$str, $start, $length) {
        $this->fix_js_DeleteItem_Flashcard($str, $start, $length);
    }

    function fix_js_ShowItem(&$str, $start, $length) {
        $this->fix_js_ShowItem_Flashcard($str, $start, $length);
    }

    function get_stop_function_name() {
        return 'HP.onunload';
    }

    function get_stop_function_args() {
        return QUIZPORT_STATUS_COMPLETED;
    }
}
?>