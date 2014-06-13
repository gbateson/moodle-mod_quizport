<?php
class quizport_output_hp_6_jmix extends quizport_output_hp_6 {
    var $icon = 'pix/f/jmx.gif';
    var $js_object_type = 'JMix';

    var $templatefile = 'jmix6.ht_';
    var $templatestrings = 'PreloadImageList|SegmentArray|AnswerArray';

    // Glossary autolinking settings
    var $headcontent_strings = 'CorrectResponse|IncorrectResponse|ThisMuchCorrect|TheseAnswersToo|YourScoreIs|NextCorrect|Segments';
    var $headcontent_arrays = '';

    var $response_num_fields = array(
        'hints', 'checks' // remove: score, weighting, clues
    );

    // constructor function
    function quizport_output_hp_6_jmix(&$quiz) {
        parent::quizport_output_hp_6($quiz);
        array_push($this->javascripts, 'mod/quizport/output/hp/6/jmix/jmix.js');
    }

    function fix_headcontent() {
        // change number of drop lines if required (there is no setting for
        // this in the JMix application but it can be set in a cfg file)
        if ($drop_total = $this->expand_DropTotal()) {
            $this->headcontent = preg_replace('/(?<=var DropTotal = )\d+(?=;)/', $drop_total, $this->headcontent, 1);
        }

        // we must add a false return value to segment links in order not to trigger the onbeforeunload event handler
        // (this is only really required on v6 output format)
        $search = '/(?<='.'onclick="'.')'.'AddSegment\(\[SegmentNumber\]\)'.'(?='.'"'.')/';
        $replace = '$0'.'; return false;';
        $this->headcontent = preg_replace($search, $replace, $this->headcontent);
    }

    function fix_bodycontent_DragAndDrop($prefix='', $suffix='') {
        $search = '/for \(var i=0; i<DropTotal; i\+\+\)\{.*?\}/s';
        $replace = ''
            ."var myParentNode = null;\n"
            ."if (navigator.appName=='Microsoft Internet Explorer' && (document.documentMode==null || document.documentMode<8)) {\n"
            ."	// IE8+ (compatible mode) IE7, IE6, IE5 ...\n"
            ."} else {\n"
            ."	// Firefox, Safari, Opera, IE8+\n"
            ."	var obj = document.getElementsByTagName('div');\n"
            ."	if (obj && obj.length) {\n"
            ."		myParentNode = obj[obj.length - 1].parentNode;\n"
            ."		var css_prefix = new Array('webkit', 'khtml', 'moz', 'ms', 'o', '');\n"
            ."		for (var i=0; i<css_prefix.length; i++) {\n"
            ."			if (css_prefix[i]=='') {\n"
            ."				var userSelect = 'userSelect';\n"
            ."			} else {\n"
            ."				var userSelect = css_prefix[i] + 'UserSelect';\n"
            ."			}\n"
            ."			if (typeof(myParentNode.style[userSelect]) != 'undefined') {\n"
            ."				myParentNode.style[userSelect] = 'none';\n"
            ."				break;\n"
            ."			}\n"
            ."		}\n"
            ."		userSelect = null;\n"
            ."		css_prefix = null;\n"
            ."	}\n"
            ."}\n"
            ."for (var i=0; i<DropTotal; i++){\n"
            ."	if (myParentNode){\n"
            ."		var div = document.createElement('div');\n"
            ."		div.setAttribute('id', 'Drop' + i);\n"
            ."		div.setAttribute('class', 'DropLine');\n"
            ."		div.setAttribute('align', 'center');\n"
            ."		div.innerHTML = '&nbsp;<br />&nbsp;';\n"
            ."		myParentNode.appendChild(div);\n"
            ."	} else {\n"
            ."		document.write('".'<div id="'."Drop' + i + '".'" class="DropLine" align="center"'.">&nbsp;<br />&nbsp;</div>');\n"
            ."	}\n"
            ."}"
        ;
        $this->bodycontent = preg_replace($search, $replace, $this->bodycontent, 1);

        $search = '/for \(var i=0; i<Segments\.length; i\+\+\)\{.*?\}/s';
        $replace = '';
        if ($prefix) {
            $prefix = $this->source->js_value_safe($prefix);
            $replace .= ''
                ."if (myParentNode){\n"
                ."	var div = document.createElement('div');\n"
                ."	div.setAttribute('id', 'JMixPrefix');\n"
                ."	div.setAttribute('class', 'CardStyle');\n"
                ."	div.innerHTML = '$prefix';\n"
                ."	myParentNode.appendChild(div);\n"
                ."} else {\n"
                ."	document.write('".'<div id="JMixPrefix" class="CardStyle"'.">$prefix</div>');\n"
                ."}\n"
            ;
        }
        $replace .= ''
            ."for (var i=0; i<Segments.length; i++){\n"
            ."	if (myParentNode){\n"
            ."		var div = document.createElement('div');\n"
            ."		div.setAttribute('id', 'D' + i);\n"
            ."		div.setAttribute('class', 'CardStyle');\n"
            ."		div = myParentNode.appendChild(div);\n"
            ."		HP_add_listener(div, 'mousedown', 'beginDrag(event, ' + i + ')');\n"
            ."	} else {\n"
            ."		document.write('".'<div id="'."D' + i + '".'" class="CardStyle" onmousedown="'."beginDrag(event, ' + i + ')".'"'."></div>');\n"
            ."	}\n"
            ."}\n"
        ;
        if ($suffix) {
            $suffix = $this->source->js_value_safe($suffix);
            $replace .= ''
                ."if (myParentNode){\n"
                ."	var div = document.createElement('div');\n"
                ."	div.setAttribute('id', 'JMixSuffix');\n"
                ."	div.setAttribute('class', 'CardStyle');\n"
                ."	div.innerHTML = '$suffix';\n"
                ."	myParentNode.appendChild(div);\n"
                ."} else {\n"
                ."	document.write('".'<div id="JMixsuffix" class="CardStyle"'.">$suffix</div>');\n"
                ."}\n"
            ;
        }
        $replace .= ''
            ."myParentNode = div = null;"
        ;
        $this->bodycontent = preg_replace($search, $replace, $this->bodycontent, 1);
    }

