<?php
/** Шаблон модального диалога */
$this->registerJsFile('@web/js/_modal_dialog.js');
$this->registerCssFile("@web/css/_modal_dialog.css");
?>
<div class="popup-fade">
    <div class="popup">
        <div class="popup-close"></div>
        <div class="popup_label"></div>
        <div class="popup_info"></div>
        <div class="popup_main-block"></div>
    </div>
</div>