<?php
class quizport_output_hp extends quizport_output {
    var $templatefile = ''; // file name of main template e.g. jcloze6.ht_
    var $templatestrings = ''; // special template strings to be expanded
    var $templatesfolders = array(); // templates folders (relative to Moodle dataroot)

    var $javascripts = array(); // external javascripts required for this format
    var $js_object_type = ''; // type of javascript object to collect quiz results from browser

    // the id/name of the of the form which returns results to the browser
    var $formid = 'store';

    // the name of the score field in the results returned form the browser
    var $scorefield = 'mark';

    // the two fields that will be used to determine the duration of a quiz attempt
    //     starttime/endtime are recorded by the client (and may not be trustworthy)
    //     resumestart/resumefinish are recorded by the server (but include transfer time to and from client)
    var $durationstartfield = 'starttime';
    var $durationfinishfield = 'endtime';

    // constructor function
    function quizport_output_hp(&$quiz) {
        parent::quizport_output($quiz);
    }

    // does this output format allow quiz attempts to be reviewed?
    function provide_review() {
        return true;
    }

    // functions to convert relative urls to absolute URLs

    function fix_relativeurls($str=null) {
        global $DB, $QUIZPORT;

        if (is_string($str)) {
            // fix relative urls in $str(ing), and return
            return parent::fix_relativeurls($str);
        }

        // do standard fixes relative urls in $this->headcontent and $this->bodycontent
        parent::fix_relativeurls();

        // now we do special fixes for HP relative urls

        // replace relative URLs in "PreloadImages(...);"
        $search = '/'.'(?<='.'PreloadImages'.'\('.')'."([^)]+?)".'(?='.'\);'.')'.'/ise';
        $replace = '$this->convert_urls_preloadimages("'.$this->source->baseurl.'","'.$this->source->filepath.'","\\1")';
        $this->headcontent = preg_replace($search, $replace, $this->headcontent);
        $this->bodycontent = preg_replace($search, $replace, $this->bodycontent);
    }

    function convert_urls_preloadimages($baseurl, $sourcefile, $urls, $quote="'") {
        if ($quote) {
            // fix quotes escaped by preg_replace
            $urls = str_replace('\\'.$quote, $quote, $urls);
        }
        $search = '/'.'(?<='.'"'.'|'."'".')'."([^'".'",]+?)'.'(?='.'"'.'|'."'".')'.'/ise';
        $replace = '$this->convert_url("'.$baseurl.'","'.$sourcefile.'","\\1")';
        return preg_replace($search, $replace, $urls);
    }

    function convert_url_navbutton($baseurl, $sourcefile, $url, $quote="'") {
        global $CFG, $DB, $QUIZPORT;
        if ($quote) {
            // fix quotes escaped by preg_replace
            $url = str_replace('\\'.$quote, $quote, $url);
        }
        $url = $this->convert_url($baseurl, $sourcefile, $url, false);

        // is this a $url for another quizport in this unit ?
        if (strpos($url, $baseurl.'/')===0) {
            $sourcefile = $this->source->xml_locate_file(substr($url, strlen($baseurl) + 1));
            $select = "unitid=$this->unitid AND sourcefile='$sourcefile'";
            if ($records = $DB->get_records_select('quizport_quizzes', $select, null, 'sortorder', '*', 0, 1)) {
                $record = reset($records); // first record - there could be more than one ?!
                $params = array(
                    'quizid'=>$record->id,
                    'qnumber'=>0, 'quizattemptid'=>0, 'quizscoreid'=>0,
                    'inpopup' => $QUIZPORT->unit->showpopup
                );
                $url = $QUIZPORT->format_url('view.php', '', $params);
            }
        }
        return $url;
    }

    // functions to expand xml templates (and the blocks and strings contained therein)

