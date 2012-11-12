<?php
class quizport_output_hp_6_jquiz extends quizport_output_hp_6 {
    var $icon = 'pix/f/jqz.gif';
    var $js_object_type = 'JQuiz';

    var $templatefile = 'jquiz6.ht_';
    var $templatestrings = 'PreloadImageList|QsToShow';

    // Glossary autolinking settings
    var $headcontent_strings = 'CorrectIndicator|IncorrectIndicator|YourScoreIs|CorrectFirstTime|DefaultRight|DefaultWrong|ShowAllQuestionsCaption|ShowOneByOneCaption|I';
    var $headcontent_arrays = 'I';

    var $response_num_fields = array(
        'score', 'weighting', 'hints', 'checks' // remove: clues
    );

    // constructor function
    function quizport_output_hp_6_jquiz(&$quiz) {
        parent::quizport_output_hp_6($quiz);
        array_push($this->javascripts, 'mod/quizport/output/hp/6/jquiz/jquiz.js');
    }


    function fix_bodycontent() {
        // switch off auto complete on short answer text boxes
        $search = '/<div class="ShortAnswer"[^>]*><form([^>]*)>/';
        if (preg_match_all($search, $this->bodycontent, $matches, PREG_OFFSET_CAPTURE)) {
            $i_max = count($matches[0]) - 1;
            for ($i=$i_max; $i>=0; $i--) {
                list($match, $start) = $matches[1][$i];
                if (strpos($match, 'autocomplete')===false) {
                    $start += strlen($match);
                    $this->bodycontent = substr_replace($this->bodycontent, ' autocomplete="off"', $start, 0);
                }
            }
        }
        parent::fix_bodycontent();
    }

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'TypeChars,ShowHint,ShowAnswers,ChangeQ,ShowHideQuestions,CheckMCAnswer,CheckMultiSelAnswer,CheckShortAnswer,CheckFinished,SwitchHybridDisplay,SetUpQuestions,CalculateOverallScore';
        return $names;
    }

    function fix_js_TypeChars_obj() {
        return 'CurrBox';
    }

    function fix_js_ChangeQ(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // stretch the canvas vertically down to cover the reading, if any
        if ($pos = strrpos($substr, '}')) {
            $substr = substr_replace($substr, '	StretchCanvasToCoverContent(true);'."\n", $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_ShowHideQuestions(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // hide/show bottom border of questions (override class="QuizQuestion")
        $n = "\n\t\t\t\t";
        if ($pos = strpos($substr, "QArray[i].style.display = '';")) {
            $substr = substr_replace($substr, 'if ((i+1)<QArray.length){'.$n."\t"."QArray[i].style.borderWidth = '';".$n.'}'.$n, $pos, 0);
        }
        if ($pos = strpos($substr, "if (i != CurrQNum){")) {
            $substr = substr_replace($substr, "QArray[i].style.borderWidth = '0px';".$n, $pos, 0);
        }

        // stretch the canvas vertically down to cover the reading, if any
        if ($pos = strrpos($substr, '}')) {
            $substr = substr_replace($substr, '	StretchCanvasToCoverContent(true);'."\n", $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_SetUpQuestions(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // catch FF errors due to invalid XHTML syntax (e.g. inclosed <font> tags)
        $search = "QList.push(Qs.removeChild(Qs.getElementsByTagName('li')[0]));";
        if ($pos = $this->strrpos($substr, $search)) {
            $replace = ''
                ."try {\n\t\t"
                ."	".$search."\n\t\t"
                ."} catch(err) {\n\t\t"
                ."	alert('Sorry, SetUpQuestions() failed.'+'\\n'+'Perhaps the XHTML of this quiz file is not valid?');\n\t\t"
                ."	return;\n\t\t"
                ."}"
            ;
            $substr = substr_replace($substr, $replace, $pos, strlen($search));
        }

        // hide bottom border of question (override class="QuizQuestion")
        if ($pos = strpos($substr, "Qs.appendChild(QList[i]);")) {
            $substr = substr_replace($substr, "QList[i].style.borderWidth = '0px';"."\n\t\t", $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_SwitchHybridDisplay(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // stretch the canvas vertically down to cover the reading, if any
        if ($pos = strrpos($substr, '}')) {
            $substr = substr_replace($substr, '	StretchCanvasToCoverContent(true);'."\n", $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_ShowHint(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // intercept Hints
        if ($pos = strpos($substr, '{')) {
            $insert = "\n"
                ."	// intercept this Hint\n"
                ."	HP.onclickHint(QNum);\n"
            ;
            $substr = substr_replace($substr, $insert, $pos+1, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }
    function fix_js_ShowAnswers(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // intercept Clues
        if ($pos = strpos($substr, '{')) {
            $insert = "\n"
                ."	// intercept this Clue\n"
                ."	if (State[QNum][0]<1) HP.onclickClue(QNum);\n"
            ;
            $substr = substr_replace($substr, $insert, $pos+1, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }
    function fix_js_CheckMCAnswer(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // intercept Check
        if ($pos = strpos($substr, '{')) {
            $insert = "\n"
                ."	// intercept this Check\n"
                ."	if(!Finished && State[QNum].length && State[QNum][0]<0) {\n"
                ."		var args = new Array(QNum, I[QNum][3][ANum][0]);\n"
                ."		HP.onclickCheck(args);\n"
                ."	}\n"
            ;
            $substr = substr_replace($substr, $insert, $pos+1, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }
    function fix_js_CheckShortAnswer(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // intercept Check
        if ($pos = strpos($substr, '{')) {
            $insert = "\n"
                ."	// intercept this Check\n"
                ."	if(!Finished && State[QNum].length && State[QNum][0]<0) {\n"
                ."		var obj = document.getElementById('Q_'+QNum+'_Guess');\n"
                ."		var args = new Array(QNum, obj.value);\n"
                ."		HP.onclickCheck(args);\n"
                ."	}\n"
            ;
            $substr = substr_replace($substr, $insert, $pos+1, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }
    function fix_js_CheckMultiSelAnswer(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // intercept Check
        if ($pos = strpos($substr, '{')) {
            $insert = "\n"
                ."	// intercept this Check\n"
                ."	if(!Finished && State[QNum].length && State[QNum][0]<0) {\n"
                ."		var g='';\n"
                ."		for (var ANum=0; ANum<I[QNum][3].length; ANum++){\n"
                ."			var obj = document.getElementById('Q_'+QNum+'_'+ANum+'_Chk');\n"
                ."			if (obj.checked) g += (g ? '+' : '') + I[QNum][3][ANum][0];\n"
                ."		}\n"
                ."		var args = new Array(QNum, g);\n"
                ."		HP.onclickCheck(args);\n"
                ."	}\n"
            ;
            $substr = substr_replace($substr, $insert, $pos+1, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }
    function fix_js_CheckFinished(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // remove creation of HotPotNet results XML and call to Finish()
		$search = '/\s*'.'Detail = [^;]*?;'.'.*?'.'setTimeout[^;]*;'.'/s';
        $substr = preg_replace($search, '', $substr, 1);

        // add other changes as per CheckAnswers in other type of HP quiz
        $this->fix_js_CheckAnswers($substr, 0, strlen($substr));

        $str = substr_replace($str, $substr, $start, $length);
    }
    function fix_js_CalculateOverallScore(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        $substr = preg_replace('/(\s*)var TotalScore = 0;/s', '\\0\\1'.'var TotalCount = 0;', $substr, 1);
        $substr = preg_replace('/(\s*)TotalScore \+= [^;]*;/s', '\\0\\1'.'TotalCount ++;', $substr, 1);
        $substr = preg_replace('/(\s*)\}\s*else\s*\{/s', '\\1} else if (TotalCount==0) {\\1'."\t".'Score = 0;'.'\\0', $substr, 1);

        $str = substr_replace($str, $substr, $start, $length);
    }
    function get_stop_function_name() {
        return 'CheckFinished';
    }
    function get_stop_function_intercept() {
        // intercept is not required in the giveup function of JQuiz
        // because the checks are intercepted by CheckFinished (see above)
        return '';
    }
    function get_stop_function_search() {
        return '/\s*if \((AllDone) == true\)({.*?WriteToInstructions[^;]*;).*?\w+ = true;\s*}\s*/s';
    }
}
?>