<?php
namespace Ps\Emulator1c;

use Bitrix\Main\DB\Connection;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;

/**
 * Класс для работы со статистикой импорта
 */
class Stats
{
    /**
     * Получает общую статистику импорта
     *
     * @return array
     */
    public static function getGeneralStats(): array
    {
        global $DB;
        
        $stats = [];
        
        // Общее количество успешных импортов
        $result = $DB->Query("
            SELECT COUNT(*) as total_imports
            FROM ps_emulator1c_log
            WHERE STATUS = 'SUCCESS'
        ");
        $stats['total_imports'] = $result->Fetch()['total_imports'] ?? 0;
        
        // Количество успешных импортов за последние 24 часа
        $result = $DB->Query("
            SELECT COUNT(*) as recent_imports
            FROM ps_emulator1c_log
            WHERE STATUS = 'SUCCESS'
            AND DATE_CREATE >= DATE_ADD(NOW(), INTERVAL -24 HOUR)
        ");
        $stats['recent_imports'] = $result->Fetch()['recent_imports'] ?? 0;
        
        // Общее количество шагов импорта
        $result = $DB->Query("
            SELECT COUNT(*) as total_steps
            FROM ps_emulator1c_log
            WHERE STATUS = 'PROCESS'
        ");
        $stats['total_steps'] = $result->Fetch()['total_steps'] ?? 0;
        
        // Среднее время импорта (безопасный подход)
        $stats['avg_import_time'] = 0;
        
        try {
            // Используем SQL для вычисления
            $result = $DB->Query("
                SELECT 
                    FILE_NAME,
                    MIN(DATE_CREATE) as start_time,
                    MAX(DATE_CREATE) as end_time,
                    TIMESTAMPDIFF(SECOND, MIN(DATE_CREATE), MAX(DATE_CREATE)) as import_time
                FROM ps_emulator1c_log
                WHERE STATUS IN ('START', 'SUCCESS')
                GROUP BY FILE_NAME
                HAVING start_time IS NOT NULL AND end_time IS NOT NULL
            ");
            
            $totalTime = 0;
            $count = 0;
            
            while ($row = $result->Fetch()) {
                if (!empty($row['import_time'])) {
                    $totalTime += (int)$row['import_time'];
                    $count++;
                }
            }
            
            if ($count > 0) {
                $stats['avg_import_time'] = round($totalTime / $count);
            }
        } catch (\Exception $e) {
            // В случае ошибки просто возвращаем 0
            $stats['avg_import_time'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * Получает последние записи лога импорта
     *
     * @param int $limit Количество записей
     * @return array
     */
    public static function getLatestImports(int $limit = 20): array
    {
        global $DB;

        $result = $DB->Query("
        SELECT * 
        FROM ps_emulator1c_log
        ORDER BY ID DESC 
        LIMIT " . $limit
        );

        $entries = [];
        while ($entry = $result->Fetch()) {
            $entries[] = $entry;
        }

        return $entries;
    }

    
    /**
     * Анализирует статистику выполнения импортов
     *
     * @param int $days Количество дней для анализа
     * @return array
     */
    public static function analyzeImports(int $days = 7): array
    {
        global $DB;
        
        $result = $DB->Query("
            SELECT 
                DATE(DATE_CREATE) as import_date,
                COUNT(*) as total,
                SUM(CASE WHEN STATUS = 'SUCCESS' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN STATUS = 'ERROR' THEN 1 ELSE 0 END) as errors
            FROM ps_emulator1c_log
            WHERE DATE_CREATE >= DATE_SUB(NOW(), INTERVAL " . $days . " DAY)
            GROUP BY DATE(DATE_CREATE)
            ORDER BY import_date DESC
        ");
        
        $stats = [];
        while ($row = $result->Fetch()) {
            $stats[] = $row;
        }
        
        return $stats;
    }
    
    /**
     * Получает статистику по файлам
     *
     * @param int $limit Количество файлов
     * @return array
     */
    public static function getFileStats(int $limit = 20): array
    {
        global $DB;
        
        // Используем чистый SQL для обработки времени
        $result = $DB->Query("
            SELECT 
                FILE_NAME,
                COUNT(*) as total_operations,
                SUM(CASE WHEN STATUS = 'SUCCESS' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN STATUS = 'ERROR' THEN 1 ELSE 0 END) as errors,
                MAX(DATE_CREATE) as last_operation,
                SUM(CASE WHEN STATUS = 'PROCESS' THEN 1 ELSE 0 END) as steps_count,
                MIN(CASE WHEN STATUS = 'START' THEN DATE_CREATE ELSE NULL END) as start_time,
                MAX(CASE WHEN STATUS = 'SUCCESS' THEN DATE_CREATE ELSE NULL END) as end_time,
                TIMESTAMPDIFF(SECOND, 
                    MIN(CASE WHEN STATUS = 'START' THEN DATE_CREATE ELSE NULL END), 
                    MAX(CASE WHEN STATUS = 'SUCCESS' THEN DATE_CREATE ELSE NULL END)
                ) as duration_seconds
            FROM ps_emulator1c_log
            GROUP BY FILE_NAME
            ORDER BY last_operation DESC
            LIMIT " . $limit
        );
        
        $stats = [];
        while ($row = $result->Fetch()) {
            // Безопасное преобразование длительности
            if (!empty($row['duration_seconds']) && $row['duration_seconds'] > 0) {
                $minutes = floor($row['duration_seconds'] / 60);
                $seconds = $row['duration_seconds'] % 60;
                $row['duration'] = "{$minutes} мин. {$seconds} сек.";
            } else {
                $row['duration'] = 'Не завершен';
            }
            
            $stats[] = $row;
        }
        
        return $stats;
    }
}