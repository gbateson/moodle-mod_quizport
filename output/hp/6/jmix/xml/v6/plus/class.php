<?php
class quizport_output_hp_6_jmix_xml_v6_plus extends quizport_output_hp_6_jmix_xml_v6 {
    var $templatefile = 'djmix6.ht_';

    function fix_bodycontent() {
        $this->fix_bodycontent_DragAndDrop();
    }
}
?>