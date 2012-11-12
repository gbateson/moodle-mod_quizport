<?php
class quizport_output_html_ispring extends quizport_output_html {
    // source file types with which this output format can be used
    var $filetypes = array('html_ispring');

    function preprocessing() {
        if ($this->cache_uptodate) {
            return true;
        }

        if (! $this->source->get_filecontents()) {
            // empty source file - shouldn't happen !!
            return false;
        }

        // remove doctype
        $search = '/\s*(?:<!--\s*)?<!DOCTYPE[^>]*>\s*(?:-->\s*)?/s';
        $this->source->filecontents = preg_replace($search, '', $this->source->filecontents);

        // replace <object> with link and force through filters
        $search = '/<object id="presentation"[^>]*>.*?<param name="movie" value="([^">]*)"[^>]*>.*?<\/object>/is';
        $replace = '<a href="\\1?d=800x600">\\1</a>';
        $this->source->filecontents = preg_replace($search, $replace, $this->source->filecontents);

        // remove fixprompt.js
        $search = '/<script[^>]*src="[^">]*fixprompt.js"[^>]*(?:(?:\/>)|(?:<\/script>))\s*/s';
        $this->source->filecontents = preg_replace($search, '', $this->source->filecontents);

        parent::preprocessing();
    }
}
?>