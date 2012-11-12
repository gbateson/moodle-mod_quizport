<?php
class quizport_output_hp_6_jmatch_xml_v6_plus extends quizport_output_hp_6_jmatch_xml_v6 {
    var $templatefile = 'djmatch6.ht_';

    // constructor function
    function quizport_output_hp_6_jmatch_xml_v6_plus(&$quiz) {
        parent::quizport_output_hp_6_jmatch_xml_v6($quiz);
    }

    function fix_bodycontent() {
        $this->fix_bodycontent_DragAndDrop();
        parent::fix_bodycontent();
    }
}
?>