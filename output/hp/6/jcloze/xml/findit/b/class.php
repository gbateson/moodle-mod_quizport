<?php
class quizport_output_hp_6_jcloze_xml_findit_b extends quizport_output_hp_6_jcloze_xml_findit {
    // source file types with which this output format can be used
    var $filetypes = array('hp_6_jcloze_xml');
    var $js_object_type = 'JClozeFindItB';

    // constructor function
    function quizport_output_hp_6_jcloze_xml_findit_b(&$quiz) {
        parent::quizport_output_hp_6_jcloze_xml_findit($quiz);

        // prepend templates for this output format
        array_unshift($this->templatesfolders, 'mod/quizport/output/hp/6/jcloze/xml/findit/b/templates');

        // replace standard jcloze.js with findit.b.js
        $this->javascripts = preg_grep('/jcloze.js/', $this->javascripts, PREG_GREP_INVERT);
        array_push($this->javascripts, 'mod/quizport/output/hp/6/jcloze/findit.b.js');
    }

    function fix_js_CheckAnswers(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // do several search and replace actions at once
        $search = array(
            '/if \(NumOfVisibleGaps < 1\)\{return;\}/',
            "/(\s+)Output = '';/s",
            '/Output \+= MissingMistakes \+ Get_NumMissingErr\(\);/',
            '/CalculateScore\(\);/' // last occurrence
        );
        $replace = array(
            'if (NumOfVisibleGaps){',
            '\\1}\\0',
            'if (NumOfVisibleGaps) \\0',
            'if (NumOfVisibleGaps) \\0'
        );
        $substr = preg_replace($search, $replace, $substr, 1);

        parent::fix_js_CheckAnswers($substr, 0, strlen($substr));

        $str = substr_replace($str, $substr, $start, $length);
    }
}
?>