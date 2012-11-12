<?php

class quizport_output_html_xhtml extends quizport_output_html {
    // source file types with which this output format can be used
    var $filetypes = array('html_xhtml');

    // constructor function
    function quizport_output_html_xhtml(&$quiz) {
        parent::quizport_output_html($quiz);
    }
}
?>