    function expand_template($filename) {
        global $CFG;

        // check that some template folders have been specified to something sensible
        if (! isset($this->templatesfolders)) {
            debugging('templatesfolders is not set', DEBUG_DEVELOPER);
            return '';
        }
        if (! is_array($this->templatesfolders)) {
            debugging('templatesfolders is not an array', DEBUG_DEVELOPER);
            return '';
        }

        // set the path to the template file
        $filepath = '';
        foreach ($this->templatesfolders as $templatesfolder) {
            if (is_file("$CFG->dirroot/$templatesfolder/$filename")) {
                $filepath = "$CFG->dirroot/$templatesfolder/$filename";
                break;
            }
        }

        // check the template was found
        if (! $filepath) {
            debugging('template not found: '.$filename, DEBUG_DEVELOPER);
            return '';
        }
        // check the template is readable
        if (! is_readable($filepath)) {
            debugging('template is not readable: '.$filepath, DEBUG_DEVELOPER);
            return '';
        }

        // try and read the template
        if (! $template = file_get_contents($filepath)) {
            debugging('template is empty: '.$filepath, DEBUG_DEVELOPER);
            return '';
        }

        // expand the blocks and strings in the template
        $this->expand_blocks($template);
        $this->expand_strings($template);

        // return the expanded template
        return $template;
    }

    function expand_blocks(&$template) {
        // expand conditional blocks
        //  [1] the full block name (including optional leading 'str' or 'incl')
        //  [2] the short block name (without optional leading 'str' or 'incl')
        //  [3] the content of the block
        $search = '/'.'\[((?:incl|str)?((?:\w|\.)+))\]'.'(.*?)'.'\[\/\\1\]'.'/ise';
        $replace = '$this->expand_block("\\2","\\3")';
        $template = preg_replace($search, $replace, $template);
    }

    function expand_block($blockname, $blockcontent, $quote="'") {
        if ($quote) {
            // fix quotes escaped by preg_replace
            $blockname = str_replace('\\'.$quote, $quote, $blockname);
            $blockcontent = str_replace('\\'.$quote, $quote, $blockcontent);
        }

        $method = 'expand_'.str_replace('.', '', $blockname);

        // check expand method exists
        if (! method_exists($this, $method)) {
            debugging('expand block method not found: '.$method, DEBUG_DEVELOPER);
            return '';
        }

        // if condition is satisfied, return block content; otherwise return empty string
        if ($this->$method()) {
            // expand any (sub) blocks within the block content
            $this->expand_blocks($blockcontent);
            return $blockcontent;
        } else {
            return '';
        }
    }

    function expand_strings(&$template, $search='') {
        if ($search=='') {
            // default search string
            $search = '/\[(?:bool|int|str)(\w+)\]/ise';
        }
        $replace = '$this->expand_string("\\0","\\1")';
        $template = preg_replace($search, $replace, $template);
    }

    function expand_string($originalstring, $stringname, $quote="'") {
        if ($quote) {
            // fix quotes escaped by preg_replace
            $originalstring = str_replace('\\'.$quote, $quote, $originalstring);
            $stringname = str_replace('\\'.$quote, $quote, $stringname);
        }
        $method = 'expand_'.$stringname;
        if (method_exists($this, $method)) {
            return $this->$method();
        } else {
            return $originalstring;
        }
    }
    function expand_halfway_color($x, $y) {
        // returns the $color that is half way between $x and $y
        $color = $x; // default
        $rgb = '/^\#?([0-9a-f])([0-9a-f])([0-9a-f])$/i';
        $rrggbb = '/^\#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i';
        if (preg_match($rgb, $x, $x_matches) || preg_match($rrggbb, $x, $x_matches)) {
            if (preg_match($rgb, $y, $y_matches) || preg_match($rrggbb, $y, $y_matches)) {
                $color = '#';
                for ($i=1; $i<=3; $i++) {
                    $x_dec = hexdec($x_matches[$i]);
                    $y_dec = hexdec($y_matches[$i]);
                    $color .= sprintf('%02x', min($x_dec, $y_dec) + abs($x_dec-$y_dec)/2);
                }
            }
        }
        return $color;
    }

    // utility function to standardize behavior of strrpos
    // (strrpos only allows a single char $needle in PHP 4)
    function strrpos($haystack, $needle, $offset=0) {
        if (floatval(PHP_VERSION)>=5.0) {
            $pos = strrpos($haystack, $needle, $offset);
        } else { // PHP 4.x
            $pos = false;
            $strpos = -1;
            while (is_int($strpos)) {
                $strpos = strpos($haystack, $needle, $strpos+1);
                $pos = $strpos;
            }
        }
        return $pos;
    }
}
?>