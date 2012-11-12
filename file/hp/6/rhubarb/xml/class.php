<?php
class quizport_file_hp_6_rhubarb_xml extends quizport_file_hp_6_rhubarb {
    function is_quizfile() {
        if (preg_match('/\.rhb$/', $this->filename)) {
            return $this;
        } else {
            return false;
        }
    }
}
?>