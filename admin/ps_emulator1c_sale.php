<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Page\Asset;
use Ps\Emulator1c\Logger;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm("Доступ запрещен");
}

if (!CModule::IncludeModule("ps.emulator1c")) {
    ShowError("Модуль не установлен");
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    die();
}

$APPLICATION->SetTitle("Экспорт заказов в 1С (эмуляция)");

if (isset($_REQUEST['ajax_action']) && $_REQUEST['ajax_action'] == 'log_status') {
    $APPLICATION->RestartBuffer();
    header('Content-Type: application/json');

    if (!check_bitrix_sessid()) {
        echo json_encode(['success' => false]);
        die();
    }

    Logger::log(
        $_REQUEST['filename'],
        $_REQUEST['status'],
        $_REQUEST['message']
    );

    echo json_encode(['success' => true]);
    die();
}

$APPLICATION->SetAdditionalCSS('/bitrix/css/ps.emulator1c/style.css?v=1.2.5');
Asset::getInstance()->addJs('/bitrix/js/ps.emulator1c/sale.js?v=1.2.5');

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<div class="emu-page">
    <div class="emu-intro">
        Эмуляция этапа B1 живого обмена: сайт отвечает на запросы 1С порциями XML по фильтрам из
        <a href="/bitrix/admin/1c_admin.php?lang=ru">настроек обмена</a>
        (какие заказы выгружать — оплата/доставка/статусы; сейчас сайт выгружает обновлённые заказы сайта s1).
        Результат — файл «что получит 1С» (скачивается по завершении). Обратный импорт ответа 1С (B2) не входит в эмуляцию.
    </div>

    <div class="emu-controls">
        <button type="button" id="sale_start_btn" class="emu-btn emu-btn--primary">Начать экспорт</button>
        <button type="button" id="sale_stop_btn" class="emu-btn emu-btn--danger" disabled>Остановить</button>
    </div>

    <div class="emu-console">
        <div class="emu-console-head">
            <span class="emu-console-title" id="emu_title">Экспорт заказов в 1С</span>
            <span class="emu-state emu-state--idle" id="emu_state">Ожидание</span>
            <span class="emu-console-meta" id="timer">Экспорт ещё не запускался</span>
        </div>
        <div class="emu-steps" id="emu_steps"></div>
        <div class="emu-console-body">
            <div class="emu-empty">Нажмите «Начать экспорт» — сайт отдаст заказы порциями XML и соберёт файл CommerceML, который получила бы 1С.</div>
            <div id="log"></div>
        </div>
        <div class="emu-console-foot" id="load" style="display:none"></div>
    </div>
</div>

<script>
BX.ready(function(){
    PsEmulator1CSale.init({
        logId: 'log',
        timerId: 'timer',
        loadId: 'load',
        startBtnId: 'sale_start_btn',
        stopBtnId: 'sale_stop_btn',
        stateId: 'emu_state',
        titleId: 'emu_title',
        stepsId: 'emu_steps'
    });
});
</script>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
