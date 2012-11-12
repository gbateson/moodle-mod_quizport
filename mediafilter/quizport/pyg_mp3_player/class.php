<?php
class quizport_mediaplayer_pyg_mp3_player extends quizport_mediaplayer {
    var $aliases = array('pyg');
    var $playerurl = 'pyg_mp3_player/pyg_mp3_player.swf';
    var $flashvars_mediaurl = 'file';
    var $more_options = array(
        'width' => 180, 'height' => 30, 'my_BackgroundColor' => '0xE6E6FA', 'autolaunch' => 'false'
    );
    var $flashvars = array(
        'my_BackgroundColor' => PARAM_ALPHANUM, 'autolaunch' => PARAM_ALPHANUM
    );
}
?>