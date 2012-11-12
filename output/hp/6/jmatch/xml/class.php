<?php
class quizport_output_hp_6_jmatch_xml extends quizport_output_hp_6_jmatch {
    // source file types with which this output format can be used
    var $filetypes = array('hp_6_jmatch_xml');

    // constructor function
    function quizport_output_hp_6_jmatch_xml(&$quiz) {
        parent::quizport_output_hp_6_jmatch($quiz);
    }
}
?>