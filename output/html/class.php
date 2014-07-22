<?php
class quizport_output_html extends quizport_output {

    // strings to mark beginning and end of submission form
    var $BeginSubmissionForm = '<!-- BeginSubmissionForm -->';
    var $EndSubmissionForm = '<!-- EndSubmissionForm -->';
    var $formid = 'store';

    // constructor function
    function quizport_output_html(&$quiz) {
        parent::quizport_output($quiz);
    }

    // functions to generate browser content

    function preprocessing() {
        global $CFG, $QUIZPORT;

        if ($this->cache_uptodate) {
            $this->fix_title();
            $this->fix_links(true);
            $this->fix_submissionform();
            return true;
        }

        $this->xmldeclaration = '';
        $this->doctype = '';
        $this->htmltag = '<html>';
        $this->headattributes = '';
        $this->headcontent = '';
        $this->bodyattributes = '';
        $this->bodycontent = '';

        if (! $this->source->get_filecontents()) {
            // empty source file - shouldn't happen !!
            return false;
        }
        $this->htmlcontent = &$this->source->filecontents;

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
            if (preg_match($this->tagpattern('!DOCTYPE','',false,'(?:<!--\s*)?','(?:\s*-->)?'), $this->htmlcontent, $matches)) {
                $this->doctype = $this->single_line($matches[0])."\n";
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
                // restrict scope of page styles, so they affect only the quiz's containing element (i.e. the middle column)
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
        foreach ($this->javascripts as $script) {
            $this->scripts .= "\n".'<script type="text/javascript" src="'.$CFG->wwwroot.'/'.$script.'"></script>';
        }
        if (preg_match_all($this->tagpattern('script'), $this->headcontent, $matches, PREG_OFFSET_CAPTURE)) {
            foreach (array_reverse($matches[0]) as $match) {
                // $match: [0] = matched string, [1] = offset to start of string
                $this->scripts = $match[0]."\n".$this->scripts;
                $this->headcontent = substr_replace($this->headcontent, '', $match[1], strlen($match[0]));
            }
            // remove block and single-line comments - except <![CDATA[ + ]]>> and  <!-- + --> and http(s)://
            if ($CFG->debug <= DEBUG_DEVELOPER) {
                $this->scripts = preg_replace('/\s*\/\*.*?\*\//s', '', $this->scripts);
                $this->scripts = preg_replace('/\s*([a-z]+:)?\/\/[^\r\n]*/ise', '$this->fix_js_comment("\\0","\\1")', $this->scripts);
            }
            $this->scripts = $this->remove_blank_lines($this->scripts);

            // standardize "} else {" formatting
            $this->scripts = preg_replace('/}\s*else\s*{/s', '} else {', $this->scripts);

        }

        // remove blank lines
        $this->headcontent = $this->remove_blank_lines($this->headcontent);

        // put each <meta> tag on its own line
        $this->headcontent = preg_replace('/'.'([^\n])'.'(<\w+)'.'/', "\\1\n\\2", $this->headcontent);

        // append styles and scripts to the end of the $this->headcontent
        $this->headcontent .= $this->styles.$this->scripts;

        // extract <body> tag
        if (! preg_match($this->tagpattern('body'), $this->htmlcontent, $matches)) {
            return false;
        }

        $this->bodyattributes = $this->single_line(preg_replace('/\s*id="[^"]*"/', '', $matches[1]));
        $this->bodycontent = $this->remove_blank_lines($matches[2]);

        // fix self-closing <script /> tags, as they cause several browsers to ignore following content
        $this->bodycontent = preg_replace('/(<script[^>]*)\/>/is', '\\1></script>', $this->bodycontent);

        if (preg_match('/\s*onload="([^"]*)"/is', $this->bodyattributes, $matches, PREG_OFFSET_CAPTURE)) {
            $this->bodyattributes = substr_replace($this->bodyattributes, '', $matches[0][1], strlen($matches[0][0]));
            if ($this->usemoodletheme) {
                // workaround to ensure javascript onload routine for quiz is always executed
                // $this->bodyattributes will only be inserted into the <body ...> tag
                // if it is included in the theme/$CFG->theme/header.html,
                // so some old or modified themes may not insert $this->bodyattributes
                $this->bodycontent .= $this->fix_onload($matches[1][0], true);
            }
        }
        $this->fix_title();
        $this->fix_relativeurls();
        $this->fix_mediafilter();
        $this->fix_links();
    }

    function set_bodycontent() {
        $this->fix_submissionform();
    }

    function fix_js_comment($comment, $protocol, $quote="'") {
        if ($quote) {
            // fix quotes escaped by preg_replace
            $comment = str_replace('\\'.$quote, $quote, $comment);
            $protocol = str_replace('\\'.$quote, $quote, $protocol);
        }
        if ($protocol || preg_match('/^\s*\/\/((?:<!\[CDATA\[)|(?:<!--)|(?:-->)|(?:\]\]>))/', $comment)) {
            return $comment;
        } else {
            return '';
        }
    }

    function fix_links($quickfix=false) {
        global $DB, $QUIZPORT;

        if ($quickfix) {
            $search = '/(?<=sesskey=)\w+/';
            $this->bodycontent = preg_replace($search, sesskey(), $this->bodycontent);

            $search = '/(?<=unitattemptid=)\w+/';
            $this->bodycontent = preg_replace($search, $QUIZPORT->unitattemptid, $this->bodycontent);

            return true;
        }

        $matches = array();

        if (preg_match_all('/<a[^>]*href="([^"]*)"[^>]*>/is', $this->bodycontent, $match, PREG_OFFSET_CAPTURE)) {
            for ($i=0; $i<count($match); $i++) {
                if (empty($matches[$i])) {
                    $matches[$i] = $match[$i];
                } else {
                    $matches[$i] = array_merge($matches[$i], $match[$i]);
                }
            }
        }

        if (preg_match_all("/location='([^']*)'/i", $this->bodycontent, $match, PREG_OFFSET_CAPTURE)) {
            for ($i=0; $i<count($match); $i++) {
                if (empty($matches[$i])) {
                    $matches[$i] = $match[$i];
                } else {
                    $matches[$i] = array_merge($matches[$i], $match[$i]);
                }
            }
        }

        if (empty($matches[1])) {
            return false; // no urls found
        }

        $urls = array();
        $strlen = strlen($this->source->baseurl);

        foreach ($matches[1] as $i=>$match) {
            $url = $this->convert_url_relative($this->source->baseurl, $this->source->filepath, '', $match[0], '', '');
            if (strpos($url, $this->source->baseurl.'/')===0) {
                $urls[$i] = addslashes(substr($url, $strlen+1));
            }
        }

        if (empty($urls)) {
            return false; // no links to files in this course
        }

        $select = "unitid=$this->unitid AND sourcefile IN ('".implode("','", $urls)."')";
        if ($quizzes = $DB->get_records_select('quizport_quizzes', $select, null, 'id', 'id,sourcefile')) {
            foreach ($quizzes as $quiz) {
                $i = array_search($quiz->sourcefile, $urls);
                $matches[1][$i][2] = $quiz->id;
            }
            foreach (array_reverse($matches[1]) as $match) {
                // $match [0] old url, [1] offset [2] quizid
                if (array_key_exists(2, $match)) {
                    // it used to be possible to send the id of the next quiz back as the "redirect" parameter
                    // but that doesn't work any more bacuse of changes to view.php
                    // $params = array('quizattemptid'=>$QUIZPORT->quizattemptid, 'redirect'=>$match[2]);

                    $params = array('quizid'=>$match[2], 'qnumber'=>0, 'quizscoreid'=>0, 'quizattemptid'=>0);
                    $newurl = $QUIZPORT->format_url('view.php', '', $params);
                    $this->bodycontent = substr_replace($this->bodycontent, $newurl, $match[1], strlen($match[0]));
                }
            }
        }
    }

    function fix_title() {
        global $CFG, $QUIZPORT;

        // extract the current title
        if (preg_match($this->tagpattern('h2'), $this->bodycontent, $matches)) {
            $title = $this->get_title();
            if (has_capability('mod/quizport:manage', $QUIZPORT->modulecontext)) {
                $title .= $QUIZPORT->print_commands(
                    // $types, $quizportscriptname, $id, $params, $popup, $return
                    array('update'), 'editquiz.php', 'quizid',
                    array('quizid'=>$QUIZPORT->quizid, 'qnumber'=>$QUIZPORT->qnumber, 'unumber'=>$QUIZPORT->unumber),
                    false, true
                );
            }
            $replace = '<h2>'.$title.'</h2>';
            $this->bodycontent = str_replace($matches[0], $replace, $this->bodycontent);
        }
    }

    function fix_submissionform() {
        global $CFG, $QUIZPORT;

        // remove previous submission form, if any
        $search = '/\s*('.$this->BeginSubmissionForm.')\s*(.*?)\s*('.$this->EndSubmissionForm.')/s';
        $this->bodycontent = preg_replace($search, '', $this->bodycontent);

        // set form params
        $params = array(
            'id' => $QUIZPORT->quizattemptid,
            'detail' => '0', 'status' => QUIZPORT_STATUS_COMPLETED,
            'starttime' => '0', 'endtime' => '0', 'redirect' => '1',
        );
        if (! preg_match('/<(input|select)[^>]*name="'.$this->scorefield.'"[^>]*>/is', $this->bodycontent)) {
            $params[$this->scorefield] = $this->scorelimit;
        }

        // create submit button, if necessary
        if (preg_match('/<input[^>]*type="submit"[^>]*>/is', $this->bodycontent)) {
            // submit button already exists
            $submit_button = '';
        } else {
            if ($this->usemoodletheme) {
                $align = ' class="continuebutton"';
            } else {
                $align = ' align="center"';
            }
            $submit_button = '<div'.$align.'><input type="submit" value="'.get_string('continue').'" /></div>'."\n";
        }

        // wrap submission form around content
        $this->bodycontent = ''
            .$this->BeginSubmissionForm."\n"
            .$QUIZPORT->print_form_start('view.php', $params, false, true, array('id' => $this->formid))
            .$this->EndSubmissionForm."\n"
            .$this->bodycontent."\n"
            .$this->BeginSubmissionForm."\n"
            .$submit_button
            .$QUIZPORT->print_form_end(true)
            .$this->EndSubmissionForm."\n"
        ;
    }

    function redirect($redirect) {
        global $QUIZPORT;
        if ($QUIZPORT->quizattempt->redirect) {
            $quizid = $QUIZPORT->quizattempt->redirect;
            $qnumber = -1; // force new quiz attempt
        } else {
            $quizid = 0;
            $qnumber = 0;
        }
        // continue to quizport/view.php
        redirect($QUIZPORT->format_url('view.php', 'coursemoduleid', array('coursemoduleid'=>$QUIZPORT->modulerecord->id, 'quizid'=>0, 'qnumber'=>0, 'quizattemptid'=>0, 'quizscoreid'=>0)));
    }
}
?>