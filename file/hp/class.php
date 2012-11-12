<?php
class quizport_file_hp extends quizport_file {
    var $xml; // an array containing the xml tree for hp xml files
    var $xml_root; // the array key of the root of the xml tree

    var $hbs_software; // hotpot or textoys
    var $hbs_quiztype; //  jcloze, jcross, jmatch, jmix, jquiz, quandary, rhubarb, sequitur

    function is_html() {
        return preg_match('/\.html?$/', $this->filename);
    }

    function get_name() {
        if ($this->is_html()) {
            return $this->html_get_name();
        } else {
            return $this->xml_get_name();
        }
    }
    function get_title() {
        if ($this->is_html()) {
            return $this->html_get_name(false);
        } else {
            return $this->xml_get_name(false);
        }
    }
    function get_entrytext() {
        if ($this->is_html()) {
            return $this->html_get_entrytext();
        } else {
            return $this->xml_get_entrytext();
        }
    }
    function get_nextquiz() {
        if ($this->is_html()) {
            return $this->html_get_nextquiz();
        } else {
            return $this->xml_get_nextquiz();
        }
    }

    // function for html files

    function html_get_name($textonly=true) {
        if (! isset($this->name)) {
            $this->name = '';
            $this->title = '';

            if (! $this->get_filecontents()) {
                // empty file - shouldn't happen !!
                return false;
            }
            if (preg_match('|<h2[^>]*class="ExerciseTitle"[^>]*>(.*?)</h2>|is', $this->filecontents, $matches)) {
                $this->name = trim(strip_tags($matches[1]));
                $this->title = trim($matches[1]);
            }
            if (! $this->name) {
                if (preg_match('|<title[^>]*>(.*?)</title>|is', $this->filecontents, $matches)) {
                    $this->name = trim(strip_tags($matches[1]));
                    if (! $this->title) {
                        $this->title = trim($matches[1]);
                    }
                }
            }
            $this->name = $this->html_entity_decode($this->name);
            $this->title = $this->html_entity_decode($this->title);
        }
        if ($textonly) {
            return $this->name;
        } else {
            return $this->title;
        }
    }
    function html_get_entrytext() {
        if (! isset($this->entrytext)) {
            $this->entrytext = '';

            if (! $this->get_filecontents()) {
                // empty file - shouldn't happen !!
                return false;
            }
            if (preg_match('|<h3[^>]*class="ExerciseSubtitle"[^>]*>\s*(.*?)\s*</h3>|is', $this->filecontents, $matches)) {
                $this->entrytext .= '<div>'.$matches[1].'</div>';
            }
            if (preg_match('|<div[^>]*id="Instructions"[^>]*>\s*(.*?)\s*</div>|is', $this->filecontents, $matches)) {
                $this->entrytext .= '<div>'.$matches[1].'</div>';
            }
        }
        return $this->entrytext;
    }
    function html_get_nextquiz() {
        if (! isset($this->nextquiz)) {
            $this->nextquiz = false;

            if (! $this->get_filecontents()) {
                // empty file - shouldn't happen !!
                return false;
            }
            if (preg_match('|<div[^>]*class="NavButtonBar"[^>]*>(.*?)</div>|is', $this->filecontents, $matches)) {

                $navbuttonbar = $matches[1];
                if (preg_match_all('|<button[^>]*onclick="'."location='([^']*)'".'[^"]*"[^>]*>|is', $navbuttonbar, $matches)) {

                    $lastbutton = count($matches[0])-1;
                    $this->nextquiz = $this->xml_locate_file(dirname($this->filepath).'/'.$matches[1][$lastbutton]);
                }
            }
        }
        return $this->nextquiz;
    }

    // functions for xml files

