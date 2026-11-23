<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

IncludeModuleLangFile(__FILE__);

if($APPLICATION->GetGroupRight("ps.emulator1c") > "D") {
    $aMenu = array(
        'parent_menu' => 'global_menu_services',
        'section' => 'ps.emulator1c',
        'sort' => 400,
        'text' => 'Эмуляция обмена с 1С',
        'icon' => 'ps_emulator1c_menu_icon',
        'page_icon' => 'ps_emulator1c_page_icon',
        'items_id' => 'menu_ps_emulator1c',
        'items' => array(
            array(
                'text' => 'Импорт каталога',
                'url' => '/bitrix/admin/ps_emulator1c_import.php?lang='.LANGUAGE_ID,
                'more_url' => array('ps_emulator1c_import.php'),
                'title' => 'Полная сессия импорта каталога из 1С'
            ),
            array(
                'text' => 'Заказы в 1С',
                'url' => '/bitrix/admin/ps_emulator1c_sale.php?lang='.LANGUAGE_ID,
                'more_url' => array('ps_emulator1c_sale.php'),
                'title' => 'Экспорт заказов в 1С (что получит 1С)'
            ),
            array(
                'text' => 'Статистика',
                'url' => '/bitrix/admin/ps_emulator1c_stats.php?lang='.LANGUAGE_ID,
                'more_url' => array('ps_emulator1c_stats.php'),
                'title' => 'Статистика эмуляций обмена'
            ),
        )
    );
    return $aMenu;
}