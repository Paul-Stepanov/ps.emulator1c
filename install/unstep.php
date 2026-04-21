<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
?>
<form action="<?
echo $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?
    echo LANGUAGE_ID ?>">
    <input type="hidden" name="id" value="ps.emulator1c">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">
    <p>Модуль успешно удален</p>
    <input type="submit" name="uninst" value="OK">
</form>