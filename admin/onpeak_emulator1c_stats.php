<?php

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Page\Asset;
use Onpeak\Emulator1c\Stats;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm("Доступ запрещен");
}

if (!CModule::IncludeModule("onpeak.emulator1c")) {
    ShowError("Модуль не установлен");
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
    die();
}

$APPLICATION->SetTitle("Статистика импорта 1С");

// Подключаем CSS
Asset::getInstance()->addCss('/bitrix/js/onpeak.emulator1c/style.css');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

// Обработка возможных ошибок
try {
    // Получаем статистику
    $generalStats = Stats::getGeneralStats();
    $latestImports = Stats::getLatestImports(15);
    $fileStats = Stats::getFileStats(10);

    $hasError = false;
} catch (\Exception $e) {
    ShowError("Ошибка при получении статистики: " . $e->getMessage());
    $hasError = true;
}

$aTabs = [
    [
        "DIV" => "edit1",
        "TAB" => "Общая статистика",
        "ICON" => "main_user_edit",
        "TITLE" => "Общая статистика импорта",
    ],
    [
        "DIV" => "edit2",
        "TAB" => "Последние импорты",
        "ICON" => "main_user_edit",
        "TITLE" => "Последние импорты",
    ],
    [
        "DIV" => "edit3",
        "TAB" => "Статистика по файлам",
        "ICON" => "main_user_edit",
        "TITLE" => "Статистика по файлам",
    ],
];

$tabControl = new CAdminTabControl("tabControl", $aTabs);
$tabControl->Begin();

if (!$hasError) {
    ?>

    <?php
    $tabControl->BeginNextTab(); ?>
    <tr>
        <td width="40%">Всего успешных импортов:</td>
        <td width="60%"><?php
            echo $generalStats['total_imports']; ?></td>
    </tr>
    <tr>
        <td>Импортов за последние 24 часа:</td>
        <td><?php
            echo $generalStats['recent_imports']; ?></td>
    </tr>
    <tr>
        <td>Среднее время импорта:</td>
        <td>
            <?php
            $seconds = $generalStats['avg_import_time'];
            $minutes = floor($seconds / 60);
            $seconds = $seconds % 60;
            echo "{$minutes} мин. {$seconds} сек.";
            ?>
        </td>
    </tr>
    <tr>
        <td>Всего шагов импорта выполнено:</td>
        <td><?php
            echo $generalStats['total_steps']; ?></td>
    </tr>

    <?php
    $tabControl->BeginNextTab(); ?>
    <tr>
        <td colspan="2">
            <table class="adm-list-table emulator-stats-table" width="100%">
                <thead>
                <tr class="adm-list-table-header">
                    <td class="adm-list-table-cell">
                        <div class="adm-list-table-cell-inner">Дата</div>
                    </td>
                    <td class="adm-list-table-cell">
                        <div class="adm-list-table-cell-inner">Файл</div>
                    </td>
                    <td class="adm-list-table-cell">
                        <div class="adm-list-table-cell-inner">Статус</div>
                    </td>
                    <td class="adm-list-table-cell">
                        <div class="adm-list-table-cell-inner">Сообщение</div>
                    </td>
                </tr>
                </thead>
                <tbody>
                <?php
                foreach ($latestImports as $import): ?>
                    <tr class="adm-list-table-row">
                        <td class="adm-list-table-cell"><?php
                            echo $import['DATE_CREATE']; ?></td>
                        <td class="adm-list-table-cell"><?php
                            echo htmlspecialchars($import['FILE_NAME']); ?></td>
                        <td class="adm-list-table-cell">
                            <span class="status-<?php
                            echo $import['STATUS']; ?>"><?php
                                echo $import['STATUS']; ?></span>
                        </td>
                        <td class="adm-list-table-cell"><?php
                            echo htmlspecialchars($import['MESSAGE']); ?></td>
                    </tr>
                <?php
                endforeach; ?>
                </tbody>
            </table>
        </td>
    </tr>

    <?php
    $tabControl->BeginNextTab(); ?>
    <tr>
        <td colspan="2">
            <table class="adm-list-table emulator-stats-table" width="100%">
                <thead>
                <tr class="adm-list-table-header">
                    <td class="adm-list-table-cell">
                        <div class="adm-list-table-cell-inner">Файл</div>
                    </td>
                    <td class="adm-list-table-cell">
                        <div class="adm-list-table-cell-inner">Этапов импорта</div>
                    </td>
                    <td class="adm-list-table-cell">
                        <div class="adm-list-table-cell-inner">Успешных импортов</div>
                    </td>
                    <td class="adm-list-table-cell">
                        <div class="adm-list-table-cell-inner">Ошибок</div>
                    </td>
                    <td class="adm-list-table-cell">
                        <div class="adm-list-table-cell-inner">Длительность</div>
                    </td>
                    <td class="adm-list-table-cell">
                        <div class="adm-list-table-cell-inner">Последняя операция</div>
                    </td>
                </tr>
                </thead>
                <tbody>
                <?php
                foreach ($fileStats as $file): ?>
                    <tr class="adm-list-table-row">
                        <td class="adm-list-table-cell"><?php
                            echo htmlspecialchars($file['FILE_NAME']); ?></td>
                        <td class="adm-list-table-cell"><?php
                            echo $file['steps_count']; ?></td>
                        <td class="adm-list-table-cell"><?php
                            echo $file['success']; ?></td>
                        <td class="adm-list-table-cell"><?php
                            echo $file['errors']; ?></td>
                        <td class="adm-list-table-cell"><?php
                            echo $file['duration']; ?></td>
                        <td class="adm-list-table-cell"><?php
                            echo $file['last_operation']; ?></td>
                    </tr>
                <?php
                endforeach; ?>
                </tbody>
            </table>
        </td>
    </tr>

    <?php
}

$tabControl->Buttons();
$tabControl->End();

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
?> 