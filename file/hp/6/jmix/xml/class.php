<?php
class quizport_file_hp_6_jmix_xml extends quizport_file_hp_6_jmix {
    var $best_outputformat = 'hp_6_jmix_xml_v6_plus';

    function is_quizfile() {
        if (preg_match('/\.jmx$/', $this->filename)) {
            return $this;
        } else {
            return false;
        }
    }
}
?>