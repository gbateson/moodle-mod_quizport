<?php
class quizport_output_hp_6_sequitur extends quizport_output_hp_6 {
    var $js_object_type = 'Sequitur';

    var $templatefile = 'sequitur6.ht_';
    var $templatestrings = 'PreloadImageList|SegmentsArray';

    // Glossary autolinking settings
    var $headcontent_strings = 'CorrectIndicator|IncorrectIndicator|YourScoreIs|strTimesUp';
    var $headcontent_arrays = 'Segments';

    // TexToys do not have a SubmissionTimeout variable
    var $hasSubmissionTimeout = false;

    var $response_text_fields = array(
        'correct', 'wrong' // remove: ignored
    );

    var $response_num_fields = array(
        'checks' // remove: score, weighting, hints, clues
    );

    // constructor function
    function quizport_output_hp_6_sequitur(&$quiz) {
        parent::quizport_output_hp_6($quiz);
        array_push($this->javascripts, 'mod/quizport/output/hp/6/sequitur/sequitur.js');
    }

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'CheckAnswer,TimesUp';
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

    function fix_js_CalculateScore(&$str, $start, $length) {
        // original function was simply this:
        // return Math.floor(100*ScoredPoints/TotalPoints);
        $substr = ''
            ."function CalculateScore(){\n"
            ."	if (typeof(window.TotalPointsAvailable)=='undefined') {\n"
            ."\n"
            ."		// initialize TotalPointsAvailable\n"
            ."		window.TotalPointsAvailable = 0;\n"
            ."\n"
            ."		// add points for questions with complete number of distractors\n"
            ."		TotalPointsAvailable += (TotalSegments - NumberOfOptions) * (NumberOfOptions - 1);\n"
            ."\n"
            ."		// add points for questions with less than the total number of distractors\n"
            ."		TotalPointsAvailable += (NumberOfOptions - 1) * NumberOfOptions / 2;\n"
            ."	}\n"
            ."\n"
            ."	if (TotalPointsAvailable==0) {\n"
            ."		return 0;\n"
            ."	} else {\n"
            ."		return Math.floor(100*ScoredPoints/TotalPointsAvailable);\n"
            ."	}\n"
            ."}"
        ;
        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_CheckAnswer(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // add extra argument to this function, so it can be called from stop button
        if ($pos = strpos($substr, ')')) {
            $substr = substr_replace($substr, ', ForceQuizStatus', $pos, 0);
        }

        // allow for Btn being null (as it is when called from stop button)
        if ($pos = strpos($substr, 'Btn.innerHTML == IncorrectIndicator')) {
            $substr = substr_replace($substr, 'Btn && ', $pos, 0);
        }
        $search = 'else{';
        if ($pos = $this->strrpos($substr, $search)) {
            $substr = substr_replace($substr, 'else if (Btn){', $pos, strlen($search));
        }

        // intercept checks
        if ($pos = strpos($substr, '{')) {
            $insert = "\n"
                ."	if (CurrentNumber!=TotalSegments && !AllDone && Btn && Btn.innerHTML!=IncorrectIndicator){\n"
                ."		HP.onclickCheck(Chosen);\n"
                ."	}"
            ;
            $substr = substr_replace($substr, $insert, $pos+1, 0);
        }

        // set quiz status
        if ($pos = strpos($substr, 'if (CurrentCorrect == Chosen)')) {
            $insert = ''
                ."if (CurrentCorrect==Chosen && CurrentNumber>=(TotalSegments-2)){\n"
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
        return '0,null,'.QUIZPORT_STATUS_ABANDONED;
    }
}
?>