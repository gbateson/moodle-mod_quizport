<?php
class quizport_output_hp_6_jcloze_xml_anctscan_autoadvance extends quizport_output_hp_6_jcloze_xml_anctscan {

    function fix_js_StartUp(&$str, $start, $length) {
        $this->jcloze_autoadvance_fix_js_StartUp($str, $start, $length);
    }

    function jcloze_autoadvance_gapid() {
        return '^GapSpan([0-9]+)$';
    }

    function jcloze_autoadvance_gaptype() {
        return 'span';
    }

    function fix_js_CheckExStatus(&$str, $start, $length) {
        $substr = substr($str, $start, $length);

        // do standard fixes to this function
        parent::fix_js_CheckExStatus($substr, 0, $length);

        // javascript regexp to match id of a Gap
        $gapid = $this->jcloze_autoadvance_gapid();

        $search = '	if (ExStatus){';
        if ($pos = strpos($substr, $search)) {
            $insert = ''
                ."	var li = ListItems[CurrentListItem];\n"
                ."	if (li.AnsweredCorrectly==false) {\n"
                ."\n"
                ."		var gapid = new RegExp('$gapid');\n"
                ."		var ListItemScore = 0;\n"
                ."\n"
                ."		var g_correct = 0;\n"
                ."		var g_wrong = 0;\n"
                ."		var g_max = li.gaps.length;\n"
                ."		for (var g=0; g<g_max; g++) {\n"
                ."\n"
                ."			var m = li.gaps[g].id.match(gapid);\n"
                ."			if (! m) {\n"
                ."				continue;\n"
                ."			}\n"
                ."\n"
                ."			var i = parseInt(m[1]);\n"
                ."			if (! GapList[i]) {\n"
                ."				continue;\n"
                ."			}\n"
                ."\n"
                ."			if (GapList[i][1].ErrorFound) {\n"
                ."				g_correct++;\n"
                ."			}\n"
                ."		}\n"
                ."\n"
                ."		var span = li.getElementsByTagName('span');\n"
                ."		var s_max = span.length;\n"
                ."		for (var s=0; s<s_max; s++) {\n"
                ."			if (span[s].id) {\n"
                ."				continue;\n"
                ."			}\n"
                ."			if(span[s].getAttribute(AA_className())=='SelectedGapSpan') {\n"
                ."				g_wrong++;\n"
                ."			}\n"
                ."		}\n"
                ."\n"
                ."		li.AnsweredCorrectly = (g_correct==g_max);\n"
                ."		if (li.AnsweredCorrectly) {\n"
                ."			if (g_correct > g_wrong) {\n"
                ."				li.score = Math.floor(100 * ((g_correct - g_wrong) / g_max));\n"
                ."			} else {\n"
                ."				li.score = 0;\n"
                ."			}\n"
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
            ;
            $substr = substr_replace($substr, $insert, $pos, 0);
        }

        $str = substr_replace($str, $substr, $start, $length);
    }

    function fix_headcontent() {
        global $CFG;
        if ($pos = strpos($this->headcontent, '<style')) {
            $path = str_replace('_', '/', get_class($this)); // 'quizport/output/hp/6/jcloze/xml/anctscan/autoadvance'
            $insert = '<link rel="stylesheet" type="text/css" href="'.$CFG->wwwroot.'/mod/'.$path.'/styles.css" />'."\n";
            $this->headcontent = substr_replace($this->headcontent, $insert, $pos, 0);
        }
        parent::fix_headcontent();
    }

    function fix_bodycontent() {

        // adjust indentation
        $search = '/<form id="Cloze"[^>]*>\s*<div class="ClozeBody"[^>]*>\s*<ol[^>]*>(.*?)<\/ol>\s*<\/div>\s*<\/form>/is';
        if (preg_match($search, $this->bodycontent, $matches, PREG_OFFSET_CAPTURE)) {

            $match = $matches[1][0];
            $start = $matches[1][1];
            $length = strlen($match);

            $search = '/(<li[^>]*>)(.*?)(<\/li>)/s';
            $callback = array($this, 'fix_bodycontent_listitem');
            $match = preg_replace_callback($search, $callback, $match);

            $this->bodycontent = substr_replace($this->bodycontent, $match, $start, $length);
        }

        // continue with usual rottmeier adjustments
        $this->fix_bodycontent_rottmeier(true);
    }

    function fix_bodycontent_listitem($match)  {
        // match contains a single <li> listitem
        $starttag = $match[1];
        $content  = $match[2];
        $endtag   = $match[3];

        $search = '/(?:<br \/>\s*)?(\*+)(.*?)((?:<br \/>)|$)/';
        if (preg_match_all($search, $content, $lines, PREG_OFFSET_CAPTURE)) {

            $i_max = count($lines[0]) - 1;
            for ($i=$i_max; $i>=0; $i--) {

                $match = $lines[0][$i][0];
                $start = $lines[0][$i][1];
                $length = strlen($match);

                $indent = strlen($lines[1][$i][0]); // count asterisks
                $match = '<div class="indent'.$indent.'">'.$lines[2][$i][0].'</div>';

                $content = substr_replace($content, $match, $start, $length);
            }
        }

        return $starttag.$content.$endtag;
    }
}
?>