<?php
class quizport_output_hp_6_jmatch_html_intro extends quizport_output_hp_6_jmatch_html {
    // source file types with which this output format can be used
    var $filetypes = array('hp_6_jmatch_html_intro');

    // constructor function
    function quizport_output_hp_6_jmatch_html_intro(&$quiz) {
        parent::quizport_output_hp_6_jmatch($quiz);
    }

    function fix_headcontent() {
        $this->fix_headcontent_rottmeier('jintro');
    }

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'CheckAnswer';
        return $names;
    }

    function fix_js_CheckAnswer(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // add extra argument to this function, so it can be called from stop button
        if ($pos = strpos($substr, ')')) {
            $substr = substr_replace($substr, 'ForceQuizStatus', $pos, 0);
        }

        // intercept checks
        if ($pos = strpos($substr, '{')) {
            $insert = "\n"
                ."	HP.onclickCheck();\n"
            ;
            $substr = substr_replace($substr, $insert, $pos+1, 0);
        }

        // set quiz status
        if ($pos = strpos($substr, 'if (TotalCorrect == F.length) {')) {
            $insert = ''
                ."if (TotalCorrect == F.length) {\n"
                ."		var QuizStatus = 4; // completed\n"
                ."	} else if (ForceQuizStatus){\n"
                ."		var QuizStatus = ForceQuizStatus; // 3=abandoned\n"
                ."	} else if (TimeOver){\n"
                ."		var QuizStatus = 2; // timed out\n"
                ."	} else {\n"
                ."		var QuizStatus = 1; // in progress\n"
                ."	}\n"
                ."	"
            ;
            $substr = substr_replace($substr, $insert, $pos, 0);
        }

        // remove call to Finish() function
        $substr = preg_replace('/\s*'.'setTimeout\(.*?\);/s', '', $substr);

        // remove call to WriteToInstructions() function
        $search = '/\s*'.'WriteToInstructions\(.*?\);/s';
        $substr = preg_replace($search, '', $substr);

        // remove superfluous if-block that contained WriteToInstructions()
        $search = '/\s*if \(\(is\.ie\)\&\&\(\!is\.mac\)\)\{\s*\}/s';
        $substr = preg_replace($search, '', $substr);

        // send results to Moodle, if necessary
        if ($pos = strrpos($substr, '}')) {
            if ($this->delay3==QUIZPORT_DELAY3_AFTEROK) {
                $flag = 1; // set form values only
            } else {
                $flag = 0; // set form values and send form
            }
            $insert = "\n"
                ."	if (QuizStatus > 1) {\n"
                ."		TimeOver = true;\n"
                ."		Locked = true;\n"
                ."		Finished = true;\n"
                ."	}\n"
                ."	if (Finished || HP.sendallclicks){\n"
                ."		if (ForceQuizStatus || QuizStatus==1){\n"
                ."			// send results immediately\n"
                ."			HP.onunload(QuizStatus);\n"
                ."		} else {\n"
                ."			// send results after delay\n"
                ."			setTimeout('HP.onunload('+QuizStatus+',$flag)', SubmissionTimeout);\n"
                ."		}\n"
                ."	}\n"
            ;
            $substr = substr_replace($substr, $insert, $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function get_stop_function_name() {
        return 'CheckAnswer';
    }

    function get_stop_function_args() {
        return QUIZPORT_STATUS_ABANDONED;
    }

    /* ================================================ **
    HP6:
        GetViewportHeight,PageDim,TrimString,StartUp,GetUserName,
        ShowMessage,HideFeedback,SendResults,Finish,WriteToInstructions,
        ShowSpecialReadingForQuestion,
    JMatch:
        CheckAnswers,beginDrag
    JMatch-intro:
        StartUpInfo(?),DisplayIntroPage(?),BuildIntroPage(?)
    ** ================================================ */
}
?>