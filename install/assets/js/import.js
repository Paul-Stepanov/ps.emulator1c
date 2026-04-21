/**
 * Модуль для работы с импортом файлов из 1С
 */
const OnpeakEmulator1C = {
    /**
     * Инициализация модуля
     * 
     * @param {Object} options Параметры инициализации
     */
    init: function(options) {
        this.options = options || {};
        this.log = document.getElementById(this.options.logId || "log");
        this.timer = document.getElementById(this.options.timerId || "timer");
        this.load = document.getElementById(this.options.loadId || "load");
        
        // Переменные таймера
        this.mSecond = 0;
        this.seconds = 0;
        this.minute = 0;
        
        // Переменные импорта
        this.step = 1;
        this.output = "";
        this.processing = true;
        this.status = "continue";
        
        // Переменные для оценки времени
        this.totalSteps = 12; // Примерное количество шагов импорта
        this.startTime = 0;
        this.estimatedTotalTime = 0;
        this.estimatedEndTime = 0;
        this.avgTimePerStep = 0;

        // Переменные для отслеживания элементов
        this.totalElements = 0;        // Общее количество элементов
        this.processedElements = 0;    // Обработанное количество элементов
        this.preparationSteps = 8;     // Количество подготовительных шагов
        this.elementsProcessingStarted = false; // Флаг начала обработки элементов
    },
    
    /**
     * Запуск импорта
     * 
     * @param {string} fileName Имя файла для импорта
     */
    startImport: function(fileName) {
        const self = this;
        
        self.importFileName = fileName;

        // Установка прогресс-бара
        self.load.innerHTML = `
            <div class="emulator-progress">
                <div class="emulator-progress-bar" style="width: 0"></div>
                <div class="emulator-progress-text">0%</div>
            </div>
        `;
        // Сохраняем ссылки на элементы прогресс-бара
        self.progressBar = self.load.querySelector('.emulator-progress-bar');
        self.progressText = self.load.querySelector('.emulator-progress-text');
        
        self.step = 1;
        self.output = "";
        self.mSecond = 0;
        self.seconds = 0;
        self.minute = 0;
        self.processing = true;
        self.status = "continue";

        // Сброс переменных элементов
        self.totalElements = 0;
        self.processedElements = 0;
        self.elementsProcessingStarted = false;

        // Инициализация времени начала импорта
        self.startTime = new Date();
        
        self.startTimer();
        self.timer.innerHTML = "";
        self.log.innerHTML = `<b>Импорт ${self.importFileName}</b><hr>`;
        self.logStatus('PROCESS', `Запуск импорта шаг ${self.step}`);
        self.query1C(self.importFileName);
    },
    
    /**
     * Запрос к 1C_exchange.php
     * 
     * @param {string} fileName Имя файла для импорта
     */
    query1C: function(fileName) {
        const self = this;
        const import1C = self.createHttpRequest();
        const url = `/bitrix/admin/1c_exchange.php?type=catalog&mode=import&filename=${fileName}`;

        self.load.style.display = "block";
        import1C.open("GET", url, true);
        import1C.onreadystatechange = function() {
            self.output = self.log.innerHTML;

            if (import1C.readyState == 4 && import1C.status == 0) {
                const errorText = `<em class="emulator-error">Ошибка в процессе выгрузки</em>
                    <div class="emulator-error-details">Сервер упал и не вернул заголовков.</div>`;
                self.log.innerHTML = `${self.output}Шаг ${self.step}: ${errorText}`;
                self.load.style.display = "none";
                self.status = "continue";
                self.logStatus('ERROR', `Ошибка на шаге ${self.step}: Сервер упал и не вернул заголовков`);
                alert("Импорт прерван из-за ошибки сервера!");
            }

            if (import1C.readyState == 4 && import1C.status == 200) {
                if ((import1C.responseText.substr(0,8) != "progress") && (import1C.responseText.substr(0,7) != "success")) {
                    const errorText = `<em class="emulator-error">Ошибка в процессе выгрузки</em>
                        <div class="emulator-error-details">${import1C.responseText}</div>`;
                    self.log.innerHTML = `${self.output}Шаг ${self.step}: ${errorText}`;
                    self.status = "error";
                    self.logStatus('ERROR', `Ошибка на шаге ${self.step}: ${import1C.responseText}`);
                } else {
                    let n = import1C.responseText.lastIndexOf('s') + 1;
                    const l = import1C.responseText.length;
                    let mess = import1C.responseText.substr(n, l);

                    self.log.innerHTML = `${self.output}Шаг ${self.step}: ${mess} (${self.seconds} сек.)<br>`;

                    // Автоскролл к текущему шагу
                    self.log.scrollTop = self.log.scrollHeight;

                    self.logStatus('PROCESS', `Шаг ${self.step}: ${mess}`);
                    self.seconds = 0;
                    self.load.style.display = "none";

                    // Парсим информацию об элементах из сообщения
                    const elementsInfo = self.parseElementsInfo(mess);
                    if (elementsInfo) {
                        self.totalElements = elementsInfo.total;
                        self.processedElements = elementsInfo.processed;
                        self.elementsProcessingStarted = true;
                    }

                    // Обновление прогресс-бара
                    if (import1C.responseText.substr(0,8) == "progress") {
                        let progress;
                        if (self.totalElements > 0 && self.elementsProcessingStarted) {
                            // Используем реальные данные об элементах
                            progress = Math.min(100, Math.floor((self.processedElements / self.totalElements) * 100));
                        } else {
                            // Fallback для подготовительных шагов (примерно 15% от общего времени)
                            const prepProgress = Math.min(15, Math.floor((self.step / self.preparationSteps) * 15));
                            progress = prepProgress;
                        }
                        self.progressBar.style.width = `${progress}%`;
                        self.progressText.innerText = `${progress}%`;
                    } else if (import1C.responseText.substr(0,7) == "success") {
                        self.progressBar.style.width = '100%';
                        self.progressText.innerText = '100%';
                    }

                    self.step++;
                }

                if ((import1C.responseText.substr(0,7) == "success") || (self.status == "error") || (self.status == "stop")) {
                    self.load.style.display = "none";
                    self.status = "continue";
                    self.processing = false;

                    const totalTime = `<b>${self.minute} мин. ${self.mSecond} сек.</b>`;
                    self.timer.innerHTML = `<hr>Время выгрузки: ${totalTime}`;

                    if (import1C.responseText.substr(0,7) == "success") {
                        self.logStatus('SUCCESS', 'Импорт успешно завершен');
                    }
                } else {
                    self.query1C(fileName);
                }
            }
        };
        import1C.send(null);
    },
    
    /**
     * Запуск таймера
     */
    startTimer: function() {
        const self = this;
        
        if (self.mSecond == 60) {
            self.mSecond = 0;
            self.minute += 1;
        }
        
        if (self.processing == true) {
            self.seconds += 1;
            self.mSecond += 1;
            
            // Обновление оставшегося времени каждые 5 секунд
            if (self.seconds % 5 === 0) {
                self.updateTimeEstimate();
            }
            
            const elapsed = `<b>${self.minute} мин. ${self.mSecond} сек.</b>`;
            let estimateHtml = '';
            
            if (self.step > 1 && self.estimatedEndTime) {
                const now = new Date();
                const remaining = Math.max(0, Math.floor((self.estimatedEndTime - now) / 1000));
                const remainingMin = Math.floor(remaining / 60);
                const remainingSec = remaining % 60;
                
                estimateHtml = ` | Осталось примерно: <b>${remainingMin} мин. ${remainingSec} сек.</b>`;
            }
            
            self.timer.innerHTML = `Время выполнения: ${elapsed}${estimateHtml}`;
            setTimeout(function() { self.startTimer(); }, 1000);
        }
    },
    
    /**
     * Обновление оценки времени завершения
     */
    updateTimeEstimate: function() {
        const self = this;

        if (self.step <= 1) return;

        const now = new Date();
        const elapsedTime = (now - self.startTime) / 1000; // в секундах

        if (self.totalElements > 0 && self.elementsProcessingStarted && self.processedElements > 0) {
            // Расчет на основе скорости обработки элементов
            const elementsPerSecond = self.processedElements / elapsedTime;
            const remainingElements = self.totalElements - self.processedElements;
            const estimatedRemainingTime = remainingElements / elementsPerSecond;
            self.estimatedEndTime = new Date(now.getTime() + (estimatedRemainingTime * 1000));
        } else if (self.step > 1) {
            // Fallback для подготовительных шагов
            const avgTimePerStep = elapsedTime / (self.step - 1);
            const estimatedRemainingTime = (self.preparationSteps - self.step + 1) * avgTimePerStep;
            self.estimatedEndTime = new Date(now.getTime() + (estimatedRemainingTime * 1000));
        }
    },
    
    /**
     * Парсит сообщение для извлечения информации об элементах
     *
     * @param {string} message Сообщение от сервера
     * @returns {Object|null} Объект {processed, total} или null
     */
    parseElementsInfo: function(message) {
        // Парсит сообщения вида "Обработано 20 из 1215 элементов"
        const match = message.match(/Обработано\s+(\d+)\s+из\s+(\d+)\s+элементов?/i);
        if (match) {
            return {
                processed: parseInt(match[1], 10),
                total: parseInt(match[2], 10)
            };
        }
        return null;
    },

    /**
     * Сброс шага импорта
     */
    resetStep: function() {
        const self = this;
        
        BX.ajax.post(
            window.location.href,
            {
                ajax_action: 'reset_step',
                sessid: BX.bitrix_sessid()
            },
            function(result) {
                alert("Шаг импорта обнулён!");
            }
        );
    },
    
    /**
     * Остановка импорта
     */
    stopImport: function() {
        const self = this;
        
        self.status = "stop";
        self.logStatus('STOP', 'Импорт остановлен пользователем');
    },
    
    /**
     * Логирование статуса импорта
     * 
     * @param {string} status Статус операции
     * @param {string} message Сообщение
     */
    logStatus: function(status, message) {
        const self = this;
        
        BX.ajax.post(
            window.location.href,
            {
                ajax_action: 'log_status',
                sessid: BX.bitrix_sessid(),
                filename: self.importFileName,
                status: status,
                message: message
            },
            function() {}
        );
    },
    
    /**
     * Создание HTTP-запроса
     * 
     * @returns {XMLHttpRequest}
     */
    createHttpRequest: function() {
        let httpRequest;
        
        if (window.XMLHttpRequest) {
            httpRequest = new XMLHttpRequest();
        } else if (window.ActiveXObject) {
            try {
                httpRequest = new ActiveXObject('Msxml2.XMLHTTP');
            } catch (e) {}
            try {
                httpRequest = new ActiveXObject('Microsoft.XMLHTTP');
            } catch (e) {}
        }
        
        return httpRequest;
    }
};