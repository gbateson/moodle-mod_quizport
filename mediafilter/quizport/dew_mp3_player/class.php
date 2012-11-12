<?php
class quizport_mediaplayer_dew_mp3_player extends quizport_mediaplayer {
    var $aliases = array('dew');
    var $playerurl = 'dew_mp3_player/dewplayer.swf';
    var $querystring_mediaurl = 'mp3';
    var $more_options = array(
        'width' => 200, 'height' => 20, 'flashvars' => '',
        // 'bgcolor' => 'FFFFFF', 'wmode' => 'transparent',
        // 'autostart' => 0, 'autoreplay' => 0, 'showtime' => 0, 'randomplay' => 0, 'nopointer' => 0
    );
}
?>