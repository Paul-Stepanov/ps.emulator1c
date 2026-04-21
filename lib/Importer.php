<?php
namespace Onpeak\Emulator1c;

use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Main\Web\Uri;

/**
 * Класс для работы с импортом файлов из 1С
 */
class Importer
{
    /** @var string Директория для загрузки файлов */
    private string $uploadDir;
    
    /**
     * Конструктор класса
     */
    public function __construct()
    {
        $this->uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/1c_catalog/';
        
        // Создаем директорию, если она не существует
        if (!file_exists($this->uploadDir) && !is_dir($this->uploadDir) && !mkdir(
                $concurrentDirectory = $this->uploadDir,
                0777,
                true
            ) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        
        // Устанавливаем настройки для импорта
        $this->setImportOptions();
    }

    /**
     * Устанавливает опции для импорта из 1С
     *
     * @return void
     * @throws ArgumentOutOfRangeException
     */
    public function setImportOptions(): void
    {
        Option::set("catalog", "DEFAULT_SKIP_SOURCE_CHECK", "Y");
        Option::set("sale", "secure_1c_exchange", "N");
    }
    
    /**
     * Сбрасывает шаг импорта
     * 
     * @return bool
     */
    public function resetImportStep(): bool
    {
        $_SESSION["BX_CML2_IMPORT"]["NS"]["STEP"] = 0;
        return true;
    }
    
    /**
     * Загружает файл из POST-запроса
     *
     * @param array $file Массив информации о файле из $_FILES
     * @return array Массив с результатом загрузки ['success' => bool, 'message' => string, 'fileName' => string]
     */
    public function uploadFile(array $file): array
    {
      // TODO: добавить архивную обработку
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'Ошибка загрузки файла: ' . $this->getUploadErrorMessage($file['error']),
                'fileName' => ''
            ];
        }
        
        $fileName = $file['name'];
        $filePath = $this->uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Логируем начало импорта
            Logger::log($fileName, 'START', 'Начало импорта файла');
            
            // Сбрасываем шаг импорта
            $this->resetImportStep();
            
            return [
                'success' => true,
                'message' => 'Файл успешно загружен',
                'fileName' => $fileName
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Не удалось переместить загруженный файл',
            'fileName' => ''
        ];
    }
    
    /**
     * Возвращает сообщение об ошибке загрузки файла
     *
     * @param int $errorCode Код ошибки
     * @return string
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Размер файла превышает значение upload_max_filesize в php.ini',
            UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает значение MAX_FILE_SIZE в HTML-форме',
            UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично',
            UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
            UPLOAD_ERR_EXTENSION => 'PHP-расширение остановило загрузку файла',
        ];
        
        return isset($errors[$errorCode]) ? $errors[$errorCode] : 'Неизвестная ошибка';
    }
    
    /**
     * Формирует URL для импорта через 1C
     *
     * @param string $fileName Имя файла
     * @return string
     */
    public function getImportUrl(string $fileName): string
    {
        return '/bitrix/admin/1c_exchange.php?type=catalog&mode=import&filename=' . urlencode($fileName);
    }
    
    /**
     * Возвращает путь к загруженному файлу
     *
     * @param string $fileName Имя файла
     * @return string
     */
    public function getFilePath(string $fileName): string
    {
        return $this->uploadDir . $fileName;
    }
    
    /**
     * Проверяет существование файла
     *
     * @param string $fileName Имя файла
     * @return bool
     */
    public function fileExists(string $fileName): bool
    {
        return file_exists($this->getFilePath($fileName));
    }
} 