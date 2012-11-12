<?php
class quizport_output_hp_6_jmix_html extends quizport_output_hp_6_jmix {
    // source file types with which this output format can be used
    var $filetypes = array('hp_6_jmix_html');

    function fix_bodycontent() {
        $this->fix_bodycontent_DragAndDrop();
    }
}
?>