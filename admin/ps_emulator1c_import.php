<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Page\Asset;
use Ps\Emulator1c\Importer;
use Ps\Emulator1c\Logger;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm("Доступ запрещен");
}

if (!CModule::IncludeModule("ps.emulator1c")) {
    ShowError("Модуль не установлен");
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    die();
}

$APPLICATION->SetTitle("Импорт файлов из 1С");

// Инициализируем импортер
$importer = new Importer();

// Если это AJAX запрос для обнуления шага
if(isset($_REQUEST['ajax_action']) && $_REQUEST['ajax_action'] == 'reset_step') {
    $APPLICATION->RestartBuffer();
    header('Content-Type: application/json');
    
    $importer->resetImportStep();
    
    echo json_encode(['success' => true]);
    die();
}

// Если это AJAX запрос для логирования
if(isset($_REQUEST['ajax_action']) && $_REQUEST['ajax_action'] == 'log_status') {
    $APPLICATION->RestartBuffer();
    header('Content-Type: application/json');
    
    Logger::log(
        $_REQUEST['filename'],
        $_REQUEST['status'],
        $_REQUEST['message']
    );
    
    echo json_encode(['success' => true]);
    die();
}

// Подключаем CSS и JS файлы
$APPLICATION->SetAdditionalCSS('/bitrix/css/ps.emulator1c/style.css');
Asset::getInstance()->addJs('/bitrix/js/ps.emulator1c/import.js');

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$aTabs = array(
    array(
        "DIV" => "edit1",
        "TAB" => "Импорт файлов",
        "ICON" => "main_user_edit",
        "TITLE" => "Импорт файлов из 1С"
    ),
);

$tabControl = new CAdminTabControl("tabControl", $aTabs);

// Если загружен файл
$uploadResult = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file']) && check_bitrix_sessid()) {
    $uploadResult = $importer->uploadFile($_FILES['import_file']);
}
?>

<form method="POST" action="<?=$APPLICATION->GetCurPage()?>" name="import_form" enctype="multipart/form-data">
    <?=bitrix_sessid_post()?>
    
    <?php $tabControl->Begin(); ?>
    
    <?php $tabControl->BeginNextTab(); ?>
    
    <tr>
        <td width="40%">Выберите xml файл для импорта:</td>
        <td width="60%">
            <input type="file" name="import_file">
        </td>
    </tr>
    
    <?php if(isset($uploadResult['success']) && $uploadResult['success']): ?>
    <tr>
        <td colspan="2">
            <div class="emulator-import-container">
                <div class="adm-info-message-wrap">
                    <div class="adm-info-message">
                        <div id="log" class="emulator-import-log"></div>
                        <div align="right" id="load"></div>
                    </div>
                    <div id="timer" class="emulator-import-timer"></div>
                    
                    <div class="emulator-import-buttons">
                        <input type="button" value="Обнулить шаг" onclick="PsEmulator1C.resetStep()" class="adm-btn" style="color:red;">
                        <input type="button" value="Остановить импорт" onclick="PsEmulator1C.stopImport()" class="adm-btn" style="color:red;">
                    </div>
                </div>
            </div>
            
            <script>
            BX.ready(function(){
                PsEmulator1C.init({
                    logId: 'log',
                    timerId: 'timer',
                    loadId: 'load'
                });
                PsEmulator1C.startImport('<?=CUtil::JSEscape($uploadResult['fileName'])?>');
            });
            </script>
        </td>
    </tr>
    <?php endif; ?>
    
    <?php $tabControl->Buttons(); ?>
    
    <input type="submit" class="adm-btn-save" name="Import" value="Загрузить">
    
    <?php $tabControl->End(); ?>
</form>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>