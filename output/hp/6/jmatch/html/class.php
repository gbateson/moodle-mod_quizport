<?php
class quizport_output_hp_6_jmatch_html extends quizport_output_hp_6_jmatch {
    // source file types with which this output format can be used
    var $filetypes = array('hp_6_jmatch_html');

    // constructor function
    function quizport_output_hp_6_jmatch_html(&$quiz) {
        parent::quizport_output_hp_6_jmatch($quiz);
    }

    function fix_bodycontent() {
        $this->fix_bodycontent_DragAndDrop();
        parent::fix_bodycontent();
    }
}
?>