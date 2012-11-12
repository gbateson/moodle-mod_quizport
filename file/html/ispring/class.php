<?php
class quizport_file_html_ispring extends quizport_file_html {
    // properties of the icon for this source file type
    var $icon = 'mod/quizport/file/html/ispring/icon.gif';

    // returns quizport_file object if $filename is a quiz file, or false otherwise
    function is_quizfile() {
        if (! preg_match('/\.html?$/', $this->filename)) {
            // not an html file
            return false;
        }
        if (! $this->get_filecontents()) {
            // empty or non-existant file
            return false;
        }
        if (! preg_match('/<!--\s*<!DOCTYPE[^>]*>\s*-->/', $this->filecontents)) {
            // no fancy DOCTYPE workarounds for IE6
            return false;
        }
        // detect <object ...>, <embed ...> and self-closing <script ... /> tags
        if (! preg_match('/<object[^>]*id="presentation"[^>]*>/', $this->filecontents)) {
            return false;
        }
        if (! preg_match('/<embed[^>]*name="presentation"[^>]*>/', $this->filecontents)) {
            return false;
        }
        if (! preg_match('/<script[^>]*src="[^">]*fixprompt.js"[^>]*\/>/', $this->filecontents)) {
            return false;
        }
        return $this;
    }

    // returns the introduction text for a quiz
    function get_entrytext() {
        return '';
    }
} // end class
?>