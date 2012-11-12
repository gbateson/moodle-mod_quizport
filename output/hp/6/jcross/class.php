<?php
class quizport_output_hp_6_jcross extends quizport_output_hp_6 {
    var $icon = 'pix/f/jcw.gif';
    var $js_object_type = 'JCross';

    var $templatefile = 'jcross6.ht_';
    var $templatestrings = 'PreloadImageList|ShowHideClueList';

    // Glossary autolinking settings
    var $headcontent_strings = 'Feedback|AcrossCaption|DownCaption|Correct|Incorrect|GiveHint|YourScoreIs';
    var $headcontent_arrays = '';

    var $response_text_fields = array(
        'correct', 'wrong' // remove: ignored
    );

    var $response_num_fields = array(
        'hints', 'clues', 'checks' // remove: score, weighting
    );

    // constructor function
    function quizport_output_hp_6_jcross(&$quiz) {
        parent::quizport_output_hp_6($quiz);
        array_push($this->javascripts, 'mod/quizport/output/hp/6/jcross/jcross.js');
    }

    function filter_text_headcontent_search_array() {
        return ''; // disable search for array names
    }

    function fix_headcontent() {
        // switch off auto complete on answer text boxes
        $search = '/(?<=<form method="post" action="" onsubmit="return false;")(?=>)/';
        $replace = ' autocomplete="off"';
        $this->headcontent = preg_replace($search, $replace, $this->headcontent, 1);

        parent::fix_headcontent();
    }

    function fix_bodycontent() {
        // we must add a false return value to clue links in order not to trigger the onbeforeunload event handler
        $search = '/(?<='.'<a href="javascript:void\(0\);" class="GridNum" onclick="'.')'.'ShowClue\([^)]*\)'.'(?='.'">'.')/';
        $replace = '\\0'.'; return false;';
        $this->bodycontent = preg_replace($search, $replace, $this->bodycontent);

        parent::fix_bodycontent();
    }

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'TypeChars,ShowHint,ShowClue,CheckAnswers';
        return $names;
    }

    function fix_js_Finish(&$str, $start, $length) {
        $name = 'Finish';

        // remove the first occurrence of this function
        $this->remove_js_function($str, $start, $length, $name);

        // the JCross template file, jcross6.js_, contains an duplicate version
        // of the Finish() function, so for completeness we remove that as well

        list($start, $finish) = $this->locate_js_function($name, $str);
        if ($finish) {
            // remove the second occurrence of this function
            $this->remove_js_function($str, $start, ($finish - $start), $name);
        }

        // remove all delayed calls to this function
        // don't put this into hp/6/class.php because it breaks JQuiz !!
        //$search = "/\s*"."setTimeout\('$name\([^)]*\)', .*?\);/s";
        //$str = preg_replace($search, '', $str);
    }

    function fix_js_TypeChars_init() {
        return ''
            ."	if (CurrentBox && (CurrentBox.parentNode==null || CurrentBox.parentNode.parentNode==null)) {\n"
            ."		CurrentBox = null;\n"
            ."	}\n"
            ."	if (CurrentBox==null) {\n"
            ."		var ClueEntry = document.getElementById('ClueEntry');\n"
            ."		if (ClueEntry) {\n"
            ."			var InputTags = ClueEntry.getElementsByTagName('input');\n"
            ."			if (InputTags && InputTags.length) {\n"
            ."				CurrentBox = InputTags[0];\n"
            ."			}\n"
            ."			InputTags = null;\n"
            ."		}\n"
            ."		ClueEntry = null;\n"
            ."	}\n"
        ;
    }

    function fix_js_TypeChars_obj() {
        return 'CurrentBox';
    }

    function fix_js_ShowHint(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // intercept Hints
        if ($pos = strrpos($substr, '}')) {
            $append = "\n"
                ."	if (OutString.length) {\n"
                ."		// intercept this Hint\n"
                ."		HP.onclickHint(HP.getQuestionName(ClueNum, (Across ? 'A' : 'D')));\n"
                ."	}\n"
            ;
            $substr = substr_replace($substr, $append, $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_ShowClue(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // intercept Clues
        if ($pos = strpos($substr, '{')) {
            $insert = "\n"
                ."	// intercept this Clue\n"
                ."	if(document.getElementById('Clue_A_' + ClueNum)) {\n"
                ."		HP.onclickClue(HP.getQuestionName(ClueNum, 'A'));\n"
                ."	}\n"
                ."	if(document.getElementById('Clue_D_' + ClueNum)) {\n"
                ."		HP.onclickClue(HP.getQuestionName(ClueNum, 'D'));\n"
                ."	}\n"
            ;
            $substr = substr_replace($substr, $insert, $pos+1, 0);
        }

        // stretch the canvas vertically down to cover the content, if any
        if ($pos = strrpos($substr, '}')) {
            $substr = substr_replace($substr, '	StretchCanvasToCoverContent(true);'."\n", $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function get_stop_function_name() {
        return 'CheckAnswers';
    }
}
?>