<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

IncludeModuleLangFile(__FILE__);

if($APPLICATION->GetGroupRight("onpeak.emulator1c") > "D") {
    $aMenu = array(
        'parent_menu' => 'global_menu_content',
        'section' => 'onpeak.emulator1c',
        'sort' => 400,
        'text' => 'Эмуляция 1С выгрузки',
        'icon' => 'onpeak_emulator1c_menu_icon',
        'page_icon' => 'onpeak_emulator1c_page_icon',
        'items_id' => 'menu_onpeak_emulator1c',
        'items' => array(
            array(
                'text' => 'Импорт файлов',
                'url' => '/bitrix/admin/onpeak_emulator1c_import.php?lang='.LANGUAGE_ID,
                'more_url' => array('onpeak_emulator1c_import.php'),
                'title' => 'Импорт файлов из 1С'
            ),
            array(
                'text' => 'Статистика',
                'url' => '/bitrix/admin/onpeak_emulator1c_stats.php?lang='.LANGUAGE_ID,
                'more_url' => array('onpeak_emulator1c_stats.php'),
                'title' => 'Статистика импорта'
            ),
        )
    );
    return $aMenu;
}