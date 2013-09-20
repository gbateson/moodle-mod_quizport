<?php
class quizport_output_hp_6 extends quizport_output_hp {
    var $htmlcontent; // the raw html content, either straight from an html file, or generated from an xml file

    // names of javascript string and array variables which can
    // be passed through Glossary autolinking filter, if enabled
    var $headcontent_strings = '';
    var $headcontent_arrays = '';

    // HP quizzes have a SubmissionTimeout variable, but TexToys do not
    var $hasSubmissionTimeout = true;

    // constructor function
    function quizport_output_hp_6(&$quiz) {
        parent::quizport_output_hp($quiz);
        array_push($this->javascripts, 'mod/quizport/output/hp/hp.js');
        if ($this->studentfeedback) {
            array_push($this->javascripts, 'mod/quizport/output/hp/feedback.js');
        }
        array_unshift($this->templatesfolders, 'mod/quizport/output/hp/6/templates');
    }

    // function to generate browser content

    function set_xmldeclaration() {
        // required for IE6 viewing HP6 (and maybe other situations too)
        // see http://moodle.org/mod/forum/discuss.php?d=73309
        if (! isset($this->xmldeclaration)) {

            if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 6')) {
                // do not set xml declaration for IE6
                // otherwise it into quirks mode
                //TO DO: check behavior if (current_theme()=='custom_corners')
                $this->xmldeclaration = '';
            } else {
                $this->xmldeclaration = '<'.'?xml version="1.0"?'.'>'."\n";
            }
        }
    }

    function set_htmlcontent() {
        if (isset($this->htmlcontent)) {
            // htmlcontent has already been set
            return true;
        }
        $this->htmlcontent = '';

        if (! $this->source->get_filecontents()) {
            // empty source file - shouldn't happen !!
            return false;
        }

        // html files
        if ($this->source->is_html()) {
            $this->htmlcontent = &$this->source->filecontents;
            return true;
        }

        // xml files
        if (! $this->source->xml_get_filecontents()) {
            // could not create xml tree - shouldn't happen !!
            return false;
        }
        if (! $this->xml_set_htmlcontent()) {
            // could not create html from xml - shouldn't happen !!
            return false;
        }

        return true;
    }

    function set_headcontent() {
        global $CFG, $QUIZPORT;

        if (isset($this->headcontent)) {
            return;
        }
        $this->headcontent = '';

        if (! $this->set_htmlcontent()) {
            // could not locate/generate html content
            return;
        }

        // extract contents of first <head> tag
        if (preg_match($this->tagpattern('head'), $this->htmlcontent, $matches)) {
            $this->headcontent = $matches[2];
        }

        if ($this->usemoodletheme) {
            // remove the title from the <head>
            $this->headcontent = preg_replace($this->tagpattern('title'), '', $this->headcontent);
        } else {
            // replace <title> with current name of this quiz
            $title = '<title>'.$this->get_title().'</title>'."\n";
            $this->headcontent = preg_replace($this->tagpattern('title'), $title, $this->headcontent);

            // extract details needed to rebuild page later in $this->view()
            if (preg_match($this->tagpattern('\?xml','',false), $this->htmlcontent, $matches)) {
                $this->xmldeclaration = $matches[0]."\n";
            }
            if (preg_match($this->tagpattern('!DOCTYPE','',false), $this->htmlcontent, $matches)) {
                $this->doctype = $this->single_line($matches[0])."\n";
                // convert short dtd to full dtd (short dtd not allowed in xhtml 1.1)
                $this->doctype = preg_replace('/"xhtml(\d+)(-\w+)?.dtd"/i', '"http://www.w3.org/TR/xhtml\\1/DTD/xhtml\\1\\2.dtd"', $this->doctype, 1);
            }
            if (preg_match($this->tagpattern('html','',false), $this->htmlcontent, $matches)) {
                $this->htmltag = $this->single_line($matches[0])."\n";
            }
            if (preg_match($this->tagpattern('head','',false), $this->htmlcontent, $matches)) {
                $this->headattributes = ' '.$this->single_line($matches[1]);
            }
        }

        // transfer <styles> tags from $this->headcontent to $this->styles
        $this->styles = '';
        if (preg_match_all($this->tagpattern('style'), $this->headcontent, $matches, PREG_OFFSET_CAPTURE)) {
            foreach (array_reverse($matches[0]) as $match) {
                // $match: [0] = matched string, [1] = offset to start of string
                $this->styles = $match[0]."\n".$this->styles;
                $this->headcontent = substr_replace($this->headcontent, '', $match[1], strlen($match[0]));
            }
            if ($this->usemoodletheme) {
                // restrict scope of Hot Potatoes styles, so they affect only the quiz's containing element (i.e. the middle column)
                $search = '/([a-z0-9_\#\.\-\,\: ]+){(.*?)}/ise';
                $replace = '$this->fix_css_definitions("#middle-column","\\1","\\2")';
                $this->styles = preg_replace($search, $replace, $this->styles);

                // the following is not necessary for standard HP styles, but may required to handle some custom styles
                $this->styles = str_replace('TheBody', 'mod-quizport-view', $this->styles);
            }
            $this->styles = $this->remove_blank_lines($this->styles);
        }

        // transfer <script> tags from $this->headcontent to $this->scripts
        $this->scripts = '';
        if (preg_match_all($this->tagpattern('script'), $this->headcontent, $matches, PREG_OFFSET_CAPTURE)) {
            foreach (array_reverse($matches[0]) as $match) {
                // $match: [0] = matched string, [1] = offset to start of string
                $this->scripts = $match[0]."\n".$this->scripts;
                $this->headcontent = substr_replace($this->headcontent, '', $match[1], strlen($match[0]));
            }
            if ($this->usemoodletheme) {
                $this->scripts = str_replace('TheBody', 'mod-quizport-view', $this->scripts);
            }
            // fix various javascript functions
            $names = $this->get_js_functionnames();
            $this->fix_js_functions($names);

            $this->scripts = preg_replace('/\s*'.'(var )?ResultForm [^;]+?;/s', '', $this->scripts);

            // remove multi-line and single-line comments - except <![CDATA[ + ]]> and  <!-- + -->
            if ($CFG->debug <= DEBUG_DEVELOPER) {
                $this->scripts = $this->fix_js_comment($this->scripts);
            }
            $this->scripts = $this->remove_blank_lines($this->scripts);

            // standardize "} else {" and "} else if" formatting
            $this->scripts = preg_replace('/\}\s*else\s*(\{|if)/s', '} else \\1', $this->scripts);

            // standardize indentation to use tabs
            $this->scripts = str_replace('        ', "\t", $this->scripts);

            // add link to external javascript libraries
            foreach ($this->javascripts as $script) {
                $this->scripts .= "\n".'<script type="text/javascript" src="'.$CFG->wwwroot.'/'.$script.'"></script>';
            }
        }

        // remove blank lines
        $this->headcontent = $this->remove_blank_lines($this->headcontent);

        // put each <meta> tag on its own line
        $this->headcontent = preg_replace('/'.'([^\n])'.'(<\w+)'.'/', "\\1\n\\2", $this->headcontent);

        // convert self closing tags to explictly closed tags (self-clocing not allowed in xhtml 1.1)
        // $this->headcontent = preg_replace('/<((\w+)[^>]*?)\s*\/>/i', '<\\1></\\2>', $this->headcontent);

        // append styles and scripts to the end of the $this->headcontent
        $this->headcontent .= $this->styles.$this->scripts;

        // do any other fixes for the headcontent
        $this->fix_headcontent_beforeonunload();
        $this->fix_headcontent();
    }

    function fix_headcontent() {
        // this function is a hook that can be used by sub classes to fix up the headcontent
    }

    function fix_headcontent_beforeonunload() {
        global $QUIZPORT;

        // warn user about consequences of navigating away from this page
        switch ($this->can_continue()) {
            case QUIZPORT_CONTINUE_RESUMEQUIZ:
                $onbeforeunload = get_string('canresumequiz', 'quizport', format_string($QUIZPORT->quiz->name));
                break;
            case QUIZPORT_CONTINUE_RESTARTQUIZ:
                $onbeforeunload = get_string('canrestartquiz', 'quizport', format_string($QUIZPORT->quiz->name));
                break;
            case QUIZPORT_CONTINUE_RESTARTUNIT:
                $onbeforeunload = get_string('canrestartunit', 'quizport');
                break;
            case QUIZPORT_CONTINUE_ABANDONUNIT:
                $onbeforeunload = get_string('abandonunit', 'quizport');
                break;
            default:
                $onbeforeunload = ''; // shouldn't happen !!
        }
        if ($onbeforeunload) {
            $search = "/(\s*)window.onunload = new Function[^\r\n]*;/s";
            $replace = "\\0\\1"
                ."window.quizportbeforeunload = function(){\\1"
                ."	if (window.HP) {\\1"
                ."		HP.onunload();\\1"
                ."	}\\1"
                ."	return '".$this->source->js_value_safe($onbeforeunload, true)."';\\1"
                ."}\\1"
                ."if (window.opera) {\\1"
                // user scripts (this is here for reference only)
                // ."	opera.setOverrideHistoryNavigationMode('compatible');\\1"
                // web page scripts
                ."	history.navigationMode = 'compatible';\\1"
                ."}\\1"
                ."window.onbeforeunload = window.quizportbeforeunload;"
            ;
            $this->headcontent = preg_replace($search, $replace, $this->headcontent, 1);
        }
    }

    function fix_headcontent_rottmeier($type='') {

        switch ($type) {

            case 'findit':
                // get position of last </style> tag and
                // insert CSS to make <b> and <em> tags bold
                // even within GapSpans added by javascript
                if ($pos = $this->strrpos($this->headcontent, '</style>')) {
                    $insert = ''
                        .'</style>'."\n"
                        .'<!--[if IE 6]><style type="text/css">'."\n"
                        .'span.GapSpan{'."\n"
                        .'	font-size:24px;'."\n"
                        .'}'."\n"
                        .'</style><![endif]-->'."\n"
                        .'<style type="text/css">'."\n"
                        .'b span.GapSpan,'."\n"
                        .'em span.GapSpan{'."\n"
                        .'	font-weight:inherit;'."\n"
                        .'}'."\n"
                    ;
                    $this->headcontent = substr_replace($this->headcontent, $insert, $pos, 0);
                }
                break;

            case 'jintro':
                // add TimeOver variable, so we can use standard detection of quiz completion
                if ($pos = strpos($this->headcontent, 'var Score = 0;')) {
                    $insert = "var TimeOver = false;\n";
                    $this->headcontent = substr_replace($this->headcontent, $insert, $pos, 0);
                }
                break;

            case 'jmemori':
                // add TimeOver variable, so we can use standard detection of quiz completion
                if ($pos = strpos($this->headcontent, 'var Score = 0;')) {
                    $insert = "var TimeOver = false;\n";
                    $this->headcontent = substr_replace($this->headcontent, $insert, $pos, 0);
                }

                // override table border collapse from standard Moodle styles
                if ($pos = $this->strrpos($this->headcontent, '</style>')) {
                    $insert = ''
                        .'#middle-column form table'."\n"
                        .'{'."\n"
                        .'	border-collapse: separate;'."\n"
                        .'	border-spacing: 2px;'."\n"
                        .'}'."\n"
                    ;
                    $this->headcontent = substr_replace($this->headcontent, $insert, $pos, 0);
                }
                break;
        }
    }

    function fix_js_comment($str) {
        $out = '';

        // the parse state
        //     0 : javascript code
        //     1 : single-quoted string
        //     2 : double-quoted string
        //     3 : single line comment
        //     4 : multi-line comment
        $state = 0;

        $strlen = strlen($str);
        $i = 0;
        while ($i<$strlen) {
            switch ($state) {
                case 1: // single-quoted string
                    $out .= $str{$i};
                    switch ($str{$i}) {
                        case "\\":
                            $i++; // skip next char
                            $out .= $str{$i};
                            break;
                        case "\n":
                        case "\r":
                        case "'":
                            $state = 0; // end of this string
                            break;
                    }
                    break;

                case 2: // double-quoted string
                    $out .= $str{$i};
                    switch ($str{$i}) {
                        case "\\":
                            $i++; // skip next char
                            $out .= $str{$i};
                            break;
                        case "\n":
                        case "\r":
                        case '"':
                            $state = 0; // end of this string
                            break;
                    }
                    break;

                case 3: // single line comment
                    if ($str{$i}=="\n" || $str{$i}=="\r") {
                        $state = 0; // end of this comment
                        $out .= $str{$i};
                    }
                    break;

                case 4: // multi-line comment
                    if ($str{$i}=='*' && $str{$i+1}=='/') {
                        $state = 0; // end of this comment
                        $i++;
                    }
                    break;

                case 0: // plain old JavaScript code
                default:
                    switch ($str{$i}) {
                        case "'":
                            $out .= $str{$i};
                            $state = 1; // start of single quoted string
                            break;

                        case '"':
                            $out .= $str{$i};
                            $state = 2; // start of double quoted string
                            break;

                        case '/':
                            switch ($str{$i+1}) {
                                case '/':
                                    switch (true) {
                                        // allow certain single line comments
                                        case substr($str, $i+2, 9)=='<![CDATA[':
                                            $out .= substr($str, $i, 11);
                                            $i += 11;
                                            break;
                                        case substr($str, $i+2, 3)==']]>':
                                            $out .= substr($str, $i, 5);
                                            $i += 5;
                                            break;
                                        case substr($str, $i+2, 4)=='<!--':
                                            $out .= substr($str, $i, 6);
                                            $i += 6;
                                            break;
                                        case substr($str, $i+2, 3)=='-->':
                                            $out .= substr($str, $i, 5);
                                            $i += 5;
                                            break;
                                        default:
                                            $state = 3; // start of single line comment
                                            $i++;
                                            break;
                                    }
                                    break;
                                case '*':
                                    $state = 4; // start of multi-line comment
                                    $i++;
                                    break;
                                default:
                                    // a slash - could be start of RegExp ?!
                                    $out .= $str{$i};
                            }
                            break;

                        default:
                            $out .= $str{$i};

                    } // end switch : non-comment char
            } // end switch : status
            $i++;
        } // end while

        return $out;
    }

   function set_bodycontent() {
        if (isset($this->bodycontent)) {
            // content was fetched from cache
            return;
        }

        // otherwise we need to generate new body content

        $this->bodycontent = '';

        if (! $this->set_htmlcontent()) {
            // could not locate/generate html content
            return;
        }

        // extract <body> tag
        if (! preg_match($this->tagpattern('body', 'onload'), $this->htmlcontent, $matches)) {
            return false;
        }
        if ($this->usemoodletheme) {
            $matches[1] = str_replace('id="TheBody"', '', $matches[1]);
        }
        $this->bodyattributes = $this->single_line($matches[1]);
        $onload = $matches[3];
        $this->bodycontent = $this->remove_blank_lines($matches[5]);

       // where necessary, add single space before javascript event handlers to make the syntax compatible with strict XHTML
        $this->bodycontent = preg_replace('/"(on(?:blur|click|focus|mouse(?:down|out|over|up)))/', '" \\1', $this->bodycontent);

        if ($this->usemoodletheme) {
            // workaround to ensure javascript onload routine for quiz is always executed
            // $this->bodyattributes will only be inserted into the <body ...> tag
            // if it is included in the theme/$CFG->theme/header.html,
            // so some old or modified themes may not insert $this->bodyattributes
            $this->bodycontent .= $this->fix_onload($onload, true);
        }

        $this->fix_title();
        $this->fix_TimeLimit();
        $this->fix_SubmissionTimeout();
        $this->fix_relativeurls();
        $this->fix_navigation();
        $this->fix_filters();
        $this->fix_mediafilter();
        $this->fix_feedbackform();
        $this->fix_reviewoptions();
        $this->fix_targets();
        $this->fix_bodycontent();
    }

    function fix_bodycontent() {
        // this function is a hook that can be used by sub classes to fix up the bodycontent
    }

    function fix_bodycontent_rottmeier($hideclozeform=false) {
        // fix left aligned instructions in Rottmeier-based formats
        //     JCloze: DropDown, FindIt(a)+(b), JGloss
        //     JMatch: JMemori
        $search = '/<p id="Instructions">(.*?)<\/p>/is';
        $replace = '<div id="Instructions">\\1</div>';
        $this->bodycontent = preg_replace($search, $replace, $this->bodycontent);

        if ($hideclozeform) {
            // initially hide the Cloze text (so gaps are not revealed)
            $search = '/<form id="Cloze" [^>]*>/is';
            if (preg_match($search, $this->bodycontent, $matches, PREG_OFFSET_CAPTURE)) {
                $match = $matches[0][0];
                $start = $matches[0][1];
                if (strpos($match, 'display:none')===false) {
                    $pos = $start + strlen($match) - 1;
                    $this->bodycontent = substr_replace($this->bodycontent, ' style="display:none"', $pos, 0);
                }
            }
        }
    }

    function get_js_functionnames() {
        // return a comma-separated list of js functions to be "fixed".
        // Each function name requires an corresponding function called:
        // fix_js_{$name}

        return 'Client,ShowElements,GetViewportHeight,PageDim,TrimString,StartUp,GetUserName,PreloadImages,ShowMessage,HideFeedback,SendResults,Finish,WriteToInstructions,ShowSpecialReadingForQuestion';
    }

    function fix_js_functions($names) {
        if (is_string($names)) {
            $names = explode(',', $names);
        }
        foreach($names as $name) {
            list($start, $finish) = $this->locate_js_function($name, $this->scripts);
            if (! $finish) {
                // debugging("Could not locate JavaScript function: $name", DEBUG_DEVELOPER);
                continue;
            }
            $methodname = "fix_js_{$name}";
            if (! method_exists($this, $methodname)) {
                // debugging("Could not locate method to fix JavaScript function: $name", DEBUG_DEVELOPER);
                continue;
            }
            $this->$methodname($this->scripts, $start, ($finish - $start));
        }
    }

    function locate_js_function($name, &$str, $includewhitespace=false) {
        $start = 0;
        $finish = 0;

        if ($includewhitespace) {
            $search = '/\s*'.'function '.$name.'\b/s';
        } else {
            $search = '/\b'.'function '.$name.'\b/';
        }
        if (preg_match($search, $str, $matches, PREG_OFFSET_CAPTURE)) {
            // $matches[0][0] : matching string
            // $matches[0][1] : offset to matching string
            $start = $matches[0][1];

            // position of opening curly bracket (or thereabouts)
            $i = $start + strlen($matches[0][0]);

            // count how many opening curly brackets we have had so far
            $count = 0;

            // the parse state
            //     0 : javascript code
            //     1 : single-quoted string
            //     2 : double-quoted string
            //     3 : single line comment
            //     4 : multi-line comment
            $state = 0;

            $strlen = strlen($str);
            while ($i<$strlen && ! $finish) {
                switch ($state) {
                    case 1: // single-quoted string
                        switch ($str{$i}) {
                            case "\\":
                                $i++; // skip next char
                                break;
                            case "'":
                                $state = 0; // end of this string
                                break;
                        }
                        break;

                    case 2: // double-quoted string
                        switch ($str{$i}) {
                            case "\\":
                                $i++; // skip next char
                                break;
                            case '"':
                                $state = 0; // end of this string
                                break;
                        }
                        break;

                    case 3: // single line comment
                        if ($str{$i}=="\n" || $str{$i}=="\r") {
                            $state = 0; // end of this comment
                        }
                        break;

                    case 4: // multi-line comment
                        if ($str{$i}=='*' && $str{$i+1}=='/') {
                            $state = 0; // end of this comment
                            $i++;
                        }
                        break;

                    case 0: // plain old JavaScript code
                    default:
                        switch ($str{$i}) {
                            case "'":
                                $state = 1; // start of single quoted string
                                break;

                            case '"':
                                $state = 2; // start of double quoted string
                                break;

                            case '/':
                                switch ($str{$i+1}) {
                                    case '/':
                                        $state = 3; // start of single line comment
                                        $i++;
                                        break;
                                    case '*':
                                        $state = 4; // start of multi-line comment
                                        $i++;
                                        break;
                                }
                                break;

                            case '{':
                                $count++; // start of Javascript code block
                                break;

                            case '}':
                                $count--; // end of Javascript code block
                                if ($count==0) { // end of outer code block (i.e. end of function)
                                    $finish = $i + 1;
                                }
                                break;

                        } // end switch : non-comment char
                } // end switch : status
                $i++;
            } // end while
        } // end if $start
        return array($start, $finish);
    }

    function fix_js_Client(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // refine detection of Chrome browser
        $search = 'this.geckoVer < 20020000';
        if ($pos = strpos($substr, $search)) {
            $substr = substr_replace($substr, 'this.geckoVer > 10000000 && ', $pos, 0);
        }

        // add detection of Chrome browser
        $search = '/(\s*)if \(this\.min == false\)\{/s';
        $replace = "\\1"
            ."this.chrome = (this.ua.indexOf('Chrome') > 0);\\1"
            ."if (this.chrome) {\\1"
            ."	this.geckoVer = 0;\\1"
            ."	this.safari = false;\\1"
            ."	this.min = true;\\1"
            ."}\\0"
        ;
        $substr = preg_replace($search, $replace, $substr, 1);

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_ShowElements(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // hide <embed> tags (required for Chrome browser)
        if ($pos = strpos($substr, 'TagName == "object"')) {
            $substr = substr_replace($substr, 'TagName == "embed" || ', $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_PageDim(&$str, $start, $length) {
        if ($this->usemoodletheme) {
            $obj = "document.getElementById('middle-column')"; // moodle
        } else {
            $obj = "document.getElementsByTagName('body')[0]"; // original
        }
        $replace = ''
            ."function getStyleValue(obj, property_name, propertyName){\n"
            ."	var value = 0;\n"
            ."	// Watch out for HTMLDocument which has no style property\n"
            ."	// as this causes errors later in getComputedStyle() in FF\n"
            ."	if (obj && obj.style){\n"
            ."		// based on http://www.quirksmode.org/dom/getstyles.html\n"
            ."		if (document.defaultView && document.defaultView.getComputedStyle){\n"
            ."			// Firefox, Opera, Safari\n"
            ."			value = document.defaultView.getComputedStyle(obj, null).getPropertyValue(property_name);\n"
            ."		} else if (obj.currentStyle) {"
            ."			// IE (and Opera)\n"
            ."			value = obj.currentStyle[propertyName];\n"
            ."		}\n"
            ."		if (typeof(value)=='string'){\n"
            ."			var r = new RegExp('([0-9.]*)([a-z]+)');\n"
            ."			var m = value.match(r);\n"
            ."			if (m){\n"
            ."				switch (m[2]){\n"
            ."					case 'em':\n"
            ."						// as far as I can see, only IE needs this\n"
            ."						// other browsers have getComputedStyle() in px\n"
            ."						if (typeof(obj.EmInPx)=='undefined'){\n"
            ."							var div = obj.parentNode.appendChild(document.createElement('div'));\n"
            ."							div.style.margin = '0px';\n"
            ."							div.style.padding = '0px';\n"
            ."							div.style.border = 'none';\n"
            ."							div.style.height = '1em';\n"
            ."							obj.EmInPx = getOffset(div, 'Height');\n"
            ."							obj.parentNode.removeChild(div);\n"
            ."						}\n"
            ."						value = parseFloat(m[1] * obj.EmInPx);\n"
            ."						break;\n"
            ."					case 'px':\n"
            ."						value = parseFloat(m[1]);\n"
            ."						break;\n"
            ."					default:\n"
            ."						value = 0;\n"
            ."				}\n"
            ."			} else {\n"
            ."				value = 0 ;\n"
            ."			}\n"
            ."		} else {\n"
            ."			value = 0;\n"
            ."		}\n"
            ."	}\n"
            ."	return value;\n"
            ."}\n"
            ."function isStrict(){\n"
            ."	if (typeof(window.cache_isStrict)=='undefined'){\n"
            ."		if (document.compatMode) { // ie6+\n"
            ."			window.cache_isStrict = (document.compatMode=='CSS1Compat');\n"
            ."		} else if (document.doctype){\n"
            ."			var s = document.doctype.systemId || document.doctype.name; // n6 OR ie5mac\n"
            ."			window.cache_isStrict = (s && s.indexOf('strict.dtd') >= 0);\n"
            ."		} else {\n"
            ."			window.cache_isStrict = false;\n"
            ."		}\n"
            ."	}\n"
            ."	return window.cache_isStrict;\n"
            ."}\n"
            ."function setOffset(obj, type, value){\n"
            ."	if (! obj){\n"
            ."		return 0;\n"
            ."	}\n"
            ."\n"
            ."	switch (type){\n"
            ."		case 'Right':\n"
            ."			return setOffset(obj, 'Width', value - getOffset(obj, 'Left'));\n"
            ."		case 'Bottom':\n"
            ."			return setOffset(obj, 'Height', value - getOffset(obj, 'Top'));\n"
            ."	}\n"
            ."\n"
            ."	if (isStrict()){\n"
            ."		// set arrays of p(roperties) and s(ub-properties)\n"
            ."		var properties = new Array('margin', 'border', 'padding');\n"
            ."		switch (type){\n"
            ."			case 'Top':\n"
            ."				var sides = new Array('Top');\n"
            ."				break;\n"
            ."			case 'Left':\n"
            ."				var sides = new Array('Left');\n"
            ."				break;\n"
            ."			case 'Width':\n"
            ."				var sides = new Array('Left', 'Right');\n"
            ."				break;\n"
            ."			case 'Height':\n"
            ."				var sides = new Array('Top', 'Bottom');\n"
            ."				break;\n"
            ."			default:\n"
            ."				return 0;\n"
            ."		}\n"
            ."		for (var p=0; p<properties.length; p++){\n"
            ."			for (var s=0; s<sides.length; s++){\n"
            ."				var propertyName = properties[p] + sides[s];\n"
            ."				var property_name = properties[p] + '-' + sides[s].toLowerCase();\n"
            ."				value -= getStyleValue(obj, property_name, propertyName);\n"
            ."			}\n"
            ."		}\n"
            ."		value = Math.floor(value);\n"
            ."	}\n"
            ."	if (obj.style) {\n"
            ."		obj.style[type.toLowerCase()] = value + 'px';\n"
            ."	}\n"
            ."}\n"
            ."function getOffset(obj, type){\n"
            ."	if (! obj){\n"
            ."		return 0;\n"
            ."	}\n"
            ."	switch (type){\n"
            ."		case 'Width':\n"
            ."		case 'Height':\n"
            ."			return eval('(obj.offset'+type+'||0)');\n"
            ."\n"
            ."		case 'Top':\n"
            ."		case 'Left':\n"
            ."			return eval('(obj.offset'+type+'||0) + getOffset(obj.offsetParent, type)');\n"
            ."\n"
            ."		case 'Right':\n"
            ."			return getOffset(obj, 'Left') + getOffset(obj, 'Width');\n"
            ."\n"
            ."		case 'Bottom':\n"
            ."			return getOffset(obj, 'Top') + getOffset(obj, 'Height');\n"
            ."\n"
            ."		default:\n"
            ."			return 0;\n"
            ."	} // end switch\n"
            ."}\n"
            ."function PageDim(){\n"
            ."	var obj = $obj;\n"
            ."	this.W = getOffset(obj, 'Width');\n"
            ."	this.H = getOffset(obj, 'Height');\n"
            ."	this.Top = getOffset(obj, 'Top');\n"
            ."	this.Left = getOffset(obj, 'Left');\n"
            ."}\n"
            ."function getClassAttribute(className, attributeName){\n"
            ."	//based on http://www.shawnolson.net/a/503/\n"
            ."	if (! document.styleSheets){\n"
            ."		return null; // old browser\n"
            ."	}\n"
            ."	var css = document.styleSheets;\n"
            ."	var rules = (document.all ? 'rules' : 'cssRules');\n"
            ."	var regexp = new RegExp('\\\\.'+className+'\\\\b');\n"
            ."	try {\n"
            ."		var i_max = css.length;\n"
            ."	} catch(err) {\n"
            ."		var i_max = 0; // shouldn't happen !!\n"
            ."	}\n"
            ."	for (var i=0; i<i_max; i++){\n"
            ."		try {\n"
            ."			var ii_max = css[i][rules].length;\n"
            ."		} catch(err) {\n"
            ."			var ii_max = 0; // shouldn't happen !!\n"
            ."		}\n"
            ."		for (var ii=0; ii<ii_max; ii++){\n"
            ."			if (! css[i][rules][ii].selectorText){\n"
            ."				continue;\n"
            ."			}\n"
            ."			if (css[i][rules][ii].selectorText.match(regexp)){\n"
            ."				if (css[i][rules][ii].style[attributeName]){\n"
            ."					// class/attribute found\n"
            ."					return css[i][rules][ii].style[attributeName];\n"
            ."				}\n"
            ."			}\n"
            ."		}\n"
            ."	}\n"
            ."	// class/attribute not found\n"
            ."	return null;\n"
            ."}\n"
        ;
        $str = substr_replace($str, $replace, $start, $length);
    }

    function fix_js_GetViewportHeight(&$str, $start, $length) {
        $replace = ''
            ."function GetViewportSize(type){\n"
            ."	if (eval('window.inner' + type)){\n"
            ."		return eval('window.inner' + type);\n"
            ."	}\n"
            ."	if (document.documentElement){\n"
            ."		if (eval('document.documentElement.client' + type)){\n"
            ."			return eval('document.documentElement.client' + type);\n"
            ."		}\n"
            ."	}\n"
            ."	if (document.body){\n"
            ."		if (eval('document.body.client' + type)){\n"
            ."			return eval('document.body.client' + type);\n"
            ."		}\n"
            ."	}\n"
            ."	return 0;\n"
            ."}\n"
            ."function GetViewportHeight(){\n"
            ."	return GetViewportSize('Height');\n"
            ."}\n"
            ."function GetViewportWidth(){\n"
            ."	return GetViewportSize('Width');\n"
            ."}"
        ;
        $str = substr_replace($str, $replace, $start, $length);
    }

    function remove_js_function(&$str, $start, $length, $function) {
        // remove this function
        $str = substr_replace($str, '', $start, $length);

        // remove all direct calls to this function
        $search = '/\s*'.$function.'\([^)]*\);/s';
        $str = preg_replace($search, '', $str);
    }

    function fix_js_TrimString(&$str, $start, $length) {
        $replace = ''
            ."function TrimString(InString){\n"
            ."	if (typeof(InString)=='string'){\n"
            ."		InString = InString.replace(new RegExp('^\\\\s+', 'g'), ''); // left\n"
            ."		InString = InString.replace(new RegExp('\\\\s+$', 'g'), ''); // right\n"
            ."		InString = InString.replace(new RegExp('\\\\s+', 'g'), ' '); // inner\n"
            ."	}\n"
            ."	return InString;\n"
            ."}"
        ;
        $str = substr_replace($str, $replace, $start, $length);
    }

    function fix_js_TypeChars(&$str, $start, $length) {
        if ($obj = $this->fix_js_TypeChars_obj()) {
            $substr = substr($str, $start, $length);
            if (strpos($substr, 'document.selection')===false) {
                $replace = ''
                    ."function TypeChars(Chars){\n"
                    .$this->fix_js_TypeChars_init()
                    ."	if ($obj==null || $obj.style.display=='none') {\n"
                    ."		return;\n"
                    ."	}\n"
                    ."	$obj.focus();\n"
                    ."	if (typeof($obj.selectionStart)=='number') {\n"
                    ."		// FF, Safari, Chrome, Opera\n"
                    ."		var startPos = $obj.selectionStart;\n"
                    ."		var endPos = $obj.selectionEnd;\n"
                    ."		$obj.value = $obj.value.substring(0, startPos) + Chars + $obj.value.substring(endPos);\n"
                    ."		var newPos = startPos + Chars.length;\n"
                    ."		$obj.setSelectionRange(newPos, newPos);\n"
                    ."	} else if (document.selection) {\n"
                    ."		// IE (tested on IE6, IE7, IE8)\n"
                    ."		var rng = document.selection.createRange();\n"
                    ."		rng.text = Chars;\n"
                    ."		rng = null; // prevent memory leak\n"
                    ."	} else {\n"
                    ."		// this browser can't insert text, so append instead\n"
                    ."		$obj.value += Chars;\n"
                    ."	}\n"
                    ."}"
                ;
                $str = substr_replace($str, $replace, $start, $length);
            }
        }
    }
    function fix_js_TypeChars_init() {
        return '';
    }
    function fix_js_TypeChars_obj() {
        return '';
    }

    function fix_js_SendResults(&$str, $start, $length) {
        $this->remove_js_function($str, $start, $length, 'SendResults');
    }

    function fix_js_GetUserName(&$str, $start, $length) {
        $this->remove_js_function($str, $start, $length, 'GetUserName');
    }

    function fix_js_Finish(&$str, $start, $length) {
        $this->remove_js_function($str, $start, $length, 'Finish');
    }

    function fix_js_PreloadImages(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // fix issue in IE8 which sometimes doesn't have Image object in popups
        // http://moodle.org/mod/forum/discuss.php?d=134510
        $search = "Imgs[i] = new Image();";
        if ($pos = strpos($substr, $search)) {
            $replace = "Imgs[i] = (window.Image ? new Image() : document.createElement('img'));";
            $substr = substr_replace($substr, $replace, $pos, strlen($search));
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_WriteToInstructions(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        if ($pos = strpos($substr, '{')) {
            $insert = "\n"
                ."	// check required HTML element exists\n"
                ."	if (! document.getElementById('InstructionsDiv')) return false;\n"
            ;
            $substr = substr_replace($substr, $insert, $pos+1, 0);
        }

        if ($pos = strrpos($substr, '}')) {
            $append = "\n"
                ."	StretchCanvasToCoverContent(true);\n"
            ;
            $substr = substr_replace($substr, $append, $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_ShowMessage(&$str, $start, $length) {
        // the ShowMessage function is used by all HP6 quizzes

        $substr = substr($str, $start, $length);

        // only show feedback if the required HTML elements exist
        // this prevents JavaScript errors which block the returning of the quiz results to Moodle
        if ($pos = strpos($substr, '{')) {
            $insert = "\n"
                ."	// check required HTML elements exist\n"
                ."	if (! document.getElementById('FeedbackDiv')) return false;\n"
                ."	if (! document.getElementById('FeedbackContent')) return false;\n"
                ."	if (! document.getElementById('FeedbackOKButton')) return false;\n"
            ;
            $substr = substr_replace($substr, $insert, $pos+1, 0);
        }

        // hide <embed> elements on Chrome browser
        $search = "/(\s*)ShowElements\(true, 'object', 'FeedbackContent'\);/s";
        $replace = ''
            ."\\0\\1"
            ."if (C.chrome) {\\1"
            ."	ShowElements(false, 'embed');\\1"
            ."	ShowElements(true, 'embed', 'FeedbackContent');\\1"
            ."}"
        ;
        $substr = preg_replace($search, $replace, $substr, 1);

        // append link to student feedback form, if necessary
        if ($this->studentfeedback) {
            $search = '/(\s*)var Output = [^;]*;/';
            $replace = ''
                ."\\0\\1"
                ."if (window.FEEDBACK) {\\1"
                ."	Output += '".'<a href="javascript:hpFeedback();">'."' + FEEDBACK[6] + '</a>';\\1"
                ."}"
            ;
            $substr = preg_replace($search, $replace, $substr, 1);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_StartUp_DragAndDrop(&$substr) {
        // fixes for Drag and Drop (JMatch and JMix)
    }

    function fix_js_StartUp_DragAndDrop_DragArea(&$substr) {
        // fix LeftCol (=left side of drag area)
        $search = '/(LeftColPos = [^;]+);/';
        $replace = '\\1 + pg.Left;';
        $substr = preg_replace($search, $replace, $substr, 1);

        // fix DragTop (=top side of Drag area)
        $search = '/DragTop = [^;]+;/';
        $replace = "DragTop = getOffset(document.getElementById('CheckButtonDiv'),'Bottom') + 10;";
        $substr = preg_replace($search, $replace, $substr, 1);
    }

    function fix_js_StartUp(&$str, $start, $length) {
        global $QUIZPORT;

        $substr = substr($str, $start, $length);

        // if necessary, fix drag area for JMatch or JMix drag-and-drop
        $this->fix_js_StartUp_DragAndDrop($substr);

        if ($pos = strrpos($substr, '}')) {
            if ($this->delay3==QUIZPORT_DELAY3_DISABLE) {
                $forceajax = 1;
            } else {
                $forceajax = 0;
            }
            if ($this->can_continue()==QUIZPORT_CONTINUE_RESUMEQUIZ) {
                $onunload_status = QUIZPORT_STATUS_INPROGRESS;
            } else {
                $onunload_status = QUIZPORT_STATUS_ABANDONED;
            }
            $append = "\n"
                ."// adjust size and position of Feedback DIV\n"
                ."	if (! window.pg){\n"
                ."		window.pg = new PageDim();\n"
                ."	}\n"
                ."	var FDiv = document.getElementById('FeedbackDiv');\n"
                ."	if (FDiv){\n"
                ."		var w = getOffset(FDiv, 'Width') || FDiv.style.width || getClassAttribute(FDiv.className, 'width');\n"
                ."		if (w){\n"
                ."			if (typeof(w)=='string' && w.indexOf('%')>=0){\n"
                ."				var percent = parseInt(w);\n"
                ."			} else {\n"
                ."				var percent = Math.floor(100 * parseInt(w) / pg.W);\n"
                ."			}\n"
                ."		} else if (window.FeedbackWidth && window.DivWidth){\n"
                ."			var percent = Math.floor(100 * FeedbackWidth / DivWidth);\n"
                ."		} else {\n"
                ."			var percent = 34; // default width as percentage\n"
                ."		}\n"
                ."		FDiv.style.width = Math.floor(pg.W * percent / 100) + 'px';\n"
                ."		FDiv.style.left = (pg.Left + Math.floor(pg.W * (50 - percent/2) / 100)) + 'px';\n"
                ."	}\n"
                ."\n"
                ."// create HP object (to collect and send responses)\n"
                ."	window.HP = new ".$this->js_object_type."('".$this->can_clickreport()."','".$forceajax."');\n"
                ."\n"
                ."// call HP.onunload to send results when this page unloads\n"
                ."	var s = '';\n"
                ."	if (typeof(window.onunload)=='function'){\n"
                ."		window.onunload_StartUp = onunload;\n"
                ."		s += 'window.onunload_StartUp();'\n"
                ."	}\n"
                ."	window.onunload = new Function(s + 'if(window.HP){HP.status=$onunload_status;HP.onunload();object_destroy(HP);}return true;');\n"
                ."\n"
            ;
            $substr = substr_replace($substr, $append, $pos, 0);
        }

        // stretch the canvas vertically down, if there is a reading
        if ($pos = strrpos($substr, '}')) {
            // Reading is contained in <div class="LeftContainer">
            // MainDiv is contained in <div class="RightContainer">
            // when there is a reading. Otherwise, MainDiv is not contained.
            // ReadingDiv is used to show different reading for each question
            if ($this->usemoodletheme) {
                $canvas = "document.getElementById('middle-column')"; // moodle
            } else {
                $canvas = "document.getElementsByTagName('body')[0]"; // original
            }
            $id = $this->embed_object_id;
            $onload = $this->embed_object_onload;
            $insert = "\n"
            ."// fix canvas height, if necessary\n"
            ."	if (! window.quizport_mediafilter_loader){\n"
            ."		StretchCanvasToCoverContent();\n"
            ."	}\n"
            ."}\n"
            ."function StretchCanvasToCoverContent(skipTimeout){\n"
            ."	if (! skipTimeout){\n"
            ."		if (navigator.userAgent.indexOf('Firefox/3')>=0){\n"
            ."			var millisecs = 1000;\n"
            ."		} else {\n"
            ."			var millisecs = 500;\n"
            ."		}\n"
            ."		setTimeout('StretchCanvasToCoverContent(true)', millisecs);\n"
            ."		return;\n"
            ."	}\n"
            ."	var canvas = $canvas;\n"
            ."	if (canvas){\n"
            ."		var ids = new Array('Reading','ReadingDiv','MainDiv');\n"
            ."		var i_max = ids.length;\n"
            ."		for (var i=i_max-1; i>=0; i--){\n"
            ."			var obj = document.getElementById(ids[i]);\n"
            ."			if (obj){\n"
            ."				obj.style.height = ''; // reset height\n"
            ."			} else {\n"
            ."				ids.splice(i, 1); // remove this id\n"
            ."				i_max--;\n"
            ."			}\n"
            ."		}\n"
            ."		var b = 0;\n"
            ."		for (var i=0; i<i_max; i++){\n"
            ."			var obj = document.getElementById(ids[i]);\n"
            ."			b = Math.max(b, getOffset(obj,'Bottom'));\n"
            ."		}\n"
            ."		if (window.Segments) {\n" // JMix special
            ."			var obj = document.getElementById('D'+(Segments.length-1));\n"
            ."			if (obj) {\n"
            ."				b = Math.max(b, getOffset(obj,'Bottom'));\n"
            ."			}\n"
            ."		}\n"
            ."		if (b){\n"
            ."			setOffset(canvas, 'Bottom', b + 4);\n"
            ."			for (var i=0; i<i_max; i++){\n"
            ."				var obj = document.getElementById(ids[i]);\n"
            ."				setOffset(obj, 'Bottom', b);\n"
            ."			}\n"
            ."		}\n"
            ."	}\n"
            ;
            if ($this->navigation==QUIZPORT_NAVIGATION_EMBED) {
                // stretch container object/iframe
                $insert .= ''
                    ."	if (parent.$onload) {\n"
                    ."		parent.$onload(null, parent.document.getElementById('".$this->embed_object_id."'));\n"
                    ."	}\n"
                ;
            }
            $substr = substr_replace($substr, $insert, $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_HideFeedback(&$str, $start, $length) {
        global $CFG, $QUIZPORT;
        $substr = substr($str, $start, $length);

        // unhide <embed> elements on Chrome browser
        $search = "/(\s*)ShowElements\(true, 'object'\);/s";
        $replace = ''
            ."\\0\\1"
            ."if (C.chrome) {\\1"
            ."	ShowElements(true, 'embed');\\1"
            ."}"
        ;
        $substr = preg_replace($search, $replace, $substr, 1);

        $search = '/('.'\s*if \(Finished == true\){\s*)(?:.*?)(\s*})/s';
        if ($this->delay3==QUIZPORT_DELAY3_AFTEROK) {
            // -1 : send form only (do not set form values, as that has already been done)
            $replace = '\\1'.'HP.onunload(HP.status,-1);'.'\\2';
        } else {
            $replace = ''; // i.e. remove this if-block
        }
        $substr = preg_replace($search, $replace, $substr, 1);

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_ShowSpecialReadingForQuestion(&$str, $start, $length) {
        $replace = ''
            ."function ShowSpecialReadingForQuestion(){\n"
            ."	var ReadingDiv = document.getElementById('ReadingDiv');\n"
            ."	if (ReadingDiv){\n"
            ."		var ReadingText = null;\n"
            ."		var divs = ReadingDiv.getElementsByTagName('div');\n"
            ."		for (var i=0; i<divs.length; i++){\n"
            ."			if (divs[i].className=='ReadingText' || divs[i].className=='TempReadingText'){\n"
            ."				ReadingText = divs[i];\n"
            ."				break;\n"
            ."			}\n"
            ."		}\n"
            ."		if (ReadingText && HiddenReadingShown){\n"
            ."			SwapReadingTexts(ReadingText, HiddenReadingShown);\n"
            ."			ReadingText = HiddenReadingShown;\n"
            ."			HiddenReadingShown = false;\n"
            ."		}\n"
            ."		var HiddenReading = null;\n"
            ."		if (QArray[CurrQNum]){\n"
            ."			var divs = QArray[CurrQNum].getElementsByTagName('div');\n"
            ."			for (var i=0; i<divs.length; i++){\n"
            ."				if (divs[i].className=='HiddenReading'){\n"
            ."					HiddenReading = divs[i];\n"
            ."					break;\n"
            ."				}\n"
            ."			}\n"
            ."		}\n"
            ."		if (HiddenReading){\n"
            ."			if (! ReadingText){\n"
            ."				ReadingText = document.createElement('div');\n"
            ."				ReadingText.className = 'ReadingText';\n"
            ."				ReadingDiv.appendChild(ReadingText);\n"
            ."			}\n"
            ."			SwapReadingTexts(ReadingText, HiddenReading);\n"
            ."			HiddenReadingShown = ReadingText;\n"
            ."		}\n"
            ."		var btn = document.getElementById('ShowMethodButton');\n"
            ."		if (btn){\n"
            ."			if (HiddenReadingShown){\n"
            ."				if (btn.style.display!='none'){\n"
            ."					btn.style.display = 'none';\n"
            ."				}\n"
            ."			} else {\n"
            ."				if (btn.style.display=='none'){\n"
            ."					btn.style.display = '';\n"
            ."				}\n"
            ."			}\n"
            ."		}\n"
            ."		btn = null;\n"
            ."		ReadingDiv = null;\n"
            ."		ReadingText = null;\n"
            ."		HiddenReading = null;\n"
            ."	}\n"
            ."}\n"
            ."function SwapReadingTexts(ReadingText, HiddenReading) {\n"
            ."	HiddenReadingParentNode = HiddenReading.parentNode;\n"
            ."	HiddenReadingParentNode.removeChild(HiddenReading);\n"
            ."\n"
            ."	// replaceChild(new_node, old_node)\n"
            ."	ReadingText.parentNode.replaceChild(HiddenReading, ReadingText);\n"
            ."\n"
            ."	if (HiddenReading.IsOriginalReadingText){\n"
            ."		HiddenReading.className = 'ReadingText';\n"
            ."	} else {\n"
            ."		HiddenReading.className = 'TempReadingText';\n"
            ."	}\n"
            ."	HiddenReading.style.display = '';\n"
            ."\n"
            ."	if (ReadingText.className=='ReadingText'){\n"
            ."	    ReadingText.IsOriginalReadingText = true;\n"
            ."	} else {\n"
            ."	    ReadingText.IsOriginalReadingText = false;\n"
            ."	}\n"
            ."	ReadingText.style.display = 'none';\n"
            ."	ReadingText.className = 'HiddenReading';\n"
            ."\n"
            ."	HiddenReadingParentNode.appendChild(ReadingText);\n"
            ."	HiddenReadingParentNode = null;\n"
            ."}\n"
        ;
        $str = substr_replace($str, $replace, $start, $length);
    }

    function fix_js_CheckAnswers(&$str, $start, $length) {
        // JCloze, JCross, JMatch : CheckAnswers
        // JMix : CheckAnswer
        // JQuiz : CheckFinished
        $substr = substr($str, $start, $length);

        // intercept Checks, if necessary
        if ($insert = $this->get_stop_function_intercept()) {
            if ($pos = strpos($substr, '{')) {
                $substr = substr_replace($substr, $insert, $pos+1, 0);
            }
        }

        // add extra argument to function - so it can be called from the "Give Up" button
        $name = $this->get_stop_function_name();
        $search = '/(?<=function '.$name.'\()(.*?)(?=\))/e';
        $replace = '("\\1" ? "\\1," : "")."ForceQuizStatus"';
        $substr = preg_replace($search, $replace, $substr, 1);

        // add call to Finish function (including QuizStatus)
        $search = $this->get_stop_function_search();
        $replace = $this->get_stop_function_replace();
        $substr = preg_replace($search, $replace, $substr, 1);

        $str = substr_replace($str, $substr, $start, $length);
    }

    function get_stop_onclick() {
        if ($name = $this->get_stop_function_name()) {
            return 'if('.$this->get_stop_function_confirm().')'.$name.'('.$this->get_stop_function_args().')';
        } else {
            return 'if(window.HP)HP.onunload('.QUIZPORT_STATUS_ABANDONED.')';
        }
    }

    function get_stop_function_confirm() {
        // Note: "&&" in onclick must be encoded as html-entities for strict XHTML
        return ''
            ."confirm("
            ."'".$this->source->js_value_safe(get_string('confirmstop', 'quizport'), true)."'"
            ."+'\\n\\n'+(window.onbeforeunload &amp;&amp; onbeforeunload()?(onbeforeunload()+'\\n\\n'):'')+"
            ."'".$this->source->js_value_safe(get_string('pressoktocontinue', 'quizport'), true)."'"
            .")"
        ;
    }

    function get_stop_function_name() {
        // the name of the javascript function into which the "give up" code should be inserted
        return '';
    }

    function get_stop_function_args() {
        // the arguments required by the javascript function which the stop_function() code calls
        return QUIZPORT_STATUS_ABANDONED;
    }

    function get_stop_function_intercept() {
        // JMix and JQuiz each have their own version of this function
        return "\n"
            ."	// intercept this Check\n"
            ."	HP.onclickCheck();\n"
        ;
    }

    function get_stop_function_search() {
        // JCloze : AllCorrect || Finished
        // JCross : AllCorrect || TimeOver
        // JMatch : AllDone || TimeOver
        // JMix : AllDone || TimeOver (in the CheckAnswer function)
        // JQuiz : AllDone (in the CheckFinished function)
        return '/\s*if \(\((\w+) == true\)\|\|\(\w+ == true\)\)({).*?}\s*/s';
    }

    function get_stop_function_replace() {
        // $1 : name of the "all correct/done" variable
        // $2 : opening curly brace of if-block plus any following text to be kept

        if ($this->delay3==QUIZPORT_DELAY3_AFTEROK) {
            $flag = 1; // set form values only
        } else {
            $flag = 0; // set form values and send form
        }
        return "\n"
            ."	if (\\1){\n"
            ."		var QuizStatus = 4; // completed\n"
            ."	} else if (ForceQuizStatus){\n"
            ."		var QuizStatus = ForceQuizStatus; // 3=abandoned\n"
            ."	} else if (TimeOver){\n"
            ."		var QuizStatus = 2; // timed out\n"
            ."	} else {\n"
            ."		var QuizStatus = 1; // in progress\n"
            ."	}\n"
            ."	if (QuizStatus > 1) \\2\n"
            ."		if (window.Interval) {\n"
            ."			clearInterval(window.Interval);\n"
            ."		}\n"
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
    }

    function postprocessing() {
        $this->fix_title_icons();
        $this->fix_submissionform();
        $this->fix_navigation_buttons();
    }

    function fix_title() {
        if (preg_match($this->tagpattern('h2'), $this->bodycontent, $matches, PREG_OFFSET_CAPTURE)) {
            // $matches: <h2 $matches[1]>$matches[2]</h2>
            $start = $matches[2][1];
            $length = strlen($matches[2][0]);
            $this->bodycontent = substr_replace($this->bodycontent, $this->get_title(), $start, $length);
        }
    }

    function fix_title_icons() {
        global $QUIZPORT;

        // add quiz edit icons if the current user is a teacher/administrator
        if (has_capability('mod/quizport:manage', $QUIZPORT->modulecontext)) {
            if (preg_match($this->tagpattern('h2'), $this->bodycontent, $matches, PREG_OFFSET_CAPTURE)) {
                // $matches: <h2 $matches[1]>$matches[2]</h2>
                $start = $matches[2][1] + strlen($matches[2][0]);
                $icons = $QUIZPORT->print_commands(
                    // $types, $quizportscriptname, $id, $params, $popup, $return
                    array('update'), 'editquiz.php', 'quizid',
                    array('quizid'=>$QUIZPORT->quizid, 'qnumber'=>$QUIZPORT->qnumber, 'unumber'=>$QUIZPORT->unumber),
                    false, true
                );
                $this->bodycontent = substr_replace($this->bodycontent, $icons, $start, 0);
            }
        }
    }

    function fix_navigation_buttons() {
        global $QUIZPORT;

        if ($this->navigation==QUIZPORT_NAVIGATION_ORIGINAL) {
            // replace relative URLs in <button class="NavButton" ... onclick="location='...'">
            $search = '/'.'(?<='.'onclick="'."location='".')'."([^']*)".'(?='."'; return false;".'")'.'/ise';
            $replace = '$this->convert_url_navbutton("'.$this->source->baseurl.'","'.$this->source->filepath.'","\\1")';
            $this->bodycontent = preg_replace($search, $replace, $this->bodycontent);

            // replace history.back() in <button class="NavButton" ... onclick="history.back(); ...">
            // with a link to the previous quiz (by sortorder) in this QuizPort activity
            if ($QUIZPORT->get_quizzes()) {
                $quizids = array_keys($QUIZPORT->quizzes);
                if ($i = array_search($this->id, $quizids)) {
                    $quizid = $quizids[$i-1];
                } else {
                    // a back button, "<=", on the first quiz
                    $quizid = 0;
                }
                $params = array(
                    'quizid'=>$quizid, 'qnumber'=>0, 'quizattemptid'=>0, 'quizscoreid'=>0
                );
                $search = '/'.'(?<='.'onclick=")'.'history\.back\(\)'.'(?=; return false;")'.'/';
                $replace = "location='".$QUIZPORT->format_url('view.php', '', $params)."'";
                $this->bodycontent = preg_replace($search, $replace, $this->bodycontent);
            }
        }
    }

    function fix_TimeLimit() {
        if ($this->timelimit > 0) {
            $search = '/(?<=var Seconds = )\d+(?=;)/';
            $this->headcontent = preg_replace($search, $this->timelimit, $this->headcontent, 1);
        }
    }

    function fix_SubmissionTimeout() {
        if ($this->delay3==QUIZPORT_DELAY3_TEMPLATE) {
            // use default from source/template file (=30000 ms =30 seconds)
            if ($this->hasSubmissionTimeout) {
                $timeout = null;
            } else {
                $timeout = 30000; // = 30 secs is HP default
            }
        } else {
            if ($this->delay3 >= 0) {
                $timeout = $this->delay3 * 1000; // milliseconds
            } else {
                $timeout = 0; // i.e. immediately
            }
        }
        if (is_null($timeout)) {
            return; // nothing to do
        }
        if ($this->hasSubmissionTimeout) {
            // remove HPNStartTime
            $search = '/var HPNStartTime\b[^;]*?;\s*/';
            $this->headcontent = preg_replace($search, '', $this->headcontent, 1);

            // reset the value of SubmissionTimeout
            $search = '/(?<=var SubmissionTimeout = )\d+(?=;)/';
            $this->headcontent = preg_replace($search, $timeout, $this->headcontent, 1);
        } else {
            // Rhubarb, Sequitur and Quandary
            $search = '/var FinalScore = 0;/';
            $replace = '\\0'."\n".'var SubmissionTimeout = '.$timeout.';';
            $this->headcontent = preg_replace($search, $replace, $this->headcontent, 1);
        }
    }

    function fix_navigation() {
        if ($this->navigation==QUIZPORT_NAVIGATION_ORIGINAL) {
            // do nothing - leave navigation as it is
            return;
        }

        // insert the stop button, if required
        if ($this->stopbutton) {
            // replace top nav buttons with a single stop button
            if ($this->stopbutton==QUIZPORT_STOPBUTTON_LANGPACK) {
                if ($pos = strpos($this->stoptext, '_')) {
                    $mod = substr($this->stoptext, 0, $pos);
                    $str = substr($this->stoptext, $pos + 1);
                    $stoptext = get_string($str, $mod);
                } else if ($this->stoptext) {
                    $stoptext = get_string($this->stoptext);
                } else {
                    $stoptext = '';
                }
            } else {
                $stoptext = $this->stoptext;
            }
            if (trim($stoptext)=='') {
                $stoptext = get_string('giveup', 'quizport');
            }
            $confirm = get_string('confirmstop', 'quizport');
            //$search = '/<!-- BeginTopNavButtons -->'.'.*?'.'<!-- EndTopNavButtons -->/s';
            $search = '/<(div class="Titles")>/s';
            $replace = '<\\1 style="position: relative">'."\n\t"
                .'<div class="quizportstopbutton">'
                .'<button class="FuncButton" '
                    .'onclick="'.$this->get_stop_onclick().'" '
                    .'onfocus="FuncBtnOver(this)" onblur="FuncBtnOut(this)" '
                    .'onmouseover="FuncBtnOver(this)" onmouseout="FuncBtnOut(this)" '
                    .'onmousedown="FuncBtnDown(this)" onmouseup="FuncBtnOut(this)">'
                    .$this->source->utf8_to_entities($stoptext)
                .'</button>'
                .'</div>'
            ;
            $this->bodycontent = preg_replace($search, $replace, $this->bodycontent, 1);
        }

        // remove (remaining) navigation buttons
        $search = '/<!-- Begin(Top|Bottom)NavButtons -->'.'.*?'.'<!-- End'.'\\1'.'NavButtons -->/s';
        $this->bodycontent = preg_replace($search, '', $this->bodycontent);
    }

    function fix_filters() {
        global $CFG;
        if (isset($CFG->textfilters)) {
            $textfilters = $CFG->textfilters;
        } else {
            $textfilters = '';
        }
        if ($this->usefilters) {
            $filters = explode(',', $textfilters);
        } else {
            $filters = array();
        }
        if ($this->useglossary && ! in_array('mod/glossary', $filters)) {
            $filters[] = 'mod/glossary';
        }
        if ($this->usemediafilter) {
            // exclude certain unnecessary or miscreant $filters
            //  - "mediaplugins" because it duplicates work done by "usemediafilter" setting
            //  - "asciimath" because it does not behave like a filter is supposed to behave
            $filters = preg_grep('/^filter\/(mediaplugin|asciimath)$/', $filters, PREG_GREP_INVERT);
        }

        $CFG->textfilters = implode(',', $filters);
        $this->filter_text_headcontent();
        $this->filter_text_bodycontent();
        $CFG->textfilters = $textfilters;

        // fix unwanted conversions by the Moodle's Tex filter
        // http://moodle.org/mod/forum/discuss.php?d=68435
        // http://tracker.moodle.org/browse/MDL-7849
        if (preg_match('/jcross|jmix/', get_class($this))) {
            $search = '/(?<=replace\(\/)'.'<a[^>]*><img[^>]*class="texrender"[^>]*title="(.*?)"[^>]*><\/a>'.'(?=\/g)/is';
            $replace = '\['.'\\1'.'\]';
            $this->headcontent = preg_replace($search, $replace, $this->headcontent);
        }

        // make sure openpopup() function is available if needed (for glossary entries)
        if ($this->navigation==QUIZPORT_NAVIGATION_ORIGINAL && in_array('mod/glossary', $filters)) {
            // add openwindow() function (from lib/javascript.php)
            $this->headcontent .= "\n"
                .'<script type="text/javascript">'."\n"
                .'//<![CDATA['."\n"
                .'function openpopup(url, name, options, fullscreen) {'."\n"
                .'    var fullurl = "'.$CFG->httpswwwroot.'" + url;'."\n"
                .'    var windowobj = window.open(fullurl, name, options);'."\n"
                .'    if (!windowobj) {'."\n"
                .'        return true;'."\n"
                .'    }'."\n"
                .'    if (fullscreen) {'."\n"
                .'        windowobj.moveTo(0, 0);'."\n"
                .'        windowobj.resizeTo(screen.availWidth, screen.availHeight);'."\n"
                .'    }'."\n"
                .'    windowobj.focus();'."\n"
                .'    return false;'."\n"
                .'}'."\n"
                .'//]]>'."\n"
                .'</script>'
            ;
        }
    }

    function filter_text_headcontent() {
        if ($this->headcontent_strings) {
            $search = "/^((?:var )?(?:".$this->headcontent_strings.")(?:\[\d+\])*\s*=\s*)'(.*)'(;)$/me";
            $replace = '"\\1'."'".'".$this->filter_text_headcontent_string("\\2")."'."'".'\\3"';
            $this->headcontent = preg_replace($search, $replace, $this->headcontent);
        }
        if ($this->headcontent_arrays) {
            $search = "/^((?:var )?(?:".$this->headcontent_arrays.")(?:\[\d+\])* = new Array\()(.*)(\);)$/me";
            $replace = '"\\1".$this->filter_text_headcontent_array("\\2")."\\3"';
            $this->headcontent = preg_replace($search, $replace, $this->headcontent);
        }
    }

    function filter_text_headcontent_array($str, $quote="'") {
        // I[q][0][a] = new Array('JQuiz answer text', 'feedback', 0, 0, 0)
        if ($quote) {
            $str = str_replace('\\'.$quote, $quote, $str);
        }
        $search = "/(?<=')(?:\\\\\\\\|\\\\'|[^'])*(?=')/e";
        $replace = '$this->filter_text_headcontent_string("\\0")';
        return preg_replace($search, $replace, $str);
    }

    function filter_text_headcontent_string($str, $quote="'") {
        // var YourScoreIs = 'Your score is';
        // I[q][1][a][2] = 'JCloze clue';
        global $CFG;
        if ($quote) {
            $str = str_replace('\\'.$quote, $quote, $str);
        }
        static $replace_pairs = array(
            // backslashes and quotes
            '\\\\'=>'\\', "\\'"=>"'", '\\"'=>'"',
            // newlines
            '\\n'=>"\n",
            // other (closing tag is for XHTML compliance)
            '\\0'=>"\0", '<\\/'=>'</'
        );

        // unescape backslashes, quote and newlines
        $str = strtr($str, $replace_pairs);

        // convert javascript unicode
        $search = '/\\\\u([0-9a-f]{4})/ie';
        $replace = '$this->source->dec_to_utf8(hexdec("\\1"))';
        $str = preg_replace($search, $replace, $str);

        // fix relative urls, filter string,
        // and return safe javascript unicode
        $str = filter_text($this->fix_relativeurls($str));
        return $this->source->js_value_safe($str, true);
    }

    function filter_text_bodycontent() {
        // convert html entities to unicode
        $search = '/&#x([0-9a-f]+);/ie';
        $replace = '$this->source->dec_to_utf8(hexdec("\\1"))';
        $this->bodycontent = preg_replace($search, $replace, $this->bodycontent);

        // filter text and convert utf8 back to html entities
        $this->bodycontent = filter_text($this->bodycontent);
        $this->bodycontent = $this->source->utf8_to_entities($this->bodycontent);
    }

    function fix_feedbackform() {
        // we are aiming to generate the following javascript to send to the client
        //FEEDBACK = new Array();
        //FEEDBACK[0] = ''; // url of feedback page/script
        //FEEDBACK[1] = ''; // array of array('teachername', 'value');
        //FEEDBACK[2] = ''; // 'student name' [formmail only]
        //FEEDBACK[3] = ''; // 'student email' [formmail only]
        //FEEDBACK[4] = ''; // window width
        //FEEDBACK[5] = ''; // window height
        //FEEDBACK[6] = ''; // 'Send a message to teacher' [prompt/button text]
        //FEEDBACK[7] = ''; // 'Title'
        //FEEDBACK[8] = ''; // 'Teacher'
        //FEEDBACK[9] = ''; // 'Message'
        //FEEDBACK[10] = ''; // 'Close this window'

        global $CFG, $USER, $QUIZPORT;

        $feedback = array();
        switch ($this->studentfeedback) {
            case QUIZPORT_FEEDBACK_NONE:
                // do nothing - feedback form is not required
                break;

            case QUIZPORT_FEEDBACK_WEBPAGE:
                if (! $this->studentfeedbackurl) {
                    $this->studentfeedback = QUIZPORT_FEEDBACK_NONE;
                } else {
                    $feedback[0] = "'$this->studentfeedbackurl'";
                }
                break;

            case QUIZPORT_FEEDBACK_FORMMAIL:
                if ($this->studentfeedbackurl) {
                    $teachers = $this->get_feedback_teachers();
                } else {
                    $teachers = '';
                }
                if ($teachers) {
                    $feedback[0] = "'".addslashes_js($this->studentfeedbackurl)."'";
                    $feedback[1] = $teachers;
                    $feedback[2] = "'".addslashes_js(fullname($USER))."'";
                    $feedback[3] = "'".addslashes_js($USER->email)."'";
                    $feedback[4] = 500; // width
                    $feedback[5] = 300; // height
                } else {
                    // no teachers (or no feedback url)
                    $this->studentfeedback = QUIZPORT_FEEDBACK_NONE;
                }
                break;

            case QUIZPORT_FEEDBACK_MOODLEFORUM:
                $found = false;
                if ($modinfo = unserialize($QUIZPORT->courserecord->modinfo)) {
                    foreach ($modinfo as $cmid => $mod) {
                        if ($mod->mod=='forum' && $mod->visible) {
                            $found = true;
                            break;
                        }
                    }
                }
                if ($found) {
                    $feedback[0] = "'".$CFG->wwwroot.'/mod/forum/index.php?id='.$this->source->courseid."'";
                } else {
                    // no forums
                    $this->studentfeedback = QUIZPORT_FEEDBACK_NONE;
                }
                break;

            case QUIZPORT_FEEDBACK_MOODLEMESSAGING:
                if ($CFG->messaging) {
                    $teachers = $this->get_feedback_teachers();
                } else {
                    $teachers = '';
                }
                if ($teachers) {
                    $feedback[0] = "'$CFG->wwwroot/message/discussion.php?id='";
                    $feedback[1] = $teachers;
                    $feedback[4] = 400; // width
                    $feedback[5] = 500; // height
                } else {
                    // no teachers (or no Moodle messaging)
                    $this->studentfeedback = QUIZPORT_FEEDBACK_NONE;
                }
                break;

            default:
                // unrecognized feedback setting, so reset it to something valid
                $this->studentfeedback = QUIZPORT_FEEDBACK_NONE;
        }
        if ($this->studentfeedback==QUIZPORT_FEEDBACK_NONE) {
            // do nothing - feedback form is not required
        } else {
            // complete remaining feedback fields
            $feedback[6] = "'".addslashes_js(get_string('feedbacksendmessagetoteacher', 'quizport'))."'";
            $feedback[7] = "'".addslashes_js(get_string('feedback'))."'";
            $feedback[8] = "'".addslashes_js(get_string('defaultcourseteacher'))."'";
            $feedback[9] = "'".addslashes_js(get_string('messagebody'))."'";
            $feedback[10] = "'".addslashes_js(get_string('closewindow'))."'";
            $js = '';
            foreach ($feedback as $i=>$str) {
                $js .= 'FEEDBACK['.$i."] = $str;\n";
            }
            $js = '<script type="text/javascript">'."\n//<![CDATA[\n"."FEEDBACK = new Array();\n".$js."//]]>\n</script>\n";
            if ($this->usemoodletheme) {
                $this->headcontent .= $js;
            } else {
                $this->bodycontent = preg_replace('/<\/head>/i', "$js</head>", $this->bodycontent, 1);
            }
        }
    }

    function get_feedback_teachers() {
        $context = get_context_instance(CONTEXT_COURSE, $this->source->courseid);
        $teachers = get_users_by_capability($context, 'mod/quizport:grade');

        $details = array();
        if (isset($teachers) && count($teachers)) {
            if ($this->studentfeedback==QUIZPORT_FEEDBACK_MOODLEMESSAGING) {
                $detail = 'id';
            } else {
                $detail = 'email';
            }
            foreach ($teachers as $teacher) {
                $details[] = "new Array('".addslashes_js(fullname($teacher))."', '".addslashes_js($teacher->$detail)."')";
            }
        }

        if ($details = implode(', ', $details)) {
            return 'new Array('.$details.')';
        } else {
            return '';
        }
    }

    function fix_reviewoptions() {
        // enable / disable review options
    }

    function fix_submissionform() {
        global $QUIZPORT;
        $params = array(
            'id' => $QUIZPORT->quizattemptid,
            $this->scorefield => '0', 'detail' => '0', 'status' => '0',
            'starttime' => '0', 'endtime' => '0', 'redirect' => '0',
        );
        $search = '/(<!-- BeginSubmissionForm -->)\s*(.*?)\s*(<!-- EndSubmissionForm -->)/s';
        $replace = ''
            .'\\1'."\n"
            .$QUIZPORT->print_form_start('view.php', $params, false, true, array('id'=>$this->formid, 'autocomplete'=>'off'))
            .$QUIZPORT->print_form_end(true)
            .'\\3'
        ;
        if (! preg_match($search, $this->bodycontent)) {
            $QUIZPORT->print_error(get_string('couldnotinsertsubmissionform', 'quizport'));
        }
        $this->bodycontent = preg_replace($search, $replace, $this->bodycontent, 1);
    }

    function fix_mediafilter_onload_extra() {
        return ''
            ."\n"
            .'  // fix canvas height, if necessary'."\n"
            .'  if(window.StretchCanvasToCoverContent) {'."\n"
            .'    StretchCanvasToCoverContent();'."\n"
            .'  }'."\n"
            ."\n"
        ;
    }

    function xml_set_htmlcontent() {

        // get the xml source
        if (! $this->source->xml_get_filecontents()) {
            // could not detect Hot Potatoes quiz type in xml file - shouldn't happen !!
            return false;
        }

        if (! $this->htmlcontent = $this->expand_template($this->templatefile)) {
            // some problem accessing the main template for this quiz type
            return false;
        }

        if ($this->templatestrings) {
            $this->expand_strings($this->htmlcontent, '/\[('.$this->templatestrings.')\]/ise');
        }

        // all done
        return true;
    }

    // captions and messages

    function expand_AlsoCorrect() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',also-correct');
    }
    function expand_BottomNavBar() {
        return $this->expand_NavBar('BottomNavBar');
    }
    function expand_CapitalizeFirst() {
        return $this->source->xml_value_bool($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',capitalize-first-letter');
    }
    function expand_CheckCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,check-caption');
    }
    function expand_ContentsURL() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,contents-url');
    }
    function expand_CorrectIndicator() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,global,correct-indicator');
    }
    function expand_Back() {
        return $this->source->xml_value_int($this->source->hbs_software.'-config-file,global,include-back');
    }
    function expand_BackCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,back-caption');
    }
    function expand_CaseSensitive() {
        return $this->source->xml_value_bool($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',case-sensitive');
    }
    function expand_ClickToAdd() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',click-to-add');
    }
    function expand_ClueCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,clue-caption');
    }
    function expand_Clues() {
        // Note: WinHotPot6 uses "include-clues", but JavaHotPotatoes6 uses "include-clue" (missing "s")
        return $this->source->xml_value_int($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',include-clues');
    }
    function expand_Contents() {
        return $this->source->xml_value_int($this->source->hbs_software.'-config-file,global,include-contents');
    }
    function expand_ContentsCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,contents-caption');
    }
    function expand_Correct() {
        if ($this->source->hbs_quiztype=='jcloze') {
            $tag = 'guesses-correct';
        } else {
            $tag = 'guess-correct';
        }
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.','.$tag);
    }
    function expand_DeleteCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',delete-caption');
    }
    function expand_DublinCoreMetadata() {
        $dc = '';
        if ($str = $this->source->xml_value('', "['rdf:RDF'][0]['@']['xmlns:dc']")) {
            $dc .= '<link rel="schema.DC" href="'.str_replace('"', '&quot;', $str).'" />'."\n";
        }
        if (is_string($this->source->xml_value('rdf:RDF,rdf:Description'))) {
            // do nothing (there is no more dc info)
        } else {
            if ($str = $this->source->xml_value('rdf:RDF,rdf:Description,dc:creator')) {
                $dc .= '<meta name="DC:Creator" content="'.str_replace('"', '&quot;', $str).'" />'."\n";
            }
            if ($str = strip_tags($this->source->xml_value('rdf:RDF,rdf:Description,dc:title'))) {
                $dc .= '<meta name="DC:Title" content="'.str_replace('"', '&quot;', $str).'" />'."\n";
            }
        }
        return $dc;
    }
    function expand_EMail() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,email');
    }
    function expand_EscapedExerciseTitle() {
        // this string only used in resultsp6sendresults.js_
        // which is not required in Moodle
        return $this->source->xml_value_js('data,title');
    }
    function expand_ExBGColor() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,ex-bg-color');
    }
    function expand_ExerciseSubtitle() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',exercise-subtitle');
    }
    function expand_ExerciseTitle() {
        return $this->source->xml_value('data,title');
    }
    function expand_FontFace() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,font-face');
    }
    function expand_FontSize() {
        $value = $this->source->xml_value($this->source->hbs_software.'-config-file,global,font-size');
        return (empty($value) ? 'small' : $value);
    }
    function expand_FormMailURL() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,formmail-url');
    }
    function expand_FullVersionInfo() {
        global $CFG;
        return $this->source->xml_value('version').'.x (Moodle '.$CFG->release.', QuizPort '.quizport_get_module_info('release').')';
    }
    function expand_FuncLightColor() { // top-left of buttons
        $color = $this->source->xml_value($this->source->hbs_software.'-config-file,global,ex-bg-color');
        return $this->expand_halfway_color($color, '#ffffff');
    }
    function expand_FuncShadeColor() { // bottom right of buttons
        $color = $this->source->xml_value($this->source->hbs_software.'-config-file,global,ex-bg-color');
        return $this->expand_halfway_color($color, '#000000');
    }
    function expand_GiveHint() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',next-correct-letter');
    }
    function expand_GraphicURL() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,graphic-url');
    }
    function expand_GuessCorrect() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',guess-correct');
    }
    function expand_GuessIncorrect() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',guess-incorrect');
    }
    function expand_HeaderCode() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,header-code');
    }
    function expand_Hint() {
        return $this->source->xml_value_int($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',include-hint');
    }
    function expand_HintCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,hint-caption');
    }
    function expand_Incorrect() {
        if ($this->source->hbs_quiztype=='jcloze') {
            $tag = 'guesses-incorrect';
        } else {
            $tag = 'guess-incorrect';
        }
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.','.$tag);
    }
    function expand_IncorrectIndicator() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,global,incorrect-indicator');
    }
    function expand_Instructions() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',instructions');
    }
    function expand_JSBrowserCheck() {
        return $this->expand_template('hp6browsercheck.js_');
    }
    function expand_JSButtons() {
        return $this->expand_template('hp6buttons.js_');
    }
    function expand_JSCard() {
        return $this->expand_template('hp6card.js_');
    }
    function expand_JSCheckShortAnswer() {
        return $this->expand_template('hp6checkshortanswer.js_');
    }
    function expand_JSHotPotNet() {
        return $this->expand_template('hp6hotpotnet.js_');
    }
    function expand_JSSendResults() {
        return $this->expand_template('hp6sendresults.js_');
    }
    function expand_JSShowMessage() {
        return $this->expand_template('hp6showmessage.js_');
    }
    function expand_JSTimer() {
        return $this->expand_template('hp6timer.js_');
    }
    function expand_JSUtilities() {
        return $this->expand_template('hp6utilities.js_');
    }
    function expand_LastQCaption() {
        $caption = $this->source->xml_value($this->source->hbs_software.'-config-file,global,last-q-caption');
        return ($caption=='<=' ? '&lt;=' : $caption);
    }
    function expand_LinkColor() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,link-color');
    }
    function expand_NamePlease() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,global,name-please');
    }
    function expand_NavBar($navbarid='') {
        $this->navbarid = $navbarid;
        $navbar = $this->expand_template('hp6navbar.ht_');
        unset($this->navbarid);
        return $navbar;
    }
    function expand_NavBarID() {
        // $this->navbarid is set in "$this->expand_NavBar"
        return empty($this->navbarid) ? '' : $this->navbarid;
    }
    function expand_NavBarJS() {
        return $this->expand_NavButtons();
    }
    function expand_NavButtons() {
        return ($this->expand_Back() || $this->expand_NextEx() || $this->expand_Contents());
    }
    function expand_NavTextColor() {
        // might be 'title-color' ?
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,text-color');
    }
    function expand_NavBarColor() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,nav-bar-color');
    }
    function expand_NavLightColor() {
        $color = $this->source->xml_value($this->source->hbs_software.'-config-file,global,nav-bar-color');
        return $this->expand_halfway_color($color, '#ffffff');
    }
    function expand_NavShadeColor() {
        $color = $this->source->xml_value($this->source->hbs_software.'-config-file,global,nav-bar-color');
        return $this->expand_halfway_color($color, '#000000');
    }
    function expand_NextCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',next-caption');
    }
    function expand_NextCorrect() {
        $tags = $this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype;
        if (! $value = $this->source->xml_value_js($tags.',next-correct-part')) {
            // jquiz
            $value = $this->source->xml_value_js($tags.',next-correct-letter');
        }
        return $value;
    }
    function expand_NextEx() {
        return $this->source->xml_value_int($this->source->hbs_software.'-config-file,global,include-next-ex');
    }
    function expand_NextExCaption() {
        $caption = $this->source->xml_value($this->source->hbs_software.'-config-file,global,next-ex-caption');
        return ($caption=='=>' ? '=&gt;' : $caption);
    }
    function expand_NextQCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,next-q-caption');
    }
    function expand_NextExURL() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',next-ex-url');
    }
    function expand_OKCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,ok-caption');
    }
    function expand_PageBGColor() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,page-bg-color');
    }
    function expand_PlainTitle() {
        return $this->source->xml_value('data,title');
    }
    function expand_PreloadImages() {
        $value = $this->expand_PreloadImageList();
        return empty($value) ? false : true;
    }
    function expand_PreloadImageList() {
        if (! isset($this->PreloadImageList)) {
            $this->PreloadImageList = '';

            $images = array();

            // extract all src values from <img> tags in the xml file
            $search = '/&amp;#x003C;img.*?src=&quot;(.*?)&quot;.*?&amp;#x003E;/is';
            if (preg_match_all($search, $this->source->filecontents, $matches)) {
                $images = array_merge($images, $matches[1]);
            }

            // extract all urls from QuizPort's [square bracket] notation
            // e.g. [%sitefiles%/images/screenshot.jpg image 350 265 center]
            $search = '/\['."([^\?\]]*\.(?:jpg|gif|png)(?:\?[^ \t\r\n\]]*)?)".'[^\]]*'.'\]/s';
            if (preg_match_all($search, $this->source->filecontents, $matches)) {
                $images = array_merge($images, $matches[1]);
            }

            if (count($images)) {
                $images = array_unique($images);
                $this->PreloadImageList = "\n\t\t'".implode("',\n\t\t'", $images)."'\n\t";
            }
        }
        return $this->PreloadImageList;
    }
    function expand_Reading() {
        return $this->source->xml_value_int('data,reading,include-reading');
    }
    function expand_ReadingText() {
        $title = $this->expand_ReadingTitle();
        if ($value = $this->source->xml_value('data,reading,reading-text')) {
            $value = '<div class="ReadingText">'.$value.'</div>';
        } else {
            $value = '';
        }
        return $title.$value;
    }
    function expand_ReadingTitle() {
        $value = $this->source->xml_value('data,reading,reading-title');
        return empty($value) ? '' : ('<h3 class="ExerciseSubtitle">'.$value.'</h3>');
    }
    function expand_Restart() {
        return $this->source->xml_value_int($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',include-restart');
    }
    function expand_RestartCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,restart-caption');
    }
    function expand_Scorm12() {
        return false; // HP scorm functionality is always disabled in Moodle
    }
    function expand_Seconds() {
        return $this->source->xml_value('data,timer,seconds');
    }
    function expand_SendResults() {
        return false; // send results (via formmail) is always disabled in Moodle
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',send-email');
    }
    function expand_ShowAllQuestionsCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,show-all-questions-caption');
    }
    function expand_ShowAnswer() {
        return $this->source->xml_value_int($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',include-show-answer');
    }
    function expand_ShowOneByOneCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,show-one-by-one-caption');
    }
    function expand_StyleSheet() {
        return $this->expand_template('hp6.cs_');
    }
    function expand_TextColor() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,text-color');
    }
    function expand_TheseAnswersToo() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',also-correct');
    }
    function expand_ThisMuch() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',this-much-correct');
    }
    function expand_Timer() {
        if ($this->timelimit < 0) {
            // use setting in source file
            return $this->source->xml_value_int('data,timer,include-timer');
        } else {
            // override setting in source file
            return $this->timelimit;
        }
    }
    function expand_TimesUp() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,global,times-up');
    }
    function expand_TitleColor() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,title-color');
    }
    function expand_TopNavBar() {
        return $this->expand_NavBar('TopNavBar');
    }
    function expand_Undo() {
        return $this->source->xml_value_int($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',include-undo');
    }
    function expand_UndoCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,undo-caption');
    }
    function expand_UserDefined1() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,user-string-1');
    }
    function expand_UserDefined2() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,user-string-2');
    }
    function expand_UserDefined3() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,user-string-3');
    }
    function expand_VLinkColor() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,global,vlink-color');
    }
    function expand_YourScoreIs() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,global,your-score-is');
    }

    function expand_Keypad() {
        $str = '';
        if ($this->source->xml_value_int($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',include-keypad')) {

            // these characters must always be in the keypad
            $chars = array();
            $this->add_keypad_chars($chars, $this->source->xml_value($this->source->hbs_software.'-config-file,global,keypad-characters'));

            // append other characters used in the answers
            switch ($this->source->hbs_quiztype) {
                case 'jcloze':
                    $tags = 'data,gap-fill,question-record';
                    break;
                case 'jquiz':
                    $tags = 'data,questions,question-record';
                    break;
                case 'rhubarb':
                    $tags = 'data,rhubarb-text';
                    break;
                default:
                    $tags = '';
            }
            if ($tags) {
                $q = 0;
                while (($question="[$q]['#']") && $this->source->xml_value($tags, $question)) {

                    if ($this->source->hbs_quiztype=='jquiz') {
                        $answers = $question."['answers'][0]['#']";
                    } else {
                        $answers = $question;
                    }

                    $a = 0;
                    while (($answer=$answers."['answer'][$a]['#']") && $this->source->xml_value($tags, $answer)) {
                        $this->add_keypad_chars($chars, $this->source->xml_value($tags,  $answer."['text'][0]['#']"));
                        $a++;
                    }
                    $q++;
                }
            }

            // remove duplicate characters and sort
            $chars = array_unique($chars);
            usort($chars, "quizport_keypad_chars_sort");

            // create keypad buttons for each character
            foreach ($chars as $char) {
                $str .= "<button onclick=\"TypeChars('".$this->source->js_value_safe($char, true)."'); return false;\">$char</button>";
            }
        }
        return $str;
    }
    function add_keypad_chars(&$chars, $text) {
        if (preg_match_all('|&[^;]+;|i', $text, $more_chars)) {
            $chars = array_merge($chars, $more_chars[0]);
        }
    }

    // JCloze

    function expand_JSJCloze6() {
        return $this->expand_template('jcloze6.js_');
    }
    function expand_ClozeBody() {
        $str = '';

        // get drop down list of words, if required
        $dropdownlist = '';
        if ($this->use_DropDownList()) {
            $this->set_WordList();
            foreach ($this->wordlist as $word) {
                $dropdownlist .= '<option value="'.$word.'">'.$word.'</option>';
            }
        }

        // cache clues flag and caption
        $includeclues = $this->expand_Clues();
        $cluecaption = $this->expand_ClueCaption();

        // detect if cloze starts with gap
        if (strpos($this->source->filecontents, '<gap-fill><question-record>')) {
            $startwithgap = true;
        } else {
            $startwithgap = false;
        }

        // initialize loop values
        $q = 0;
        $tags = 'data,gap-fill';
        $question_record = "$tags,question-record";

        // initialize loop values
        $q = 0;
        $tags = 'data,gap-fill';
        $question_record = "$tags,question-record";

        // loop through text and gaps
        $looping = true;
        while ($looping) {
            $text = $this->source->xml_value($tags, "[0]['#'][$q]");
            $gap = '';
            if (($question="[$q]['#']") && $this->source->xml_value($question_record, $question)) {
                $gap .= '<span class="GapSpan" id="GapSpan'.$q.'">';
                if ($this->use_DropDownList()) {
                    $gap .= '<select id="Gap'.$q.'"><option value=""></option>'.$dropdownlist.'</select>';
                } else {
                    // minimum gap size
                    if (! $gapsize = $this->source->xml_value_int($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',minimum-gap-size')) {
                        $gapsize = 6;
                    }

                    // increase gap size to length of longest answer for this gap
                    $a = 0;
                    while (($answer=$question."['answer'][$a]['#']") && $this->source->xml_value($question_record, $answer)) {
                        $answertext = $this->source->xml_value($question_record,  $answer."['text'][0]['#']");
                        $answertext = preg_replace('/&[#a-zA-Z0-9]+;/', 'x', $answertext);
                        $gapsize = max($gapsize, strlen($answertext));
                        $a++;
                    }

                    $gap .= '<input type="text" id="Gap'.$q.'" onfocus="TrackFocus('.$q.')" onblur="LeaveGap()" class="GapBox" size="'.$gapsize.'"></input>';
                }
                if ($includeclues) {
                    $clue = $this->source->xml_value($question_record, $question."['clue'][0]['#']");
                    if (strlen($clue)) {
                        $gap .= '<button style="line-height: 1.0" class="FuncButton" onfocus="FuncBtnOver(this)" onmouseover="FuncBtnOver(this)" onblur="FuncBtnOut(this)" onmouseout="FuncBtnOut(this)" onmousedown="FuncBtnDown(this)" onmouseup="FuncBtnOut(this)" onclick="ShowClue('.$q.')">'.$cluecaption.'</button>';
                    }
                }
                $gap .= '</span>';
            }
            if (strlen($text) || strlen($gap)) {
                if ($startwithgap) {
                    $str .= $gap.$text;
                } else {
                    $str .= $text.$gap;
                }
                $q++;
            } else {
                // no text or gap, so force end of loop
                $looping = false;
            }
        }
        if ($q==0) {
            // oops, no gaps found!
            return $this->source->xml_value($tags);
        } else {
            return $str;
        }
    }
    function expand_ItemArray() {
        // this method is overridden by JCloze and JQuiz output formats
    }
    function expand_WordList() {
        $str = '';
        if ($this->include_WordList()) {
            $this->set_WordList();
            $str = implode(' &#160;&#160; ', $this->wordlist);
        }
        return $str;
    }
    function include_WordList() {
        return $this->source->xml_value_int($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',include-word-list');
    }
    function use_DropDownList() {
        return $this->source->xml_value_int($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',use-drop-down-list');
    }
    function set_WordList() {

        if (isset($this->wordlist)) {
            // do nothing
        } else {
            $this->wordlist = array();

            // is the wordlist required
            if ($this->include_WordList() || $this->use_DropDownList()) {

                $q = 0;
                $tags = 'data,gap-fill,question-record';
                while (($question="[$q]['#']") && $this->source->xml_value($tags, $question)) {
                    $a = 0;
                    $aa = 0;
                    while (($answer=$question."['answer'][$a]['#']") && $this->source->xml_value($tags, $answer)) {
                        $text = $this->source->xml_value($tags,  $answer."['text'][0]['#']");
                        $correct =  $this->source->xml_value_int($tags, $answer."['correct'][0]['#']");
                        if (strlen($text) && $correct) { // $correct is always true
                            $this->wordlist[] = $text;
                            $aa++;
                        }
                        $a++;
                    }
                    $q++;
                }
                $this->wordlist = array_unique($this->wordlist);
                sort($this->wordlist);
            }
        }
    }

    // jcross

    function expand_JSJCross6() {
        return $this->expand_template('jcross6.js_');
    }
    function expand_CluesAcrossLabel() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',clues-across');
    }
    function expand_CluesDownLabel() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',clues-down');
    }
    function expand_EnterCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',enter-caption');
    }
    function expand_ShowHideClueList() {
        $value = $this->source->xml_value_int($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',include-clue-list');
        return empty($value) ? ' style="display: none;"' : '';
    }
    function expand_CluesDown() {
        return $this->expand_jcross_clues('D');
    }
    function expand_CluesAcross() {
        return $this->expand_jcross_clues('A');
    }
    function expand_jcross_clues($direction) {
        // $direction: A(cross) or D(own)
        $row = null;
        $r_max = 0;
        $c_max = 0;
        $this->get_jcross_grid($row, $r_max, $c_max);

        $clue_i = 0; // clue index;
        $str = '';
        for ($r=0; $r<=$r_max; $r++) {
            for ($c=0; $c<=$c_max; $c++) {
                $aword = $this->get_jcross_aword($row, $r, $r_max, $c, $c_max);
                $dword = $this->get_jcross_dword($row, $r, $r_max, $c, $c_max);
                if ($aword || $dword) {
                    $clue_i++; // increment clue index

                    // get the definition for this word
                    $def = '';
                    $word = ($direction=='A') ? $aword : $dword;

                    $i = 0;
                    $clues = 'data,crossword,clues,item';
                    while (($clue = "[$i]['#']") && $this->source->xml_value($clues, $clue)) {
                        if ($word==$this->source->xml_value($clues, $clue."['word'][0]['#']")) {
                            $def = $this->source->xml_value($clues, $clue."['def'][0]['#']");
                            break;
                        }
                        $i++;
                    }
                    if ($def) {
                        $str .= '<tr><td class="ClueNum">'.$clue_i.'. </td><td id="Clue_'.$direction.'_'.$clue_i.'" class="Clue">'.$def.'</td></tr>';
                    }
                }
            }
        }
        return $str;
    }
    function expand_LetterArray() {
        $row = null;
        $r_max = 0;
        $c_max = 0;
        $this->get_jcross_grid($row, $r_max, $c_max);

        $str = '';
        for ($r=0; $r<=$r_max; $r++) {
            $str .= "L[$r] = new Array(";
            for ($c=0; $c<=$c_max; $c++) {
                $str .= ($c>0 ? ',' : '')."'".$this->source->js_value_safe($row[$r]['cell'][$c]['#'], true)."'";
            }
            $str .= ");\n";
        }
        return $str;
    }
    function expand_GuessArray() {
        $row = null;
        $r_max = 0;
        $c_max = 0;
        $this->get_jcross_grid($row, $r_max, $c_max);

        $str = '';
        for ($r=0; $r<=$r_max; $r++) {
            $str .= "G[$r] = new Array('".str_repeat("','", $c_max)."');\n";
        }
        return $str;
    }
    function expand_ClueNumArray() {
        $row = null;
        $r_max = 0;
        $c_max = 0;
        $this->get_jcross_grid($row, $r_max, $c_max);

        $i = 0; // clue index
        $str = '';
        for ($r=0; $r<=$r_max; $r++) {
            $str .= "CL[$r] = new Array(";
            for ($c=0; $c<=$c_max; $c++) {
                if ($c>0) {
                    $str .= ',';
                }
                $aword = $this->get_jcross_aword($row, $r, $r_max, $c, $c_max);
                $dword = $this->get_jcross_dword($row, $r, $r_max, $c, $c_max);
                if (empty($aword) && empty($dword)) {
                    $str .= 0;
                } else {
                    $i++; // increment the clue index
                    $str .= $i;
                }
            }
            $str .= ");\n";
        }
        return $str;
    }
    function expand_GridBody() {
        $row = null;
        $r_max = 0;
        $c_max = 0;
        $this->get_jcross_grid($row, $r_max, $c_max);

        $i = 0; // clue index;
        $str = '';
        for ($r=0; $r<=$r_max; $r++) {
            $str .= '<tr id="Row_'.$r.'">';
            for ($c=0; $c<=$c_max; $c++) {
                if (empty($row[$r]['cell'][$c]['#'])) {
                    $str .= '<td class="BlankCell">&nbsp;</td>';
                } else {
                    $aword = $this->get_jcross_aword($row, $r, $r_max, $c, $c_max);
                    $dword = $this->get_jcross_dword($row, $r, $r_max, $c, $c_max);
                    if (empty($aword) && empty($dword)) {
                        $str .= '<td class="LetterOnlyCell"><span id="L_'.$r.'_'.$c.'">&nbsp;</span></td>';
                    } else {
                        $i++; // increment clue index
                        $str .= '<td class="NumLetterCell"><a href="javascript:void(0);" class="GridNum" onclick="ShowClue('.$i.','.$r.','.$c.')">'.$i.'</a><span class="NumLetterCellText" id="L_'.$r.'_'.$c.'" onclick="ShowClue('.$i.','.$r.','.$c.')">&nbsp;&nbsp;&nbsp;</span></td>';
                    }
                }
            }
            $str .= '</tr>';
        }
        return $str;
    }
    function get_jcross_grid(&$rows, &$r_max, &$c_max) {
        $r_max = 0;
        $c_max = 0;

        $r = 0;
        $tags = 'data,crossword,grid,row';
        while (($moretags="[$r]['#']") && $row = $this->source->xml_value($tags, $moretags)) {
            $rows[$r] = $row;
            for ($c=0; $c<count($row['cell']); $c++) {
                if (! empty($row['cell'][$c]['#'])) {
                    $r_max = max($r, $r_max);
                    $c_max = max($c, $c_max);
                }
            }
            $r++;
        }
    }
    function get_jcross_dword(&$row, $r, $r_max, $c, $c_max) {
        $str = '';
        if (($r==0 || empty($row[$r-1]['cell'][$c]['#'])) && $r<$r_max && !empty($row[$r+1]['cell'][$c]['#'])) {
            $str = $this->get_jcross_word($row, $r, $r_max, $c, $c_max, true);
        }
        return $str;
    }
    function get_jcross_aword(&$row, $r, $r_max, $c, $c_max) {
        $str = '';
        if (($c==0 || empty($row[$r]['cell'][$c-1]['#'])) && $c<$c_max && !empty($row[$r]['cell'][$c+1]['#'])) {
            $str = $this->get_jcross_word($row, $r, $r_max, $c, $c_max, false);
        }
        return $str;
    }
    function get_jcross_word(&$row, $r, $r_max, $c, $c_max, $go_down=false) {
        $str = '';
        while ($r<=$r_max && $c<=$c_max && !empty($row[$r]['cell'][$c]['#'])) {
            $str .= $row[$r]['cell'][$c]['#'];
            if ($go_down) {
                $r++;
            } else {
                $c++;
            }
        }
        return $str;
    }

    // jmatch

    function expand_JSJMatch6() {
        return $this->expand_template('jmatch6.js_');
    }
    function expand_JSDJMatch6() {
        return $this->expand_template('djmatch6.js_');
    }
    function expand_JSFJMatch6() {
        return $this->expand_template('fjmatch6.js_');
    }
    function expand_ShuffleQs() {
        return $this->source->xml_value_bool($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',shuffle-questions');
    }
    function expand_QsToShow() {
        $i = $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',show-limited-questions');
        if ($i) {
            $i = $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',questions-to-show');
        }
        if (empty($i)) {
            $i = 0;
            if ($this->source->hbs_quiztype=='jmatch') {
                $tags = 'data,matching-exercise,pair';
            } else if ($this->source->hbs_quiztype=='jquiz') {
                $tags = 'data,questions,question-record';
            } else {
                $tags = '';
            }
            if ($tags) {
                while (($moretags="[$i]['#']") && $value = $this->source->xml_value($tags, $moretags)) {
                    $i++;
                }
            }
        }
        return $i;
    }
    function expand_MatchDivItems() {
        $this->set_jmatch_items();

        $l_keys = $this->shuffle_jmatch_items($this->l_items);
        $r_keys = $this->shuffle_jmatch_items($this->r_items);

        $options = '<option value="x">'.$this->source->xml_value('data,matching-exercise,default-right-item').'</option>'."\n";

        foreach ($r_keys as $key) {
            // only add the first occurrence of the text (i.e. skip duplicates)
            if ($key==$this->r_items[$key]['key']) {
                $options .= '<option value="'.$key.'">'.$this->r_items[$key]['text'].'</option>'."\n";
                // Note: if the 'text' contains an image, it could be added as the background image of the option
                // http://www.small-software-utilities.com/design/91/html-select-with-background-image-or-icon-next-to-text/
                // ... or of an optgroup ...
                // http://ask.metafilter.com/16153/Images-in-HTML-select-form-elements
            }
        }

        $str = '';
        foreach ($l_keys as $key) {
            $str .= '<tr><td class="l_item">'.$this->l_items[$key]['text'].'</td>';
            $str .= '<td class="r_item">';
            if ($this->r_items[$key]['fixed']) {
                $str .= $this->r_items[$key]['text'];
            }  else {
                $str .= '<select id="s'.$this->r_items[$key]['key'].'_'.$key.'">'.$options.'</select>';
            }
            $str .= '</td><td></td></tr>';
        }
        return $str;
    }
    function expand_FixedArray() {
        $this->set_jmatch_items();
        $str = '';
        foreach ($this->l_items as $i=>$item) {
            $str .= "F[$i] = new Array();\n";
            $str .= "F[$i][0] = '".$this->source->js_value_safe($item['text'], true)."';\n";
            $str .= "F[$i][1] = ".($item['key']+1).";\n";
        }
        return $str;
    }
    function expand_DragArray() {
        $this->set_jmatch_items();
        $str = '';
        foreach ($this->r_items as $i=>$item) {
            $str .= "D[$i] = new Array();\n";
            $str .= "D[$i][0] = '".$this->source->js_value_safe($item['text'], true)."';\n";
            $str .= "D[$i][1] = ".($item['key']+1).";\n";
            $str .= "D[$i][2] = ".$item['fixed'].";\n";
        }
        return $str;
    }
    function expand_Slide() {
        // return true if any JMatch drag-and-drop RH items are fixed and therefore need to slide to the LHS
        $this->set_jmatch_items();
        foreach ($this->r_items as $i=>$item) {
            if ($item['fixed']) {
                return true;
            }
        }
        return false;
    }
    function set_jmatch_items() {
        if (count($this->l_items)) {
            return;
        }
        $tags = 'data,matching-exercise,pair';
        $i = 0;
        while (($item = "[$i]['#']") && $this->source->xml_value($tags, $item)) {

            $l_item = $item."['left-item'][0]['#']";
            $l_text = $this->source->xml_value($tags, $l_item."['text'][0]['#']");
            $l_fixed = $this->source->xml_value_int($tags, $l_item."['fixed'][0]['#']");

            $r_item = $item."['right-item'][0]['#']";
            $r_text = $this->source->xml_value($tags, $r_item."['text'][0]['#']");
            $r_fixed = $this->source->xml_value_int($tags, $r_item."['fixed'][0]['#']");

            if ($r_fixed) {
                // dragable item is actually fixed
                $key = $i;
            } else {
                // typically all right-hand items are unique, but there may be duplicates
                // in which case we want the key of the first item containing this text
                for ($key=0; $key<$i; $key++) {
        	        if (isset($this->r_items[$key]) && $this->r_items[$key]['text']==$r_text) {
                        break;
                    }
                }
            }

            if (strlen($r_text)) {
                $addright = true;
            } else {
                $addright = false;
            }
            if (strlen($l_text)) {
                $this->l_items[] = array('key' => $key, 'text' => $l_text, 'fixed' => $l_fixed);
                $addright = true; // force right item to be added
            }
            if ($addright) {
                $this->r_items[] = array('key' => $key, 'text' => $r_text, 'fixed' => $r_fixed);
            }
            $i++;
        }
    }
    function shuffle_jmatch_items(&$items) {
        // get moveable items
        $moveable_keys = array();
        for ($i=0; $i<count($items); $i++) {
            if(! $items[$i]['fixed']) {
                $moveable_keys[] = $i;
            }
        }
        // shuffle moveable items
        $this->seed_random_number_generator();
        shuffle($moveable_keys);

        $keys = array();
        for ($i=0, $ii=0; $i<count($items); $i++) {
            if($items[$i]['fixed']) {
                //  fixed items stay where they are
                $keys[] = $i;
            } else {
                //  moveable items are inserted in a shuffled order
                $keys[] = $moveable_keys[$ii++];
            }
        }
        return $keys;
    }
    function seed_random_number_generator() {
        static $seeded = false;
        if (! $seeded) {
            srand((double) microtime() * 1000000);
            $seeded = true;
        }
    }

    // JMatch flash card

    function expand_TRows() {
        $str = '';
        $this->set_jmatch_items();
        $i_max = count($this->l_items);
        for ($i=0; $i<$i_max; $i++) {
            $str .= '<tr class="FlashcardRow" id="I_'.$i.'"><td id="L_'.$i.'">'.$this->l_items[$i]['text'].'</td><td id="R_'.$i.'">'.$this->r_items[$i]['text'].'</td></tr>'."\n";
        }
        return $str;
    }

    // jmix

    function expand_JSJMix6() {
        return $this->expand_template('jmix6.js_');
    }
    function expand_JSFJMix6() {
        return $this->expand_template('fjmix6.js_');
    }
    function expand_JSDJMix6() {
        return $this->expand_template('djmix6.js_');
    }
    function expand_Punctuation() {
        $chars = array();

        // RegExp pattern to match HTML entity
        $pattern = '/&#x([0-9A-F]+);/i';

        // entities for all punctutation except '&#;' (because they are used in html entities)
        $entities = $this->jmix_encode_punctuation('!"$%'."'".'()*+,-./:<=>?@[\]^_`{|}~');

        // xml tags for JMix segments and alternate answers
        $punctuation_tags = array(
            'data,jumbled-order-exercise,main-order,segment',
            'data,jumbled-order-exercise,alternate'
        );
        foreach ($punctuation_tags as $tags) {

            // get next segment (or alternate answer)
            $i = 0;
            while ($value = $this->source->xml_value($tags, "[$i]['#']")) {

                // convert low-ascii punctuation to entities
                $value = strtr($value, $entities);

                // extract all hex HTML entities
                if (preg_match_all($pattern, $value, $matches)) {

                    // loop through hex entities
                    $m_max = count($matches[0]);
                    for ($m=0; $m<$m_max; $m++) {

                        // convert to hex number
                        eval('$hex=0x'.$matches[1][$m].';');

                        // is this a punctuation character?
                        if (
                            ($hex>=0x0020 && $hex<=0x00BF) || // ascii punctuation
                            ($hex>=0x2000 && $hex<=0x206F) || // general punctuation
                            ($hex>=0x3000 && $hex<=0x303F) || // CJK punctuation
                            ($hex>=0xFE30 && $hex<=0xFE4F) || // CJK compatability
                            ($hex>=0xFE50 && $hex<=0xFE6F) || // small form variants
                            ($hex>=0xFF00 && $hex<=0xFF40) || // halfwidth and fullwidth forms (1)
                            ($hex>=0xFF5B && $hex<=0xFF65) || // halfwidth and fullwidth forms (2)
                            ($hex>=0xFFE0 && $hex<=0xFFEE)    // halfwidth and fullwidth forms (3)
                        ) {
                            // add this character
                            $chars[] = $matches[0][$m];
                        }
                    }
                } // end if HTML entity

                $i++;
            } // end while next segment (or alternate answer)
        } // end foreach $tags

        $chars = implode('', array_unique($chars));
        return $this->source->js_value_safe($chars, true);
    }
    function expand_OpenPunctuation() {
        $chars = array();

        // unicode punctuation designations (pi="initial quote", ps="open")
        //  http://www.sql-und-xml.de/unicode-database/pi.html
        //  http://www.sql-und-xml.de/unicode-database/ps.html
        $pi = '0022|0027|00AB|2018|201B|201C|201F|2039';
        $ps = '0028|005B|007B|0F3A|0F3C|169B|201A|201E|2045|207D|208D|2329|23B4|2768|276A|276C|276E|2770|2772|2774|27E6|27E8|27EA|2983|2985|2987|2989|298B|298D|298F|2991|2993|2995|2997|29D8|29DA|29FC|3008|300A|300C|300E|3010|3014|3016|3018|301A|301D|FD3E|FE35|FE37|FE39|FE3B|FE3D|FE3F|FE41|FE43|FE47|FE59|FE5B|FE5D|FF08|FF3B|FF5B|FF5F|FF62';
        $pattern = '/(&#x('.$pi.'|'.$ps.');)/i';

        // HMTL entities of opening punctuation
        $entities = $this->jmix_encode_punctuation('"'."'".'(<[{');

        // xml tags for JMix segments and alternate answers
        $punctuation_tags = array(
            'data,jumbled-order-exercise,main-order,segment',
            'data,jumbled-order-exercise,alternate'
        );
        foreach ($punctuation_tags as $tags) {
            $i = 0;
            while ($value = $this->source->xml_value($tags, "[$i]['#']")) {
                $value = strtr($value, $entities);
                if (preg_match_all($pattern, $value, $matches)) {
                    $chars = array_merge($chars, $matches[0]);
                }
                $i++;
            }
        }

        $chars = implode('', array_unique($chars));
        return $this->source->js_value_safe($chars, true);
    }
    function jmix_encode_punctuation($str) {
        $entities = array();
        $i_max = strlen($str);
        for ($i=0; $i<$i_max; $i++) {
            $entities[$str{$i}] = '&#x'.sprintf('%04X', ord($str{$i})).';';
        }
        return $entities;
    }
    function expand_ForceLowercase() {
        // When generating html files with standard JMix program, the user is prompted:
        //   Should the word Xxxxx begin with a capital letter
        //   even when it isn't at the beginning of a sentence?

        // The following xml tag implements a similar functionality
        // This tag does not exist in standard Hot Potatoes XML files,
        // but it can be added manually, for example to a HP config file

        // Should we force the first letter of the first word to be lowercase?
        // - all other letters are assumed to have the correct case
        $tag = $this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',force-lowercase';
        return $this->source->xml_value_int($tag);
    }
    function expand_SegmentArray($more_values=array()) {

        $segments = array();
        $values = array();

        // XML tags to the start of a segment
        $tags = 'data,jumbled-order-exercise,main-order,segment';

        $i = 0;
        while ($value = $this->source->xml_value($tags, "[$i]['#']")) {
            if ($i==0 && $this->expand_ForceLowercase()) {
                $value = strtolower(substr($value, 0, 1)).substr($value, 1);
            }
            $key = array_search($value, $values);
            if (is_numeric($key)) {
                $segments[] = $key;
            } else {
                $segments[] = $i;
                $values[$i] = $value;
            }
            $i++;
        }

        foreach ($more_values as $value) {
            $key = array_search($value, $values);
            if (is_numeric($key)) {
                $segments[] = $key;
            } else {
                $segments[] = $i;
                $values[$i] = $value;
            }
            $i++;
        }

        $this->seed_random_number_generator();
        $keys = array_keys($segments);
        shuffle($keys);

        $str = '';
        for ($i=0; $i<count($keys); $i++) {
            $key = $segments[$keys[$i]];
            $str .= "Segments[$i] = new Array();\n";
            $str .= "Segments[$i][0] = '".$this->source->js_value_safe($values[$key], true)."';\n";
            $str .= "Segments[$i][1] = ".($key+1).";\n";
            $str .= "Segments[$i][2] = 0;\n";
        }
        return $str;
    }
    function expand_AnswerArray() {

        $segments = array();
        $values = array();
        $escapedvalues = array();

        // XML tags to the start of a segment
        $tags = 'data,jumbled-order-exercise,main-order,segment';

        $i = 0;
        while ($value = $this->source->xml_value($tags, "[$i]['#']")) {
            if ($i==0 && $this->expand_ForceLowercase()) {
                $value = strtolower(substr($value, 0, 1)).substr($value, 1);
            }
            $key = array_search($value, $values);
            if (is_numeric($key)) {
                $segments[] = $key+1;
            } else {
                $segments[] = $i+1;
                $values[$i] = $value;
                $escapedvalues[] = preg_quote($value, '/');
            }
            $i++;
        }

        // start the answers array
        $a = 0;
        $str = 'Answers['.($a++).'] = new Array('.implode(',', $segments).");\n";

        // pattern to match the next part of an alternate answer
        $pattern = '/^('.implode('|', $escapedvalues).')\s*/i';

        // XML tags to the start of an alternate answer
        $tags = 'data,jumbled-order-exercise,alternate';

        $i = 0;
        while ($value = $this->source->xml_value($tags, "[$i]['#']")) {
            $segments = array();
            while (strlen($value) && preg_match($pattern, $value, $matches)) {
                $key = array_search($matches[1], $values);
                if (is_numeric($key)) {
                    $segments[] = $key+1;
                    $value = substr($value, strlen($matches[0]));
                } else {
                    // invalid alternate sequence - shouldn't happen !!
                    $segments = array();
                    break;
                }
            }
            if (count($segments)) {
                $str .= 'Answers['.($a++).'] = new Array('.implode(',', $segments).");\n";
            }
            $i++;
        }
        return $str;
    }
    function expand_RemainingWords() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',remaining-words');
    }

    function expand_DropTotal() {
        return $this->source->xml_value_int($this->source->hbs_software.'-config-file,global,drop-total');
    }

    // JQuiz

    function expand_JSJQuiz6() {
        return $this->expand_template('jquiz6.js_');
    }

    function expand_QuestionOutput() {
        // start question list
        $str = '<ol class="QuizQuestions" id="Questions">'."\n";

        $q = 0;
        $tags = 'data,questions,question-record';
        while (($question="[$q]['#']") && $this->source->xml_value($tags, $question) && ($answers = $question."['answers'][0]['#']") && $this->source->xml_value($tags, $answers)) {

            // get question
            $question_text = $this->source->xml_value($tags, $question."['question'][0]['#']");
            $question_type = $this->source->xml_value_int($tags, $question."['question-type'][0]['#']");

            switch ($question_type) {
                case 1: // MULTICHOICE:
                    $textbox = false;
                    $liststart = '<ol class="MCAnswers">'."\n";
                    break;
                case 2: // SHORTANSWER:
                    $textbox = true;
                    $liststart = '';
                    break;
                case 3: // HYBRID:
                    $textbox = true;
                    $liststart = '<ol class="MCAnswers" id="Q_'.$q.'_Hybrid_MC" style="display: none;">'."\n";
                    break;
                case 4: // MULTISELECT:
                    $textbox = false;
                    $liststart = '<ol class="MSelAnswers">'."\n";
                    break;
                default:
                    continue; // unknown question type
            }

            $first_answer_tags = $question."['answers'][0]['#']['answer'][0]['#']['text'][0]['#']";
            $first_answer_text = $this->source->xml_value($tags, $first_answer_tags, '', false);

            // check we have a question (or at least one answer)
            if (strlen($question_text) || strlen($first_answer_text)) {

                // start question
                $str .= '<li class="QuizQuestion" id="Q_'.$q.'" style="display: none;">';
                $str .= '<p class="QuestionText">'.$question_text.'</p>';

                if ($textbox) {

                    // get prefix, suffix and maximum size of ShortAnswer box (default = 9)
                    list($prefix, $suffix, $size) = $this->expand_jquiz_textbox_details($tags, $answers, $q);

                    $str .= '<div class="ShortAnswer" id="Q_'.$q.'_SA"><form method="post" action="" onsubmit="return false;"><div>';
                    $str .= $prefix;
                    if ($size<=25) {
                        // text box
                        $str .= '<input type="text" id="Q_'.$q.'_Guess" onfocus="TrackFocus('."'".'Q_'.$q.'_Guess'."'".')" onblur="LeaveGap()" class="ShortAnswerBox" size="'.$size.'"></input>';
                    } else {
                        // textarea (29 cols wide)
                        $str .= '<textarea id="Q_'.$q.'_Guess" onfocus="TrackFocus('."'".'Q_'.$q.'_Guess'."'".')" onblur="LeaveGap()" class="ShortAnswerBox" cols="29" rows="'.ceil($size/25).'"></textarea>';
                    }
                    $str .= $suffix;
                    $str .= '<br /><br />';

                    $caption = $this->expand_CheckCaption();
                    $str .= $this->expand_jquiz_button($caption, "CheckShortAnswer($q)");

                    if ($this->expand_Hint()) {
                        $caption = $this->expand_HintCaption();
                        $str .= $this->expand_jquiz_button($caption, "ShowHint($q)");
                    }
                    if ($this->expand_ShowAnswer()) {
                        $caption = $this->expand_ShowAnswerCaption();
                        $str .= $this->expand_jquiz_button($caption, "ShowAnswers($q)");
                    }

                    $str .= '</div></form></div>';
                }
                if ($liststart) {
                    $str .= $liststart;

                    $a = 0;
                    $aa = 0;
                    while (($answer = $answers."['answer'][$a]['#']") && $this->source->xml_value($tags, $answer)) {
                        $text = $this->source->xml_value($tags, $answer."['text'][0]['#']");
                        if (strlen($text)) {
                            if ($question_type==1 || $question_type==3) {
                                // MULTICHOICE or HYBRID: button
                                if ($this->source->xml_value_int($tags, $answer."['include-in-mc-options'][0]['#']")) {
                                    $str .= '<li id="Q_'.$q.'_'.$aa.'"><button class="FuncButton" onfocus="FuncBtnOver(this)" onblur="FuncBtnOut(this)" onmouseover="FuncBtnOver(this)" onmouseout="FuncBtnOut(this)" onmousedown="FuncBtnDown(this)" onmouseup="FuncBtnOut(this)" id="Q_'.$q.'_'.$aa.'_Btn" onclick="CheckMCAnswer('.$q.','.$aa.',this)">&nbsp;&nbsp;?&nbsp;&nbsp;</button>&nbsp;&nbsp;'.$text.'</li>'."\n";
                                }
                            } else if ($question_type==4) {
                                // MULTISELECT: checkbox
                                $str .= '<li id="Q_'.$q.'_'.$aa.'"><form method="post" action="" onsubmit="return false;"><div><input type="checkbox" id="Q_'.$q.'_'.$aa.'_Chk" class="MSelCheckbox" />'.$text.'</div></form></li>'."\n";
                            }
                            $aa++;
                        }
                        $a++;
                    }
                    $str .= '</ol>';

                    if ($question_type==4) {
                        // MULTISELECT: check button
                        $caption = $this->expand_CheckCaption();
                        $str .= $this->expand_jquiz_button($caption, "CheckMultiSelAnswer($q)");
                    }
                }

                // finish question
                $str .= "</li>\n";
            }
            $q++;
        } // end while $question

        // finish question list and finish
        return $str."</ol>\n";
    }
    function expand_jquiz_textbox_details($tags, $answers, $q, $defaultsize=9) {
        $prefix = '';
        $suffix = '';
        $size = $defaultsize;

        $a = 0;
        while (($answer = $answers."['answer'][$a]['#']") && $this->source->xml_value($tags, $answer)) {
            $text = $this->source->xml_value($tags, $answer."['text'][0]['#']", '', false);
            $text = preg_replace('/&[#a-zA-Z0-9]+;/', 'x', $text);
            $size = max($size, strlen($text));
            $a++;
        }

        return array($prefix, $suffix, $size);
    }
    function expand_jquiz_button($caption, $onclick) {
        return '<button class="FuncButton" onfocus="FuncBtnOver(this)" onblur="FuncBtnOut(this)" onmouseover="FuncBtnOver(this)" onmouseout="FuncBtnOut(this)" onmousedown="FuncBtnDown(this)" onmouseup="FuncBtnOut(this)" onclick="'.$onclick.'">'.$caption.'</button>';
    }
    function expand_MultiChoice() {
        return $this->jquiz_has_question_type(1);
    }
    function expand_ShortAnswer() {
        return $this->jquiz_has_question_type(2);
    }
    function expand_MultiSelect() {
        return $this->jquiz_has_question_type(4);
    }
    function jquiz_has_question_type($type) {
        // does this JQuiz have any questions of the given $type?
        $q = 0;
        $tags = 'data,questions,question-record';
        while (($question = "[$q]['#']") && $this->source->xml_value($tags, $question)) {
            $question_type = $this->source->xml_value($tags, $question."['question-type'][0]['#']");
            if ($question_type==$type || ($question_type==3 && ($type==1 || $type==2))) {
                // 1=MULTICHOICE 2=SHORTANSWER 3=HYBRID
                return true;
            }
            $q++;
        }
        return false;
    }
    function expand_CompletedSoFar() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,global,completed-so-far');
    }
    function expand_ContinuousScoring() {
        return $this->source->xml_value_bool($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',continuous-scoring');
    }
    function expand_CorrectFirstTime() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,global,correct-first-time');
    }
    function expand_ExerciseCompleted() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,global,exercise-completed');
    }
    function expand_ShowCorrectFirstTime() {
        return $this->source->xml_value_bool($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',show-correct-first-time');
    }
    function expand_ShuffleAs() {
        return $this->source->xml_value_bool($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',shuffle-answers');
    }

    function expand_DefaultRight() {
        return $this->expand_GuessCorrect();
    }
    function expand_DefaultWrong() {
        return $this->expand_GuessIncorrect();
    }
    function expand_ShowAllQuestionsCaptionJS() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,global,show-all-questions-caption');
    }
    function expand_ShowOneByOneCaptionJS() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,global,show-one-by-one-caption');
    }
    function expand_CorrectList() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',correct-answers');
    }
    function expand_HybridTries() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',short-answer-tries-on-hybrid-q');
    }
    function expand_PleaseEnter() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',enter-a-guess');
    }
    function expand_PartlyIncorrect() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',partly-incorrect');
    }
    function expand_ShowAnswerCaption() {
        return $this->source->xml_value($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',show-answer-caption');
    }
    function expand_ShowAlsoCorrect() {
        return $this->source->xml_value_bool($this->source->hbs_software.'-config-file,global,show-also-correct');
    }

    // Textoys stylesheets (tt3.cs_)

    function expand_isRTL() {
        // this may require some clever detection of RTL languages (e.g. Hebrew)
        // but for now we just check the RTL setting in Options -> Configure Output
        return $this->source->xml_value_int($this->source->hbs_software.'-config-file,global,process-for-rtl');
    }
    function expand_isLTR() {
        if ($this->expand_isRTL()) {
            return false;
        } else {
            return true;
        }
    }
    function expand_RTLText() {
        return $this->expand_isRTL();
    }
}

