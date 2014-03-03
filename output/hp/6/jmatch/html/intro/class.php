<?php
class quizport_output_hp_6_jmatch_html_intro extends quizport_output_hp_6_jmatch_html {
    // source file types with which this output format can be used
    var $filetypes = array('hp_6_jmatch_html_intro');

    // constructor function
    function quizport_output_hp_6_jmatch_html_intro(&$quiz) {
        parent::quizport_output_hp_6_jmatch($quiz);
    }

    function fix_headcontent() {
        //$this->fix_headcontent_DragAndDrop();
        $this->fix_headcontent_rottmeier('jintro');
    }

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'CheckAnswer,ShowDescription';
        return $names;
    }

    public function fix_js_StartUp(&$str, $start, $length)  {
        $substr = substr($str, $start, $length);
        parent::fix_js_StartUp($substr, 0, $length);

        // remove code that assigns event keypress/keydown handler
        $search = '/(else\s*)?if\s*\([^)]*\)\s*\{[^{}]*SuppressBackspace[^{}}]*\}\s*/s';
        $substr = preg_replace($search, '', $substr);

        $str = substr_replace($str, $substr, $start, $length);
    }

    public function fix_js_ShowDescription(&$str, $start, $length)  {
        $replace = ''
            ."function ShowDescription(evt, ElmNum){\n"
            ."	if (evt==null) {\n"
            ."		evt = window.event; // IE\n"
            ."	}\n"

            ."	var obj = document.getElementById('DivIntroPage');\n"
            ."	if (obj) {\n"

            // get max X and Y for this page
            ."		var pg = new PageDim();\n"
            ."		var maxX = (pg.Left + pg.W);\n"
            ."		var maxY = (pg.Top  + pg.H);\n"

            // get mouse position
            ."		if (evt.pageX || evt.pageY) {\n"
            ."			var posX = evt.pageX;\n"
            ."			var posY = evt.pageY;\n"
            ."		} else if (evt.clientX || evt.clientY) {\n"
            ."			var posX = evt.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;\n"
            ."			var posY = evt.clientY + document.body.scrollTop + document.documentElement.scrollTop;\n"
            ."		} else {\n"
            ."			var posX = 0;\n"
            ."			var posY = 0;\n"
            ."		}\n"

            // insert new description and make div visible
            ."		obj.innerHTML = D[ElmNum][0];\n"
            ."		obj.style.display = 'block';\n"

            // make sure posX and posY are within the display area
            ."		posX = Math.max(0, Math.min(posX + 12, maxX - getOffset(obj, 'Width')));\n"
            ."		posY = Math.max(0, Math.min(posY + 12, maxY - getOffset(obj, 'Height')));\n"

            // move the description div to (posX, posY)
            ."		setOffset(obj, 'Left', posX);\n"
            ."		setOffset(obj, 'Top', posY);\n"
            ."		obj.style.zIndex = ++topZ;\n"
            ."	}\n"
            ."}\n"
        ;
        $str = substr_replace($str, $replace, $start, $length);
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