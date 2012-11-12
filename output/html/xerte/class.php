<?php
class quizport_output_html_xerte extends quizport_output_html {
    // source file types with which this output format can be used
    var $filetypes = array('html_xerte');

    // constructor function
    function quizport_output_html_xerte(&$quiz) {
        $quiz->usemediafilter = 0;
        parent::quizport_output_html($quiz);
    }

    function preprocessing() {
        if ($this->cache_uptodate) {
            return true;
        }

        if (! $this->source->get_filecontents()) {
            // empty source file - shouldn't happen !!
            return false;
        }

        if ($pos = strpos($this->source->filecontents, '<title>')) {
            $insert = '<base href="'.$this->source->baseurl.'/'.$this->source->filepath.'">'."\n";
            $this->source->filecontents = substr_replace($this->source->filecontents, $insert, $pos, 0);
        }

        // replace external javascript with modified inline javascript
        $search = '/<script[^>]*src\s*=\s*"([^"]*)"[^>]*>\s*<\/script>/';
        $callback = array($this, 'preprocessing_xerte_js');
        $this->source->filecontents = preg_replace_callback($search, $callback, $this->source->filecontents);

        parent::preprocessing();
    }

    function preprocessing_xerte_js($match) {
        $js = file_get_contents($this->source->dirname.'/'.$match[1]);

        $baseurl = $this->source->baseurl.'/';
        if ($pos = strrpos($this->source->filepath, '/')) {
            $baseurl .= substr($this->source->filepath, 0, $pos + 1);
        }

        // several search-and-replace fixes
        //  - add style to center the Flash Object
        //  - convert MainPreloader.swf to absolute URL
        //  - break up "script" strings to prevent unwanted QuizPort postprocessing
        $search = array(
            ' style="'."width:' + rloWidth + 'px; height:' + rloHeight + 'px; ".'"',
            'var FileLocation = getLocation();',
            'MainPreloader.swf',
            'script', 'Script', 'SCRIPT',
        );
        $replace = array(
            ' style="'."width:' + rloWidth + 'px; height:' + rloHeight + 'px; margin:auto;".'"',
            "var FileLocation = '$baseurl';",
            $baseurl.'MainPreloader.swf',
            "scr' + 'ipt", "Scr' + 'ipt", "SCR' + 'IPT",
        );

        if ($this->source->get_displayMode()=='fill window') {
            // remove "id" to prevent resizing of Flash object
            // there might be another way to do this
            // e.g. using js to stretch canvas area
            $search[] = ' id="'."rlo' + rloID + '".'"';
            $replace[] = '';
        }

        $js = str_replace($search, $replace, $js);
        return '<script type="text/javascript">'."\n".trim($js)."\n".'</script>'."\n";
    }
}
?>