    function xml_get_name($textonly=true) {
        if (! isset($this->name)) {
            $this->name = '';
            $this->title = '';

            if (! $this->xml_get_filecontents()) {
                // could not detect Hot Potatoes quiz type - shouldn't happen !!
                return false;
            }
            $this->title = $this->xml_value('data,title');
            $this->title = $this->html_entity_decode($this->title);
            $this->name = trim(strip_tags($this->title)); // sanitize
        }
        if ($textonly) {
            return $this->name;
        } else {
            return $this->title;
        }
    }
    function xml_get_entrytext() {
        if (! isset($this->entrytext)) {
            $this->entrytext = '';

            if (! $this->xml_get_filecontents()) {
                // could not detect Hot Potatoes quiz type - shouldn't happen !!
                return false;
            }
            if ($intro = $this->xml_value($this->hbs_software.'-config-file,'.$this->hbs_quiztype.',exercise-subtitle')) {
                $this->entrytext .= '<h3>'.$intro.'</h3>';
            }
            if ($intro = $this->xml_value($this->hbs_software.'-config-file,'.$this->hbs_quiztype.',instructions')) {
                $this->entrytext .= '<div>'.$intro.'</div>';
            }
        }
        return $this->entrytext;
    }
    function xml_get_nextquiz() {
        if (! isset($this->nextquiz)) {
            $this->nextquiz = false;

            if (! $this->xml_get_filecontents()) {
                // could not detect Hot Potatoes quiz type in xml file - shouldn't happen !!
                return false;
            }

            if (! $this->xml_value_int($this->hbs_software.'-config-file,global,include-next-ex')) {
                // next exercise is not enabled for this quiz
                return false;
            }

            if (! $nextquiz = $this->xml_value($this->hbs_software.'-config-file,'.$this->hbs_quiztype.',next-ex-url')) {
                // there is no next URL given for the next quiz
                return false;
            }

            // set the URL of the next quiz
            $this->nextquiz = $this->xml_locate_file(dirname($this->filepath).'/'.$nextquiz);
        }
        return $this->nextquiz;
    }
    function xml_locate_file($file, $filetypes=null) {
        if (preg_match('|^https?://|', $file)) {
            return $file;
        }

        $filepath = $this->basepath.'/'.$file;
        if (file_exists($filepath)) {
            return $file;
        }

        $filename = basename($filepath);
        if (! $pos = strrpos($filename, '.')) {
            return $file;
        }

        $filetype = substr($filename, $pos + 1);
        if ($filetype=='htm' || $filetype=='html') {
            // $file is a local html file that doesn't exist
            // so search for a HP source file with the same name
            $len = strlen($filetype);
            $filepath = substr($filepath, 0, -$len);
            if (is_null($filetypes)) {
                $filetypes = array('jcl', 'jcw', 'jmt', 'jmx', 'jqz'); // 'jbc' for HP 5 ?
            }
            foreach ($filetypes as $filetype) {
                if (file_exists($filepath.$filetype)) {
                    return substr($file, 0, -$len).$filetype;
                }
            }
        }

        // valid $file could not be found :-(
        return '';
    }

