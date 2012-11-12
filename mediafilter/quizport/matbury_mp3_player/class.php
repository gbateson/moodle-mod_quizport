<?php
class quizport_mediaplayer_matbury_mp3_player extends quizport_mediaplayer {
    var $aliases = array('matbury');
    var $playerurl = 'matbury_mp3_player/matbury_mp3_player.swf';
    var $flashvars_mediaurl = 'mp3url';
    var $more_options = array(
        'width' => 200, 'height' => 18, 'majorversion' => 9, 'build' => 115,
        'timesToPlay' => 1, 'showPlay' => 'true', 'waitToPlay' => 'true'
    );
    var $flashvars = array(
        'timesToPlay' => PARAM_INT, 'showPlay' => PARAM_ALPHANUM, 'waitToPlay' => PARAM_ALPHANUM
    );
}
?>