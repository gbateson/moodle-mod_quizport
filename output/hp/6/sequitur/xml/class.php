<?php
class quizport_output_hp_6_sequitur_xml extends quizport_output_hp_6_sequitur {
    // source file types with which this output format can be used
    var $filetypes = array('hp_6_sequitur_xml');

    function expand_JSSequitur6() {
        return $this->expand_template('sequitur6.js_');
    }
    function expand_NumberOfOptions() {
        $tags = $this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',number-of-options';
        return $this->source->xml_value_int($tags);
    }
    function expand_PartText() {
        $tags = $this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',show-part-text';
        return $this->source->xml_value($tags);
    }
    function expand_Solution() {
        $tags = $this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',include-solution';
        return $this->source->xml_value_int($tags);
    }
    function expand_SolutionCaption() {
        $tags = $this->source->hbs_software.'-config-file,global,solution-caption';
        return $this->source->xml_value($tags);
    }
    function expand_Score() {
        $tags = $this->source->hbs_software.'-config-file,global,your-score-is';
        return $this->source->xml_value_js($tags);
    }
    function expand_WholeText() {
        $tags = $this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',show-whole-text';
        return $this->source->xml_value($tags);
    }

    function expand_SegmentsArray() {
        // we might have empty segments, so we need to first
        // find out how many segments there are and then go
        // through them all, ignoring the empty ones

        $i_max = 0;
        if ($segments = $this->source->xml_value('data,segments')) {
            if (isset($segments['segment'])) {
                $i_max = count($segments['segment']);
            }
        }
        unset($segments);

        $str = '';
        $tags = 'data,segments,segment';

        $i =0 ;
        $ii =0 ;
        while ($i<$i_max) {
            if ($segment = $this->source->xml_value_js($tags, "[$i]['#']")) {
                $str .= "Segments[$ii]='$segment';\n";
                $ii++;
            }
            $i++;
        }

        return $str;
    }

    function expand_StyleSheet() {
        return $this->expand_template('tt3.cs_');
    }
}
?>