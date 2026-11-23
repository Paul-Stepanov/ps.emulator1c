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

$APPLICATION->SetTitle("Импорт каталога из 1С (эмуляция)");

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
Asset::getInstance()->addJs('/bitrix/js/ps.emulator1c/import.js?v=1.2.5');

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<div class="emu-page">
    <div class="emu-intro">
        Эмуляция ходит в штатный <b>/bitrix/admin/1c_exchange.php</b> и автоматически соблюдает
        <a href="/bitrix/admin/1c_admin.php?lang=ru">настройки обмена</a>
        (интервал шага, zip, кэш инфоблока, действия с отсутствующими элементами/разделами) — как живая 1С.
        Порядок теста живой выгрузки: сначала <b>import0_1.xml</b> (товары), затем <b>offers0_1.xml</b> (цены и остатки).
        Файлы передаются в ядро частями, как это делает 1С.
    </div>

    <div class="emu-controls">
        <input type="file" id="import_file_input" accept=".xml,.zip">
        <button type="button" id="import_start_btn" class="emu-btn emu-btn--primary">Начать импорт</button>
        <button type="button" id="import_stop_btn" class="emu-btn emu-btn--danger" disabled>Остановить</button>
    </div>

    <div class="emu-console">
        <div class="emu-console-head">
            <span class="emu-console-title" id="emu_title">Импорт каталога</span>
            <span class="emu-state emu-state--idle" id="emu_state">Ожидание</span>
            <span class="emu-console-meta" id="timer">Выберите файл и нажмите «Начать импорт»</span>
        </div>
        <div class="emu-steps" id="emu_steps"></div>
        <div class="emu-console-body">
            <div class="emu-empty">Выберите import0_1.xml / offers0_1.xml (или .zip) и нажмите «Начать импорт» — лог сессии появится здесь.</div>
            <div id="log"></div>
        </div>
        <div class="emu-console-foot" id="load" style="display:none"></div>
    </div>
</div>

<script>
BX.ready(function(){
    PsEmulator1C.init({
        logId: 'log',
        timerId: 'timer',
        loadId: 'load',
        fileInputId: 'import_file_input',
        startBtnId: 'import_start_btn',
        stopBtnId: 'import_stop_btn',
        stateId: 'emu_state',
        titleId: 'emu_title',
        stepsId: 'emu_steps'
    });
});
</script>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
