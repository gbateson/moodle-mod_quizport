<?php
class quizport_output_hp_6_jquiz_xml extends quizport_output_hp_6_jquiz {
    function expand_ItemArray() {
        $q = 0;
        $qq = 0;
        $str = 'I=new Array();'."\n";
        $tags = 'data,questions,question-record';
        while (($question="[$q]['#']") && $this->source->xml_value($tags, $question) && ($answers = $question."['answers'][0]['#']") && $this->source->xml_value($tags, $answers)) {

            $question_type = $this->source->xml_value_int($tags, $question."['question-type'][0]['#']");
            $weighting = $this->source->xml_value_int($tags, $question."['weighting'][0]['#']");
            $clue = $this->source->xml_value_js($tags, $question."['clue'][0]['#']");

            $a = 0;
            $aa = 0;
            while (($answer = $answers."['answer'][$a]['#']") && $this->source->xml_value($tags, $answer)) {
                $text     = $this->expand_ItemArray_answertext($tags,  $answer, $a);
                $feedback = $this->source->xml_value_js($tags,  $answer."['feedback'][0]['#']");
                $correct  = $this->source->xml_value_int($tags, $answer."['correct'][0]['#']");
                $percent  = $this->source->xml_value_int($tags, $answer."['percent-correct'][0]['#']");
                $include  = $this->source->xml_value_int($tags, $answer."['include-in-mc-options'][0]['#']");
                if (strlen($text)) {
                    if ($aa==0) { // first time only
                        $str .= "\n";
                        $str .= "I[$qq] = new Array();\n";
                        $str .= "I[$qq][0] = $weighting;\n";
                        $str .= "I[$qq][1] = '$clue';\n";
                        $str .= "I[$qq][2] = '".($question_type-1)."';\n";
                        $str .= "I[$qq][3] = new Array();\n";
                    }
                    $text = $this->source->single_line($text, '');
                    $str .= "I[$qq][3][$aa] = new Array('$text','$feedback',$correct,$percent,$include);\n";
                    $aa++;
                }
                $a++;
            }
            if ($aa) {
                $qq++;
            }
            $q++;
        }
        return $str;
    }
    function expand_ItemArray_answertext($tags,  $answer, $a) {
        return $this->source->xml_value_js($tags,  $answer."['text'][0]['#']");
    }
}
?>