    function xml_get_filecontents() {
        if (! isset($this->xml)) {
            $this->xml = false;
            $this->xml_root = '';

            if (! $this->get_filecontents()) {
                // empty file - shouldn't happen !!
                return false;
            }

            $this->compact_filecontents();
            $this->pre_xmlize_filecontents();

            if (! $this->xml = xmlize($this->filecontents, 0)) {
                debugging('Could not parse XML file: '.$this->filepath);
            }

            $this->xml_root = $this->hbs_software.'-'.$this->hbs_quiztype.'-file';
            if (! array_key_exists($this->xml_root, $this->xml)) {
                debugging('Could not find XML root node: '.$this->xml_root);
            }

            if (isset($this->config) && $this->config->get_filecontents()) {

                $this->config->compact_filecontents();
                $xml = xmlize($this->config->filecontents, 0);

                $config_file = $this->hbs_software.'-config-file';
                if (isset($xml[$config_file]['#']) && isset($this->xml[$this->xml_root]['#'])) {

                    // make sure the xml tree has the expected structure
                    if (! isset($this->xml[$this->xml_root]['#'][$config_file][0]['#'])) {
                        if (! isset($this->xml[$this->xml_root]['#'][$config_file][0])) {
                            if (! isset($this->xml[$this->xml_root]['#'][$config_file])) {
                                $this->xml[$this->xml_root]['#'][$config_file] = array();
                            }
                            $this->xml[$this->xml_root]['#'][$config_file][0] = array();
                        }
                        $this->xml[$this->xml_root]['#'][$config_file][0]['#'] = array();
                    }

                    // reference to the config values in $this->xml
                    $config = &$this->xml[$this->xml_root]['#'][$config_file][0]['#'];

                    $items = array_keys($xml[$config_file]['#']);
                    foreach ($items as $item) { // 'global', 'jcloze', ... etc ..., 'version'
                        if (is_array($xml[$config_file]['#'][$item][0]['#'])) {
                            $values = array_keys($xml[$config_file]['#'][$item][0]['#']);
                            foreach ($values as $value) {
                                $config[$item][0]['#'][$value] = $xml[$config_file]['#'][$item][0]['#'][$value];
                            }
                        }
                    }
                }
            }
        }
        return $this->xml ? true : false;
    }

    function pre_xmlize_filecontents() {
        if ($this->filecontents) {
            // encode all ampersands that are not part of HTML entities
            // http://stackoverflow.com/questions/310572/regex-in-php-to-match-that-arent-html-entities
            // Note: we could also use '<![CDATA[&]]>' as the replace string
            $search = '/&(?!(?:[a-zA-Z]+|#[0-9]+|#x[0-9a-fA-F]+);)/';
            $this->filecontents = preg_replace($search, '&amp;', $this->filecontents);
        }
    }

