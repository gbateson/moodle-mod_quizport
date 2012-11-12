<?php
class quizport_output_hp_6_rhubarb_xml extends quizport_output_hp_6_rhubarb {

    function expand_JSRhubarb6() {
        return $this->expand_template('rhubarb6.js_');
    }

    function expand_Finished() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,global,finished');
    }

    function expand_GuessHere() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,global,type-your-guess-here');
    }

    function expand_IncorrectWords() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',incorrect-words');
    }

    function expand_PreparingExercise() {
        return $this->source->xml_value_js($this->source->hbs_software.'-config-file,global,preparing-exercise');
    }

    function expand_Solution() {
        return $this->source->xml_value_int($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',include-solution');
    }

    function expand_WordsArray() {
        $str = '';

        $space = ' \\x09\\x0A\\x0C\\x0D'; // " \t\n\r\l"
        $punc = preg_quote('!"#$%&()*+,-./:;+<=>?@[]\\^_`{|}~', '/'); // not apostrophe \'
        $search = '/([^'.$punc.$space.']+)|(['.$punc.']['.$punc.$space.']*)/s';

        if (preg_match_all($search, $this->source->xml_value('data,rhubarb-text'), $matches)) {
            foreach ($matches[0] as $i => $word) {
                $str .= "Words[$i] = '".$this->source->js_value_safe($word, true)."';\n";
            }
        }
        return $str;
    }

    function expand_FreeWordsArray() {
        $str = '';
        $i =0;
        $tags = 'data,free-words,free-word';
        while ($word = $this->source->xml_value($tags, "[$i]['#']")) {
            $str .= "FreeWords[$i] = '".$this->source->js_value_safe($word, true)."';\n";
            $i++;
        }
        return $str;
    }

    function expand_StyleSheet() {
        return $this->expand_template('tt3.cs_');
    }
}
?>
