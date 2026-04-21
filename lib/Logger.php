<?php
namespace Ps\Emulator1c;

use Bitrix\Main\DB\Connection;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;

/**
 * Класс для логирования операций импорта
 */
class Logger
{
    /**
     * Добавляет запись в лог
     *
     * @param string $fileName Имя файла
     * @param string $status Статус операции (START, PROCESS, SUCCESS, ERROR, STOP)
     * @param string $message Сообщение
     * @return int|false ID записи или false в случае ошибки
     */
    public static function log($fileName, $status, $message): false|int
    {
        global $DB;
        
        return $DB->Add('ps_emulator1c_log', [
            'DATE_CREATE' => new DateTime(),
            'FILE_NAME' => $fileName,
            'STATUS' => $status,
            'MESSAGE' => $message
        ]);
    }
    
    /**
     * Получает последние записи лога
     *
     * @param int $limit Количество записей
     * @return array
     */
    public static function getLatestEntries(int $limit = 20): array
    {
        global $DB;
        
        $result = $DB->Query("
            SELECT *
            FROM ps_emulator1c_log
            ORDER BY DATE_CREATE DESC
            LIMIT " . $limit
        );
        
        $entries = [];
        while ($entry = $result->Fetch()) {
            $entries[] = $entry;
        }
        
        return $entries;
    }
    
    /**
     * Получает статистику по логам
     *
     * @return array
     */
    public static function getStats(): array
    {
        global $DB;
        
        $stats = [];
        
        // Общее количество успешных импортов
        $result = $DB->Query("
            SELECT COUNT(*) as total_imports
            FROM ps_emulator1c_log
            WHERE STATUS = 'SUCCESS'
        ");
        $stats['total_imports'] = $result->Fetch()['total_imports'];
        
        // Количество успешных импортов за последние 24 часа
        $result = $DB->Query("
            SELECT COUNT(*) as recent_imports
            FROM ps_emulator1c_log
            WHERE STATUS = 'SUCCESS'
            AND DATE_CREATE >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stats['recent_imports'] = $result->Fetch()['recent_imports'];
        
        return $stats;
    }
}