    function fix_navigation_buttons()  {
        parent::fix_navigation_buttons();

        // replace location.reload() in <button class="FuncButton" ... onclick="location.reload()" ...">
        // with javascript code to return all tiles/segments to their starting positions
        $search = '/(?<='.'onclick=")location.reload\(\);?'.'(?=")/';
        if (strpos(get_class($this), '_plus_')) {
            // Drag and Drop
            $replace = 'for (var i=0; i<Cds.length; i++) Cds[i].GoHome(); return false;';
        } else {
            // clickety-click
            $replace = 'GuessSequence = new Array();'.
                       'BuildCurrGuess();'.
                       'BuildExercise();'.
                       'DisplayExercise(Exercise);'.
                       "WriteToGuess('<span class=&quot;Answer&quot;>' + Output + '</span>');".
                       'return false;';
        }
        $this->bodycontent = preg_replace($search, $replace, $this->bodycontent);
    }

    function get_js_functionnames()  {
        // Note that the drag functions get added twice to the html
        // once from hp6card.js_ (this is actually the JMatch version)
        // and once from djmix6.js_. Consequently, we need to process
        // these functions twice: once to delete, and then again to modify
        $drag = 'beginDrag,doDrag,endDrag';
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '')."CardSetHTML,$drag,CheckAnswer,TimesUp,WriteToGuess,$drag";
        return $names;
    }

    public function get_beginDrag_target() {
        return 'Cds[CurrDrag]';
    }

    function fix_js_TimesUp(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // make sure we get the latest GuessSequence
        $search = '	CheckAnswer(0);';
        if ($pos = strpos($substr, $search)) {
            $insert = ''
                ."	if (window.GetGuessSequence){\n"
                ."		GetGuessSequence();\n"
                ."		CompiledOutput = CompileString(GuessSequence);\n"
                ."	}\n"
            ;
            $substr = substr_replace($substr, $insert, $pos, 0);
        }

        $search = "/\s*document\.getElementById\('Timer'\)\.innerHTML = '([^']*)';/s";
        if (preg_match($search, $substr, $matches, PREG_OFFSET_CAPTURE)) {
            $msg = $matches[1][0];
            $substr = substr_replace($substr, '', $matches[0][1], strlen($matches[0][0]));
        } else {
            $msg = 'Your time is over!';
        }

        if ($pos = strrpos($substr, '}')) {
            if ($this->delay3==QUIZPORT_DELAY3_AFTEROK) {
                $flag = 1; // set form values only
            } else {
                $flag = 0; // set form values and send form
            }
            $insert = ''
                ."	Finished = true;\n"
                ."	HP.onunload(".QUIZPORT_STATUS_TIMEDOUT.",$flag);\n"
                ."	ShowMessage('$msg');\n"
            ;
            $substr = substr_replace($substr, $insert, $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_CheckAnswer(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // remove the premature "return" if there is no guesses, because this function
        // is also called for the "Give Up" button and the onbeforeunload() event handler
        // and in those cases we need to continue and return the results to Moodle
        $search = ''
            .'/(if \(GuessSequence\.length < 1\)\{)' // $1
            .'(.*?)' // $2
            .'\s*return;'
            .'(\s*\})' // $3
            .'/s'
        ;
        $substr = preg_replace($search, '$1$2$3', $substr, 1);

        // encapsulate the main body of the function code in an "if" block
        $search = ''
            .'/(?<=var AllDone = false;)' // look behind
            .'(.*?)'
            .'(\s*)'
            .'(?=if \(\(AllDone == true\)\|\|\(TimeOver == true\)\)\{)' // look ahead
            .'/s'
        ;
        if (preg_match($search, $substr, $matches, PREG_OFFSET_CAPTURE)) {
            $replace = "\n"
                .'	if (GuessSequence.length){'
                .preg_replace('/[\\n\\r]+/', '$0	', $matches[1][0])."\n"
                ."	}\n"
            ;
            $substr = substr_replace($substr, $replace, $matches[1][1], strlen($matches[1][0]));
        }

        // add other changes as per CheckAnswers in other type of HP quiz
        $this->fix_js_CheckAnswers($substr, 0, strlen($substr));

        // this must come after call to $this->fix_js_CheckAnswers()
        $search = 'TimeOver == true';
        if ($pos = strpos($substr, $search)) {
            $replace = $search.' || ForceQuizStatus';
            $substr = substr_replace($substr, $replace, $pos, strlen($search));
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_WriteToGuess(&$str, $start, $length)  {
        $substr = substr($str, $start, $length);

        if ($pos = strrpos($substr, '}')) {
            $insert = '	StretchCanvasToCoverContent(true);'."\n";
            $substr = substr_replace($substr, $insert, $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_StartUp_DragAndDrop(&$substr) {

        // fix top and left of drag area
        $this->fix_js_StartUp_DragAndDrop_DragArea($substr);

        // restrict width of drop lines
        $search = 'L[i].SetL(LeftColPos);';
        if ($pos = strpos($substr, $search)) {
            $insert = "\n\t\t".'L[i].SetW(DivWidth - 40);';
            $substr = substr_replace($substr, $insert, $pos + strlen($search), 0);
        }

        // position the prefix tile on the left of the top drop line
        // and the suffix tile on the right of the bottom drop line
        $search = 'SetInitialPositions();';
        if ($pos = strpos($substr, $search)) {
            $insert = "\n"
                ."	var div = document.getElementById('JMixPrefix');\n"
                ."	if (div) {\n"
                ."		div.style.top = L[0].GetT() + 'px';\n"
                ."		var leftmin = L[0].GetL();\n"
                ."		var leftmax = Cds[0].GetL() - parseInt(div.offsetWidth) - 10;\n"
                ."		div.style.left = Math.max(leftmin, leftmax) + 'px';\n"
                ."		div.style.color = Cds[0].css.color;\n"
                ."		div.style.backgroundColor = Cds[0].css.backgroundColor;\n"
                ."		div.style.zIndex = ++topZ;\n"
                ."	}\n"
                ."	var div = document.getElementById('JMixSuffix');\n"
                ."	if (div) {\n"
                ."		var x = L.length - 1;\n" // Math.min(L.length, CRows.length)
                ."		div.style.top = L[x].GetT() + 'px';\n"
                ."		var leftmin = L[x].GetR() - parseInt(div.offsetWidth);\n"
                ."		var x = Cds.length - 1;\n"
                ."		var leftmax = Cds[x].GetR() + 10;\n"
                ."		div.style.left = Math.min(leftmin, leftmax) + 'px';\n"
                ."		div.style.color = Cds[x].css.color;\n"
                ."		div.style.backgroundColor = Cds[x].css.backgroundColor;\n"
                ."		div.style.zIndex = ++topZ;\n"
                ."	}\n"
                ."	div = null;\n"
            ;
            $substr = substr_replace($substr, $insert, $pos + strlen($search), 0);
        }

        // stretch the canvas vertically down
        if ($pos = strrpos($substr, '}')) {
            $insert = ''
            ."	var b = 0;\n"
            ."	var objParentNode = null;\n"
            ."	if (window.Segments) {\n"
            ."		var obj = document.getElementById('D'+(Segments.length-1));\n"
            ."		if (obj) {\n"
            ."			b = Math.max(b, getOffset(obj,'Bottom'));\n"
            ."			objParentNode = objParentNode || obj.parentNode;\n"
            ."		}\n"
            ."	}\n"
            ."	if (b) {\n"
            ."		// stretch parentNodes down vertically, if necessary\n"
            ."		var canvas = document.getElementById('middle-column');\n"
            ."		while (objParentNode) {\n"
            ."			if (b > getOffset(objParentNode, 'Bottom')) {\n"
            ."				setOffset(objParentNode, 'Bottom', b+4);\n"
            ."			}\n"
            ."			if (canvas && objParentNode==canvas) {\n"
            ."				objParentNode = null;\n"
            ."			} else {\n"
            ."				objParentNode = objParentNode.parentNode;\n"
            ."			}\n"
            ."		}\n"
            ."	}\n"
            ;
            //$substr = substr_replace($substr, $insert, $pos, 0);
        }
    }

    function get_stop_onclick() {
        return ''
            .'if('.$this->get_stop_function_confirm().'){'
                .'if (window.GetGuessSequence){'
                    .'GetGuessSequence();'
                    .'CompiledOutput=CompileString(GuessSequence);'
                .'}'
                .$this->get_stop_function_name().'('.$this->get_stop_function_args().')'
            .'}'
        ;
    }

    function get_stop_function_name() {
        return 'CheckAnswer';
    }

    function get_stop_function_args() {
        return '0,'.QUIZPORT_STATUS_ABANDONED;
    }

    function get_stop_function_intercept() {
        return "\n"
            ."	if (CheckType==0) HP.onclickCheck(); // intercept Checks\n"
            ."	if (CheckType==1) HP.onclickHint(0); // intercept Hints\n"
        ;
    }
}
?>