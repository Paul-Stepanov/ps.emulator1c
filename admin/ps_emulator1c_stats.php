<?php

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

use Ps\Emulator1c\Stats;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm("Доступ запрещен");
}

if (!CModule::IncludeModule("ps.emulator1c")) {
    ShowError("Модуль не установлен");
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
    die();
}

$APPLICATION->SetTitle("Статистика импорта 1С");

$APPLICATION->SetAdditionalCSS('/bitrix/css/ps.emulator1c/style.css?v=1.2.5');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

try {
    $generalStats = Stats::getGeneralStats();
    $latestImports = Stats::getLatestImports(15);
    $fileStats = Stats::getFileStats(10);
    $hasError = false;
} catch (\Exception $e) {
    ShowError("Ошибка при получении статистики: " . $e->getMessage());
    $hasError = true;
}

$avgMinutes = (int)floor($generalStats['avg_import_time'] / 60);
$avgSeconds = (int)($generalStats['avg_import_time'] % 60);

function emuStatusBadge(string $status): string
{
    $class = preg_replace('/[^a-z]/', '', strtolower($status));

    return '<span class="emu-badge-l emu-badge-l--' . $class . '">' . htmlspecialchars($status) . '</span>';
}
?>

<?php if (!$hasError): ?>
<div class="emu-page">
    <div class="emu-kpis">
        <div class="emu-kpi"><b><?php echo (int)$generalStats['total_imports']; ?></b><span>успешных импортов</span></div>
        <div class="emu-kpi"><b><?php echo (int)$generalStats['recent_imports']; ?></b><span>за последние 24 часа</span></div>
        <div class="emu-kpi"><b><?php echo $avgMinutes; ?> мин. <?php echo $avgSeconds; ?> сек.</b><span>среднее время импорта</span></div>
        <div class="emu-kpi"><b><?php echo (int)$generalStats['total_steps']; ?></b><span>шагов импорта выполнено</span></div>
    </div>

    <div class="emu-section-h">Последние импорты</div>
    <table class="emu-table">
        <thead>
        <tr>
            <th>Дата</th>
            <th>Файл</th>
            <th>Статус</th>
            <th>Сообщение</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($latestImports as $import): ?>
            <tr>
                <td><?php echo htmlspecialchars($import['DATE_CREATE']); ?></td>
                <td><?php echo htmlspecialchars($import['FILE_NAME']); ?></td>
                <td><?php echo emuStatusBadge($import['STATUS']); ?></td>
                <td><?php echo htmlspecialchars($import['MESSAGE']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="emu-section-h">Статистика по файлам</div>
    <table class="emu-table">
        <thead>
        <tr>
            <th>Файл</th>
            <th>Этапов импорта</th>
            <th>Успешных импортов</th>
            <th>Ошибок</th>
            <th>Длительность</th>
            <th>Последняя операция</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($fileStats as $file): ?>
            <tr>
                <td><?php echo htmlspecialchars($file['FILE_NAME']); ?></td>
                <td><?php echo (int)$file['steps_count']; ?></td>
                <td><?php echo (int)$file['success']; ?></td>
                <td><?php echo (int)$file['errors']; ?></td>
                <td><?php echo htmlspecialchars($file['duration']); ?></td>
                <td><?php echo htmlspecialchars($file['last_operation']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
