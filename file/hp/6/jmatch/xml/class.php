<?php
class quizport_file_hp_6_jmatch_xml extends quizport_file_hp_6_jmatch {
    var $best_outputformat = 'hp_6_jmatch_xml_v6_plus';

    function is_quizfile() {
        if (preg_match('/\.jmt$/', $this->filename)) {
            return $this;
        } else {
            return false;
        }
    }
}
?>