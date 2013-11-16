<?php
class quizport_output_hp_6_jmatch_xml_sort extends quizport_output_hp_6_jmatch_xml {
    var $filetypes = array('hp_6_jmatch_xml');
    var $templatefile = 'djmatch6.ht_';

    // constructor function
    function quizport_output_hp_6_jmatch_xml_sort(&$quiz) {
        parent::quizport_output_hp_6_jmatch_xml($quiz);

        // prepend templates for this output format
        array_unshift($this->templatesfolders, 'mod/quizport/output/hp/6/jmatch/xml/sort/templates');
    }
}
?>