<?php
class quizport_file_html_xerte extends quizport_file_html {
    // properties of the icon for this source file type
    var $icon = 'mod/quizport/file/html/xerte/icon.gif';

    // xmlized content of template.xml
    var $template_xml = null;

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
        if (! preg_match('/<script[^>]*src\s*=\s*"[^"]*rloObject.js"[^>]*>/', $this->filecontents)) {
            return false;
        }
        if (! preg_match("/myRLO = new rloObject\('\d*','\d*','[^']*.rl[to]'\)/", $this->filecontents)) {
            return false;
        }
        return $this;
    }

    function get_template_xml() {
        if (is_null($this->template_xml)) {
            $filepath = $this->dirname.'/template.xml';
            if (file_exists($filepath)) {
                $contents = file_get_contents($filepath);
                $this->template_xml = xmlize($contents, 0);
            } else {
                $this->template_xml = array();
            }
        }
        return $this->template_xml;
    }

    function get_template_value($tags, $default=null) {
        $value = &$this->get_template_xml();
        foreach($tags as $tag) {
            if (! is_array($value)) {
                return $default;
            }
            if(! array_key_exists($tag, $value)) {
                return $default;
            }
            $value = &$value[$tag];
        }
        return $value;
    }

    function get_name() {
        if ($name = $this->get_template_value(array('learningObject', '@', 'name'))) {
            return $name;
        }
        return parent::get_name();
    }

    function get_displayMode() {
        return $this->get_template_value(array('learningObject', '@', 'displayMode'), 'default');
    }

    // returns the introduction text for a quiz
    function get_entrytext() {
        return '';
    }
} // end class
?>