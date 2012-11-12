<?php
class quizport_file_html_xhtml extends quizport_file_html {
    // the icon for this source file type
    var $icon = 'pix/f/html.gif';
    var $iconwidth = '16';
    var $iconheight = '16';
    var $iconclass = 'icon';

    // returns quizport_file object if $filename is a quiz file, or false otherwise
    function is_quizfile() {
        if (preg_match('/\.html?$/', $this->filename)) {
            return $this;
        } else {
            return false;
        }
    }
} // end class
?>