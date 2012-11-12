<?php
class quizport_file_hp_6_jcloze_xml extends quizport_file_hp_6_jcloze {
    function is_quizfile() {
        if (preg_match('/\.jcl$/', $this->filename)) {
            return $this;
        } else {
            return false;
        }
    }

    function compact_filecontents() {
        // remove white space within tags
        parent::compact_filecontents();

        // fix white space and html entities in open text

        // Note: when testing this code, be aware that
        // xmlize() behaves differently in PHP4 and PHP5

        $search = '/(?<=<gap-fill)'.'>.*?<'.'(?=\/gap-fill>)/s';
        if (preg_match($search, $this->filecontents, $matches, PREG_OFFSET_CAPTURE)) {
            $match = $matches[0][0];
            $start = $matches[0][1];
            $length = strlen($match);

            // convert newlines to html line breaks
            $newlines = array(
                "\r\n" => '&lt;br /&gt;',
                "\r"   => '&lt;br /&gt;',
                "\n"   => '&lt;br /&gt;',
            );
            $match = strtr($match, $newlines);

            // make sure there is at least one space between the gaps
            $search = '/(?<=<\/question-record>)(?=<question-record>)/';
            $match = preg_replace($search, ' ', $match);

            // surround ampersands in open text by CDATA start and end tags
            $search = '/(?<=>)([^<]*)(?=<)/s';
            $match = preg_replace_callback($search, array(&$this, 'compact_filecontents_opentext'), $match);

            $this->filecontents = substr_replace($this->filecontents, $match, $start, $length);
        }
    }

    function compact_filecontents_opentext(&$matches) {
        $search = '/&[a-zA-Z0-9#;]*;/';
        return preg_replace_callback($search, array(&$this, 'compact_filecontents_entities'), $matches[0]);
    }

    function compact_filecontents_entities(&$matches) {
        // these html entities are coverted back to plain text
        static $html_entities = array(
            '&apos;' => "'",
            '&quot;' => '"',
            '&lt;'   => '<',
            '&gt;'   => '>',
            '&amp;'  => '&'
        );
        return '<![CDATA['.strtr($matches[0], $html_entities).']]>';
    }
}
?>