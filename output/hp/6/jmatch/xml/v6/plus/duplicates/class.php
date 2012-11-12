<?php
class quizport_output_hp_6_jmatch_xml_v6_plus_duplicates extends quizport_output_hp_6_jmatch_xml_v6_plus {

    // constructor function
    function quizport_output_hp_6_jmatch_xml_v6_plus_duplicates(&$quiz) {
        parent::quizport_output_hp_6_jmatch_xml_v6_plus($quiz);

        // prepend templates for this output format
        array_unshift($this->templatesfolders, 'mod/quizport/output/hp/6/jmatch/xml/v6/plus/duplicates/templates');
    }

    function expand_DragArray() {
        $this->set_jmatch_items();
        $str = '';

        // simple array to map item keys and texts
        $texts = array();
        foreach ($this->l_items as $i=>$item) {
            $key = $item['key'];
            if (empty($this->r_items[$i]['fixed'])) {
                $texts[$key] = $item['text'];
            }
        }

        // array to map drag item keys to fixed items key(s)
        $keys = array();
        foreach ($this->l_items as $i=>$item) {
            $key = $item['key'];
            if (empty($this->r_items[$i]['fixed'])) {
                $texts_keys = array_keys($texts, $item['text']);
                foreach ($texts_keys as $i => $texts_key) {
                    $texts_keys[$i] = $texts_key + 1;
                }
                if (count($texts_keys)==1) {
                    $keys[$key] = $texts_keys[0];
                } else {
                    $keys[$key] = 'new Array('.implode(',', $texts_keys).')';
                }
            } else {
                // drag item is fixed
                $keys[$key] = $key + 1;
            }
        }
        unset($texts);

        foreach ($this->r_items as $i=>$item) {
            $str .= "D[$i] = new Array();\n";
            $str .= "D[$i][0] = '".$this->source->js_value_safe($item['text'], true)."';\n";
            $str .= "D[$i][1] = ".$keys[$item['key']].";\n";
            $str .= "D[$i][2] = ".$item['fixed'].";\n";
        }
        return $str;
    }
}
?>