function quizport_keypad_chars_sort($a_char, $b_char) {
    $a_value =  quizport_keypad_char_value($a_char);
    $b_value =  quizport_keypad_char_value($b_char);
    if ($a_value < $b_value) {
        return -1;
    }
    if ($a_value > $b_value) {
        return 1;
    }
    // values are equal
    return 0;
}
function quizport_keypad_char_value($char) {
    global $QUIZPORT;
    // hexadecimal
    if (preg_match('/&#x([0-9a-fA-F]+);/', $char, $matches)) {
        $ord = hexdec($matches[1]);

    // decimal
    } else if (preg_match('/&#(\d+);/', $char, $matches)) {
        $ord = intval($matches[1]);

    // other html entity (named?)
    } else if (preg_match('/&[^;]+;/', $char, $matches)) {
        $char = $QUIZPORT->quiz->source->html_entity_decode($matches[0]);
        $ord = ord($char);

    // not an html entity
    } else {
        $ord = ord($char);
    }

    // lowercase letters (plain or accented)
    if (($ord>=97 && $ord<=122) || ($ord>=224 && $ord<=255)) {
        return ($ord-31) + ($ord/1000);
    }

    // subscripts and superscripts
    switch ($ord) {
        case 0x2070: return 48.1; // super 0 = ord('0') + 0.1
        case 0x00B9: return 49.1; // super 1
        case 0x00B2: return 50.1; // super 2
        case 0x00B3: return 51.1; // super 3
        case 0x2074: return 52.1; // super 4
        case 0x2075: return 53.1; // super 5
        case 0x2076: return 54.1; // super 6
        case 0x2077: return 55.1; // super 7
        case 0x2078: return 56.1; // super 8
        case 0x2079: return 57.1; // super 9

        case 0x207A: return 43.1; // super +
        case 0x207B: return 45.1; // super -
        case 0x207C: return 61.1; // super =
        case 0x207D: return 40.1; // super (
        case 0x207E: return 41.1; // super )
        case 0x207F: return 110.1; // super n

        case 0x2080: return 47.9; // sub 0 = ord('0') - 0.1
        case 0x2081: return 48.9; // sub 1
        case 0x2082: return 49.9; // sub 2
        case 0x2083: return 50.9; // sub 3
        case 0x2084: return 51.9; // sub 4
        case 0x2085: return 52.9; // sub 5
        case 0x2086: return 53.9; // sub 6
        case 0x2087: return 54.9; // sub 7
        case 0x2088: return 55.9; // sub 8
        case 0x2089: return 56.9; // sub 9

        case 0x208A: return 42.9; // sub +
        case 0x208B: return 44.9; // sub -
        case 0x208C: return 60.9; // sub =
        case 0x208D: return 39.9; // sub (
        case 0x208E: return 40.9; // sub )
        case 0x208F: return 109.9; // sub n
    }

    return $ord;
}
?>
