<?php
class quizport_output_hp_6_sequitur_xml_incremental extends quizport_output_hp_6_sequitur_xml {

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'CalculateScore';
        return $names;
    }
}
?>