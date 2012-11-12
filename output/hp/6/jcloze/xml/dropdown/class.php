<?php
class quizport_output_hp_6_jcloze_xml_dropdown extends quizport_output_hp_6_jcloze_xml {
    // source file types with which this output format can be used
    var $filetypes = array('hp_6_jcloze_xml');
    var $js_object_type = 'JClozeDropDown';

    // constructor function
    function quizport_output_hp_6_jcloze_xml_dropdown(&$quiz) {
        parent::quizport_output_hp_6_jcloze_xml($quiz);

        // prepend templates for this output format
        array_unshift($this->templatesfolders, 'mod/quizport/output/hp/6/jcloze/xml/dropdown/templates');

        // replace standard jcloze.js with dropdown.js
        $this->javascripts = preg_grep('/jcloze.js/', $this->javascripts, PREG_GREP_INVERT);
        array_push($this->javascripts, 'mod/quizport/output/hp/6/jcloze/dropdown.js');
    }

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'Show_Solution,Build_GapText';
        return $names;
    }

    function fix_bodycontent() {
        $this->fix_bodycontent_rottmeier(true);
    }

    function fix_js_Build_GapText(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        parent::fix_js_Build_GapText($substr, 0, strlen($substr));

        if ($this->expand_CaseSensitive()) {
            $search = 'SelectorList = Shuffle(SelectorList);';
            $replace = 'SelectorList = AlphabeticalSort(SelectorList, x);';
            $substr = str_replace($search, $replace, $substr);
            $substr .= "\n"
                ."function AlphabeticalSort(SelectorList, x) {\n"
                ."	if (MakeIndividualDropdowns) {\n"
                ."		var y_max = I[x][1].length - 1;\n"
                ."	} else {\n"
                ."		var y_max = I.length - 1;\n"
                ."	}\n"
                ."	var sorted = false;\n"
                ."	while (! sorted) {\n"
                ."		sorted = true;\n"
                ."		for (var y=0; y<y_max; y++) {\n"
                ."			var y1 = SelectorList[y];\n"
                ."			var y2 = SelectorList[y + 1];\n"
                ."			if (MakeIndividualDropdowns) {\n"
                ."				var s1 = I[x][1][y1][0].toLowerCase();\n"
                ."				var s2 = I[x][1][y2][0].toLowerCase();\n"
                ."			} else {\n"
                ."				var s1 = I[y1][1][0][0].toLowerCase();\n"
                ."				var s2 = I[y2][1][0][0].toLowerCase();\n"
                ."			}\n"
                ."			if (s1 > s2) {\n"
                ."				sorted = false;\n"
                ."				SelectorList[y] = y2;\n"
                ."				SelectorList[y + 1] = y1;\n"
                ."			}\n"
                ."		}\n"
                ."	}\n"
                ."	return SelectorList;\n"
                ."}\n"
            ;
        }

        $str = substr_replace($str, $substr, $start, $length);
    }
}
?>