<?php
class quizport_output_hp_6_rhubarb extends quizport_output_hp_6 {
    var $js_object_type = 'Rhubarb';

    var $templatefile = 'rhubarb6.ht_';
    var $templatestrings = 'PreloadImageList|FreeWordsArray|WordsArray';

    // Glossary autolinking settings
    var $headcontent_strings = 'strFinished|YourScoreIs|strTimesUp';
    var $headcontent_arrays = 'Words';

    // TexToys do not have a SubmissionTimeout variable
    var $hasSubmissionTimeout = false;

    // constructor function
    function quizport_output_hp_6_rhubarb(&$quiz) {
        parent::quizport_output_hp_6($quiz);
        array_push($this->javascripts, 'mod/quizport/output/hp/6/rhubarb/rhubarb.js');
    }

    function fix_bodycontent() {
        // switch off auto complete on Rhubarb text boxes
        $search = '/<form id="Rhubarb"([^>]*)>/';
        if (preg_match($search, $this->bodycontent, $matches, PREG_OFFSET_CAPTURE)) {
            $match = $matches[1][0];
            $start = $matches[1][1];
            if (strpos($match, 'autocomplete')===false) {
                $this->bodycontent = substr_replace($this->bodycontent, $match.' autocomplete="off"', $start, strlen($match));
            }
        }
        parent::fix_bodycontent();
    }


    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'TypeChars,Hint,CheckWord,CheckFinished,TimesUp';
        return $names;
    }

    function fix_js_TimesUp(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        if ($pos = strpos($substr, '	ShowMessage')) {
            if ($this->delay3==QUIZPORT_DELAY3_AFTEROK) {
                $flag = 1; // set form values only
            } else {
                $flag = 0; // set form values and send form
            }
            $insert = ''
                ."	Finished = true;\n"
                ."	HP.onunload(".QUIZPORT_STATUS_TIMEDOUT.",$flag);\n"
            ;
            $substr = substr_replace($substr, $insert, $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_TypeChars_init() {
        return "	var obj = document.getElementById('Guess');\n";
    }

    function fix_js_TypeChars_obj() {
        return 'obj';
    }

    function fix_js_Hint(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // intercept Hints
        if ($pos = strpos($substr, '{')) {
            $insert = "\n"
                ."	if (! AllDone) {\n"
                ."		// intercept this Hint\n"
                ."		HP.onclickHint(0);\n"
                ."	}\n"
            ;
            $substr = substr_replace($substr, $insert, $pos+1, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_CheckWord(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // intercept Hints
        if ($pos = strpos($substr, '{')) {
            $insert = "\n"
                ."	if (! AllDone && InputWord.length) {\n"
                ."		// intercept this Check\n"
                ."		HP.onclickCheck(InputWord);\n"
                ."	}"
            ;
            $substr = substr_replace($substr, $insert, $pos+1, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_CheckFinished(&$str, $start, $length) {
        parent::fix_js_CheckAnswers($str, $start, $length);
    }

    function get_stop_function_intercept() {
        // intercept is not required in the giveup function of JQuiz
        // because the checks are intercepted by CheckFinished (see above)
        return '';
    }

    function get_stop_function_name() {
        return 'CheckFinished';
    }

    function get_stop_function_args() {
        return QUIZPORT_STATUS_ABANDONED;
    }

    function get_stop_function_search() {
        return '/\s*if \((Done) == true\)(\{.*?)\}/s';
    }
}
?>