    function xml_value($tags, $more_tags=null, $default='', $nl2br=true) {
        static $block_elements = null;

        // set reference to a $value in $this->xml array
        if (isset($this->xml_root)) {
            $all_tags = "['".$this->xml_root."']['#']";
        } else {
            $all_tags = ''; // shouldn't happen
        }
        if ($tags) {
            $all_tags .= "['".str_replace(",", "'][0]['#']['", $tags)."']";
        }
        if ($more_tags===null) {
            $all_tags .= "[0]['#']";
        } else {
            $all_tags .= $more_tags;
        }
        $all_tags = explode('][', str_replace("'", '', substr($all_tags, 1, -1)));

        $value = $this->xml;
        foreach ($all_tags as $tag) {
            if (! is_array($value)) {
                return null;
            }
            if(! array_key_exists($tag, $value)) {
                return null;
            }
            $value = $value[$tag];
        }

        if (is_string($value)) {
            // the following 3 lines do not seem to be necessary anymore (4th Oct 2011)
            // and are responsible for messing up double-byte chars in JCross clues
            // if (empty($CFG->unicodedb)) {
            //     $value = utf8_decode($value);
            // }

            // decode angle brackets
            $value = strtr($value, array('&#x003C;'=>'<', '&#x003E;'=>'>', '&#x0026;'=>'&'));

            // remove white space before and after HTML block elements
            if ($block_elements===null) {
                // set regexp to detect white space around html block elements
                $block_elements = array(
                    //'div','p','pre','blockquote','center',
                    //'h1','h2','h3','h4','h5','h6','hr',
                    'table','caption','colgroup','col','tbody','thead','tfoot','tr','th','td',
                    'ol','ul','dl','li','dt','dd',
                    'applet','embed','object','param',
                    'select','optgroup','option',
                    'fieldset','legend',
                    'frameset','frame'
                );
                $space = '(?:\s|(?:<br[^>]*>))*'; // unwanted white space
                $block_elements = '(?:\/?'.implode(')|(?:\/?', $block_elements).')';
                $block_elements = '/'.$space.'(<(?:'.$block_elements.')[^>]*>)'.$space.'/is';
                //.'(?='.'<)' // followed by the start of another tag
            }
            $value = preg_replace($block_elements, '\\1', $value);

            // standardize whitespace within tags
            // $1 : chars before whitespace
            // $2 : whitespace (including <br />)
            $search = '/<(\w+)((?:(?:<br\s*\/?>)|[^>])*)>/ise';
            $replace = '"<\\1".$this->single_line("\\2").">"';
            $value = preg_replace($search, $replace, $value);

            // replace remaining newlines with <br /> but not in <script> or <style> blocks
            // $1 : chars before open text
            // $2 : text to be converted
            // $3 : chars following text
            if ($nl2br) {
                $search = '/(^|(?:<\/(?:script|style)>\s?))(.*?)((?:\s?<(?:script|style)[^>]*>)|$)/ise';
                $replace = '$this->xml_value_nl2br("\\1", "\\2", "\\3")';
                $value = preg_replace($search, $replace, $value);
            }

            // encode unicode characters as HTML entities
            // (in particular, accented characters that have not been encoded by HP)
            $value = $this->utf8_to_entities($value);
        }
        return $value;
    }
    function single_line($str, $quote="'") {
        if ($quote) {
            $str = str_replace('\\'.$quote, $quote, $str);
        }
        return preg_replace('/(?:\s|(?:<br\b[^>]*>))+/i', ' ', $str);
    }
    function xml_value_nl2br($before, $text, $after, $quote="'") {
        if ($quote) {
            $before = str_replace('\\'.$quote, $quote, $before);
            $text = str_replace('\\'.$quote, $quote, $text);
            $after = str_replace('\\'.$quote, $quote, $after);
        }
        return $before.str_replace("\n", '<br />', $text).$after;
    }
    function xml_value_bool($tags, $more_tags=null, $default=false) {
        $value = $this->xml_value($tags, $more_tags, $default, false);
        if (empty($value)) {
            return 'false';
        } else {
            return 'true';
        }
    }
    function xml_value_int($tags, $more_tags=null, $default=0) {
        $value = $this->xml_value($tags, $more_tags, $default, false);
        return intval($value);
    }
    function xml_value_js($tags, $more_tags=null, $default='', $nl2br=true, $convert_to_unicode=true) {
        $value = $this->xml_value($tags, $more_tags, $default, $nl2br);
        return $this->js_value_safe($value, $convert_to_unicode);
    }
    function js_value_safe($str, $convert_to_unicode=false) {
        // encode a string for javascript
        static $replace_pairs = array(
            // backslashes and quotes
            '\\'=>'\\\\', "'"=>"\\'", '"'=>'\\"',
            // newlines (win = "\r\n", mac="\r", linux/unix="\n")
            "\r\n"=>'\\n', "\r"=>'\\n', "\n"=>'\\n',
            // other (closing tag is for XHTML compliance)
            "\0"=>'\\0', '</'=>'<\\/'
        );
        $str = strtr($str, $replace_pairs);

        // convert (hex and decimal) html entities to javascript unicode, if required
        if ($convert_to_unicode) {
            $str = $this->utf8_to_entities($str, 1);
            $str = preg_replace('/&#x([0-9A-F]+);/i', '\\u\\1', $str);
            $str = preg_replace('/&#(\d+);/e', "'\\u'.sprintf('%04X', '\\1')", $str);
        }
        return $str;
    }
    function utf8_to_entities($str, $entity_type=2) {
        // $entity_type: see utf8_to_entity (below)
        // unicode characters can be detected by checking the hex value of a character
        //  00 - 7F : ascii char (roman alphabet + punctuation)
        //  80 - BF : byte 2, 3 or 4 of a unicode char
        //  C0 - DF : 1st byte of 2-byte char
        //  E0 - EF : 1st byte of 3-byte char
        //  F0 - FF : 1st byte of 4-byte char
        // if the string doesn't match any of the above, it might be
        //  80 - FF : single-byte, non-ascii char
        $search = '/'.'[\xc0-\xdf][\x80-\xbf]'.'|'.'[\xe0-\xef][\x80-\xbf]{2}'.'|'.'[\xf0-\xff][\x80-\xbf]{3}'.'|'.'[\x80-\xff]'.'/e';
        return preg_replace($search, '$this->utf8_to_entity("\\0", $entity_type)', $str);
    }
    function utf8_to_entity($char, $entity_type=0) {
        // $entity_type:
        //   2 : html hex entity e.g. &#x12FE;
        //   1 : javascript entity e.g. \u12FE
        //   0 : decimal number e.g. 28001

        // many thanks for the ideas from ...
        // http://www.zend.com/codex.php?id=835&single=1

        // array used to figure out what number to decrement from character order value
        // according to the number of characters used to map unicode to ascii by utf-8
        static $UTF8_DECREMENT = array(
            1=>0, 2=>192, 3=>224, 4=>240 // hex : 1=>0, 2=>0xB, 3=>0xD, 4=>0xE
        );

        // the number of bits to shift each character by
        static $UTF8_SHIFT = array(
            1 => array(0=>0),
            2 => array(0=>6,  1=>0),
            3 => array(0=>12, 1=>6,  2=>0),
            4 => array(0=>18, 1=>12, 2=>6, 3=>0)
        );

        $dec = 0;
        $len = strlen($char);
        for ($pos=0; $pos<$len; $pos++) {
            $ord = ord ($char{$pos});
            $ord -= ($pos ? 128 : $UTF8_DECREMENT[$len]);
            $dec += ($ord << $UTF8_SHIFT[$len][$pos]);
        }
        switch ($entity_type) {
            case 2: return '&#x'.sprintf('%04X', $dec).';';
            case 1 : return '\\u'.sprintf('%04X', $dec);
            default: return $dec;
        }
    }
    function html_entity_decode($str) {
        static $entities_table;

        if (floatval(PHP_VERSION)>=5.0 && function_exists('html_entity_decode')) {
            return html_entity_decode($str, ENT_QUOTES, 'utf-8');
        } else {
            // get html entities table (first time only)
            if (! isset($entities_table)) {
                $entities_table = get_html_translation_table(HTML_ENTITIES);
                $entities_table = array_flip($entities_table);
            }

            // convert numeric html entities
            $str = preg_replace('/&#x([0-9a-f]+);/ie', '$this->dec_to_utf8(hexdec("\\1"))', $str);
            $str = preg_replace('/&#([0-9]+);/e', '$this->dec_to_utf8("\\1")', $str);

            // convert named html entities
            return strtr($str, $entities_table);
        }
    }
    function dec_to_utf8($dec) {
        // thanks to Miguel Perez: http://jp2.php.net/chr (19-Sep-2007)
        if ($dec <= 0x7F) {
            return chr($dec);
        }
        if ($dec <= 0x7FF) {
            return chr(0xC0 | $dec >> 6).chr(0x80 | $dec & 0x3F);
        }
        if ($dec <= 0xFFFF) {
            return chr(0xE0 | $dec >> 12).chr(0x80 | $dec >> 6 & 0x3F).chr(0x80 | $dec & 0x3F);
        }
        if ($dec <= 0x10FFFF) {
            return chr(0xF0 | $dec >> 18).chr(0x80 | $dec >> 12 & 0x3F).chr(0x80 | $dec >> 6 & 0x3F).chr(0x80 | $dec & 0x3F);
        }
        return '';
    }

    // synchonize file and Moodle settings
    function synchronize_moodle_settings(&$quiz) {
        $name = $this->get_name();
        if ($name=='' || $name==$quiz->name) {
            return false;
        } else {
            $quiz->name = $name;
            return true;
        }
    }
} // end class
?>