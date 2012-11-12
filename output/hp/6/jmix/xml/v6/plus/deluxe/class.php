<?php
class quizport_output_hp_6_jmix_xml_v6_plus_deluxe extends quizport_output_hp_6_jmix_xml_v6_plus {

    // constructor function
    function quizport_output_hp_6_jmix_xml_v6_plus_deluxe(&$quiz) {
        parent::quizport_output_hp_6_jmix_xml_v6_plus($quiz);

        // prepend templates for this output format
        array_unshift($this->templatesfolders, 'mod/quizport/output/hp/6/jmix/xml/v6/plus/deluxe/templates');
    }

    function fix_bodycontent_DragAndDrop() {
        // user-string-1: prefix (optional)
        // user-string-2: suffix (optional)
        $prefix = trim($this->expand_UserDefined1());
        $suffix = trim($this->expand_UserDefined2());
        parent::fix_bodycontent_DragAndDrop($prefix, $suffix);
    }

     function fix_js_StartUp_DragAndDrop_DragArea(&$substr) {
        // fix LeftCol (=left side of drag area)
        $search = '/LeftColPos = [^;]+;/';
        $replace = "LeftColPos = getOffset(document.getElementById('CheckButtonDiv'),'Left') + 20;";
        $substr = preg_replace($search, $replace, $substr, 1);

        // fix DivWidth (=width of drag area)
        $search = '/DivWidth = [^;]+;/';
        $replace = "DivWidth = getOffset(document.getElementById('CheckButtonDiv'),'Width') - 40;";
        $substr = preg_replace($search, $replace, $substr, 1);

        // fix DragTop (=top side of drag area)
        $search = '/DragTop = [^;]+;/';
        $replace = "DragTop = getOffset(document.getElementById('CheckButtonDiv'),'Bottom') + 10;";
        $substr = preg_replace($search, $replace, $substr, 1);
    }

    function expand_SegmentArray() {
        // user-string-3: (optional)
        //   distractor words: words, delimited, by, commas, like, this
        //   phrases: (one phrase) [another phrase] {yet another phrase}
        if ($value = $this->expand_UserDefined3()) {
            if (preg_match('/^(\()|(\[)|(\{).*(?(1)\)|(?(2)\]|(?(3)\})))$/', $value)) {
                $search = '/\s*\\'.substr($value, -1).'\s*\\'.substr($value, 0, 1).'\s*/';
                $more_values = preg_split($search, substr($value, 1, -1));
            } else {
                $more_values = preg_split('/\s*,\s*/', trim($value));
            }
        } else {
            $more_values = array();
        }
        return parent::expand_SegmentArray($more_values);
    }
}
?>