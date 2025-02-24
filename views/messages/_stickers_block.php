<?php
$out = '<div class="telegram_stikers_block">';
$out .= '<div class="telegram_stikers_block_head">';
$out .= '<div id="close_stikers_block"><img src="/img/cross.svg" /></div>';
$out .= '</div>';

$dir_stikers = $_SERVER['DOCUMENT_ROOT'].'/web/img/stickers/';
$dirs_stikers = array_diff( scandir($dir_stikers), array('..', '.') );

$dir = '';

if (!empty($dirs_stikers)) {
    $out .= '<div class="telegram_stikers_block_nav">';
    foreach($dirs_stikers as $dir_st) {
        $dir = ($dir == '') ? $dir_st : $dir;
        $files_stikers = array_diff( scandir($dir_stikers.$dir_st), array('..', '.') );
        $out .= '<div id="'.$dir_st.'" class="telegram_stikers_block_nav_item">';
        $out .= '<span><img src="/img/stickers/'.$dir_st.'/'.$files_stikers[2].'"></span>';
        $out .= '</div>';
    }
    $out .= '</div>';

}
$out .= '<div class="telegram_stikers_block_list">';

$files_stikers = array_diff( scandir($dir_stikers.$dir), array('..', '.') );

if (!empty($files_stikers)) {
    foreach($files_stikers as $file) {
        $out .= '<span><img src="/img/stickers/'.$dir.'/'.$file.'"></span>';
    }
} else {
    $out .= 'This sicker pack is missing.';
}

$out .= '</div>';
$out .= '</div>';


echo $out;