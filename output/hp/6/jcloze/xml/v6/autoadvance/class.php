<?php
class quizport_output_hp_6_jcloze_xml_v6_autoadvance extends quizport_output_hp_6_jcloze_xml_v6 {

    function get_js_functionnames() {
        // start list of function names
        $names = parent::get_js_functionnames();
        $names .= ($names ? ',' : '').'CheckAnswer';
        return $names;
    }

    function fix_js_StartUp(&$str, $start, $length) {
        $this->jcloze_autoadvance_fix_js_StartUp($str, $start, $length);
    }

    function jcloze_autoadvance_gapid() {
        return '^Gap([0-9]+)$';
    }

    function jcloze_autoadvance_gaptype() {
        if ($this->use_DropDownList()) {
            return 'select';
        } else {
            return 'input';
        }
    }

    function fix_js_CheckAnswer(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // make sure we trim answer as  well as response when checking for correctness
        $search = '/(?<=TrimString\(UpperGuess\) == )(UpperAnswer)/';
        $substr = preg_replace($search, 'TrimString($1)', $substr);

        if ($this->use_DropDownList()) {
            // only treat 1st possible answer as correct
            $substr = str_replace('I[GapNum][1].length', '1', $substr);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_js_CheckAnswers(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // javascript regexp to match id of a Gap
        $gapid = $this->jcloze_autoadvance_gapid();

        $search = '/for \(var i \= 0; i<I\.length; i\+\+\)\{(.*?)(?=var TotalScore = 0;)/s';
        $replace = "\n"
            ."	var clues = new Array();\n"
            ."	var li = ListItems[CurrentListItem];\n"
            ."	if (li.AnsweredCorrectly==false) {\n"
            ."\n"
            ."		var gapid = new RegExp('$gapid');\n"
            ."		var ListItemScore = 0;\n"
            ."\n"
            ."		var g_max = li.gaps.length;\n"
            ."		for (var g=0; g<g_max; g++) {\n"
            ."\n"
            ."			var m = li.gaps[g].id.match(gapid);\n"
            ."			if (! m) {\n"
            ."				continue;\n"
            ."			}\n"
            ."\n"
            ."			var i = parseInt(m[1]);\n"
            ."			if (! State[i]) {\n"
            ."				continue;\n"
            ."			}\n"
            ."\n"
            ."			if (State[i].AnsweredCorrectly) {\n"
            ."				ListItemScore += State[i].ItemScore;\n"
            ."			} else {\n"
            ."				var GapValue = GetGapValue(i);\n"
            ."				if (typeof(GapValue)=='string' && GapValue=='') {\n"
            ."					// not answered yet\n"
            ."					AllCorrect = false;\n"
            ."				} else if (CheckAnswer(i, true) > -1) {\n"
            ."					// correct answer\n"
            ."					var TotalChars = GapValue.length;\n"
            ."					State[i].ItemScore = (TotalChars-State[i].HintsAndChecks)/TotalChars;\n"
            ."					if (State[i].ClueGiven){\n"
            ."						State[i].ItemScore /= 2;\n"
            ."					}\n"
            ."					if (State[i].ItemScore < 0){\n"
            ."						State[i].ItemScore = 0;\n"
            ."					}\n"
            ."					State[i].AnsweredCorrectly = true;\n"
            ."					SetCorrectAnswer(i, GapValue);\n"
            ."					ListItemScore += State[i].ItemScore;\n"
            ."				} else {\n"
            ."					// wrong answer\n"
            ."					var clue = I[i][2];\n"
            ."					if (clue) {\n"
            ."						var c_max = clues.length;\n"
            ."						for (var c=0; c<c_max; c++) {\n"
            ."							if (clues[c]==clue) {\n"
            ."								break;\n"
            ."							}\n"
            ."						}\n"
            ."						if (c==c_max) {\n"
            ."							clues[c] = clue;\n"
            ."						}\n"
            ."						State[i].ClueGiven = true;\n"
            ."					}\n"
            ."					AllCorrect = false;\n"
            ."				}\n"
            ."			}\n"
            ."		}\n"
            ."		li.AnsweredCorrectly = AllCorrect;\n"
            ."		if (li.AnsweredCorrectly) {\n"
            ."			li.score = Math.round(100 * (ListItemScore / g_max));\n"
            ."			var next_i = CurrentListItem;\n"
            ."			var i_max = ListItems.length;\n"
            ."			for (var i=0; i<i_max; i++) {\n"
            ."				var next_i = (CurrentListItem + i + 1) % i_max;\n"
            ."				if (ListItems[next_i].AnsweredCorrectly==false) {\n"
            ."					break;\n"
            ."				}\n"
            ."			}\n"
            ."			if (next_i==CurrentListItem) {\n"
            ."				AA_SetProgressBar(next_i);\n"
            ."			} else {\n"
            ."				AA_ChangeListItem(next_i);\n"
            ."			}\n"
            ."		}\n"
            ."	}\n"
            ."	li = null;\n"
            ."	clues = clues.join('\\n\\n');\n"
            .'	'
        ;
        $substr = preg_replace($search, $replace, $substr, 1);

        $search = '		TotalScore += State[i].ItemScore;';
        if ($pos = strpos($substr, $search)) {
            $insert = ''
                ."		if (State[i].AnsweredCorrectly==false) {\n"
                ."			AllCorrect = false;\n"
                ."		}\n"
            ;
            $substr = substr_replace($substr, $insert, $pos, 0);
        }

        $search = 'Output += Incorrect';
        if ($pos = strpos($substr, $search)) {
            $insert = 'Output += (clues ? clues : Incorrect)';
            $substr = substr_replace($substr, $insert, $pos, strlen($search));
        }

        $search = 'ShowMessage(Output)';
        if ($pos = strpos($substr, $search)) {
            $insert = 'if (clues || AllCorrect) ';
            $substr = substr_replace($substr, $insert, $pos, 0);
        }

        $search = "setTimeout('WriteToInstructions(Output)', 50);";
        if ($pos = strpos($substr, $search)) {
            $substr = substr_replace($substr, '', $pos, strlen($search));
        }

        parent::fix_js_CheckAnswers($substr, 0, strlen($substr));
        $str = substr_replace($str, $substr, $start, $length);
    }

    function expand_ClozeBody() {
        $str = '';

        $wordlist = $this->setup_wordlist();

        // cache clues flag and caption
        $includeclues = $this->expand_Clues();
        $cluecaption = $this->expand_ClueCaption();

        // detect if cloze starts with gap
        if (strpos($this->source->filecontents, '<gap-fill><question-record>')) {
            $startwithgap = true;
        } else {
            $startwithgap = false;
        }

        // initialize loop values
        $q = 0;
        $tags = 'data,gap-fill';
        $question_record = "$tags,question-record";

        // loop through text and gaps
        $looping = true;
        while ($looping) {
            $text = $this->source->xml_value($tags, "[0]['#'][$q]");
            $gap = '';
            if (($question="[$q]['#']") && $this->source->xml_value($question_record, $question)) {
                $gap .= '<span class="GapSpan" id="GapSpan'.$q.'">';
                if (is_array($wordlist)) {
                    $gap .= '<select id="Gap'.$q.'"><option value=""></option>'.$wordlist[$q].'</select>';
                } else if ($wordlist) {
                    $gap .= '<select id="Gap'.$q.'"><option value=""></option>'.$wordlist.'</select>';
                } else {
                    // minimum gap size
                    if (! $gapsize = $this->source->xml_value_int($this->source->hbs_software.'-config-file,'.$this->source->hbs_quiztype.',minimum-gap-size')) {
                        $gapsize = 6;
                    }

                    // increase gap size to length of longest answer for this gap
                    $a = 0;
                    while (($answer=$question."['answer'][$a]['#']") && $this->source->xml_value($question_record, $answer)) {
                        $answertext = $this->source->xml_value($question_record,  $answer."['text'][0]['#']");
                        $answertext = preg_replace('/&[#a-zA-Z0-9]+;/', 'x', $answertext);
                        $gapsize = max($gapsize, strlen($answertext));
                        $a++;
                    }

                    $gap .= '<input type="text" id="Gap'.$q.'" onfocus="TrackFocus('.$q.')" onblur="LeaveGap()" class="GapBox" size="'.$gapsize.'"></input>';
                }
                if ($includeclues) {
                    $clue = $this->source->xml_value($question_record, $question."['clue'][0]['#']");
                    if (strlen($clue)) {
                        $gap .= '<button style="line-height: 1.0" class="FuncButton" onfocus="FuncBtnOver(this)" onmouseover="FuncBtnOver(this)" onblur="FuncBtnOut(this)" onmouseout="FuncBtnOut(this)" onmousedown="FuncBtnDown(this)" onmouseup="FuncBtnOut(this)" onclick="ShowClue('.$q.')">'.$cluecaption.'</button>';
                    }
                }
                $gap .= '</span>';
            }
            if (strlen($text) || strlen($gap)) {
                if ($startwithgap) {
                    $str .= $gap.$text;
                } else {
                    $str .= $text.$gap;
                }
                $q++;
            } else {
                // no text or gap, so force end of loop
                $looping = false;
            }
        }
        if ($q==0) {
            // oops, no gaps found!
            return $this->source->xml_value($tags);
        } else {
            return $str;
        }
    }

    function setup_wordlist() {

        // get drop down list of words
        $words = array();
        $wordlists = array();
        $singlewordlist = true;

        if ($this->use_DropDownList()) {
            $q = 0;
            $tags = 'data,gap-fill,question-record';
            while (($question="[$q]['#']") && $this->source->xml_value($tags, $question)) {
                $a = 0;
                $aa = 0;
                while (($answer=$question."['answer'][$a]['#']") && $this->source->xml_value($tags, $answer)) {
                    $text = $this->source->xml_value($tags,  $answer."['text'][0]['#']");
                    if (strlen($text)) {
                        $wordlists[$q][$aa] = $text;
                        $words[] = $text;
                        $aa++;
                    }
                    $a++;
                }
                if ($aa) {
                    $wordlists[$q] = array_unique($wordlists[$q]);
                    sort($wordlists[$q]);

                    $wordlist = '';
                    foreach ($wordlists[$q] as $word) {
                        $wordlist .= '<option value="'.$word.'">'.$word.'</option>';
                    }
                    $wordlists[$q] = $wordlist;

                    if ($aa >= 2) {
                        $singlewordlist = false;
                    }
                }
                $q++;
            }

            $words = array_unique($words);
            sort($words);
        }

        if ($singlewordlist) {
            $wordlist = '';
            foreach ($words as $word) {
                $wordlist .= '<option value="'.$word.'">'.$word.'</option>';
            }
            return $wordlist;
        } else {
            return $wordlists;
        }
    }
}
?>
