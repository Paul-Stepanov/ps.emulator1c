/**
 * Эмуляция полной сессии обмена каталогом с 1С поверх /bitrix/admin/1c_exchange.php
 */
window.PsEmulator1C = {
    init: function(options) {
        this.options = options || {};
        this.log = document.getElementById(this.options.logId || "log");
        this.timerEl = document.getElementById(this.options.timerId || "timer");
        this.loadEl = document.getElementById(this.options.loadId || "load");
        this.fileInput = document.getElementById(this.options.fileInputId || "import_file_input");
        this.startBtn = document.getElementById(this.options.startBtnId || "import_start_btn");
        this.stopBtn = document.getElementById(this.options.stopBtnId || "import_stop_btn");
        this.stateEl = document.getElementById(this.options.stateId || "emu_state");
        this.titleEl = document.getElementById(this.options.titleId || "emu_title");
        this.stepsEl = document.getElementById(this.options.stepsId || "emu_steps");

        this.fileName = "";
        this.step = 0;
        this.processing = false;
        this.stopped = false;
        this.timerId = null;
        this.startedAt = 0;
        this.stepStartedAt = 0;
        this.totalElements = 0;
        this.processedElements = 0;

        this.renderSteps(["checkauth", "init", "file", "import", "complete"]);

        const self = this;
        if (this.startBtn) {
            this.startBtn.addEventListener("click", function() {
                const file = self.fileInput && self.fileInput.files && self.fileInput.files[0];
                if (!file) {
                    alert("Выберите файл импорта (import0_1.xml или offers0_1.xml)");
                    return;
                }
                self.startCatalogImport(file);
            });
        }
        if (this.stopBtn) {
            this.stopBtn.addEventListener("click", function() {
                self.stopImport();
            });
        }
    },

    /**
     * Запуск полной сессии: checkauth → init → file×N → import×N → complete.
     * mode=deactivate не отправляем: живая 1С его не шлёт, а ядро по нему
     * деактивирует всё, что не участвовало в сессии, игнорируя настройки обмена.
     * @param {File} file Файл из input[type=file]
     */
    startCatalogImport: async function(file) {
        const self = this;
        if (self.processing) {
            return;
        }
        self.processing = true;
        self.stopped = false;
        self.fileName = file.name;
        self.step = 0;
        self.totalElements = 0;
        self.processedElements = 0;
        self.startedAt = Date.now();
        self.setButtons(true);
        self.setState("Выполняется", "run");
        if (self.titleEl) {
            self.titleEl.textContent = "Импорт " + self.fileName;
        }
        self.hideEmpty();
        self.log.innerHTML = "";
        self.renderProgress();
        self.startTimer();
        self.setStepState("checkauth", "current");

        try {
            const auth = await self.request("checkauth");
            const sessid = self.matchOne(auth.text, /sessid=([^\s\r\n]+)/);
            const timestamp = self.matchOne(auth.text, /timestamp=(\d+)/);
            if (!sessid || !timestamp) {
                throw new Error("checkauth не вернул sessid/timestamp: " + self.firstLine(auth.text));
            }
            self.sessid = sessid;
            self.setStepState("checkauth", "done");
            self.setStepState("init", "current");
            self.logLine(auth.text, "Сессия обмена установлена (timestamp=" + timestamp + ")");

            const init = await self.request("init");
            const zip = self.matchOne(init.text, /zip=(\w+)/);
            const fileLimit = parseInt(self.matchOne(init.text, /file_limit=(\d+)/) || "0", 10);
            if (!fileLimit) {
                throw new Error("init не вернул file_limit: " + self.firstLine(init.text));
            }
            self.setStepState("init", "done");
            self.setStepState("file", "current");
            self.logLine(init.text, "init: zip=" + zip + ", file_limit=" + fileLimit + " байт");

            if (zip === "yes" && !/\.zip$/i.test(self.fileName)) {
                // живая 1С на zip=yes архивирует выгрузку; ядро .xml распаковать не сможет (UnZip)
                throw new Error("Настройки обмена требуют zip (zip=yes). Заархивируйте файл в .zip или выключите USE_ZIP в настройках обмена (/bitrix/admin/1c_admin.php)");
            }

            await self.uploadFileChunks(file, fileLimit);
            self.setStepState("file", "done");
            self.setStepState("import", "current");
            await self.runImportLoop(file.name);
            self.setStepState("import", "done");
            self.setStepState("complete", "current");

            const complete = await self.request("complete");
            self.logLine(complete.text, "Завершение сессии (OnCompleteCatalogImport1C)");
            if (!self.startsWith(complete.text, "success")) {
                throw new Error(self.firstLine(complete.text));
            }
            self.setStepState("complete", "done");

            self.finish("success", "Импорт завершён");
        } catch (e) {
            self.finish("error", e.message);
            alert("Обмен прерван: " + e.message);
        }
    },

    uploadFileChunks: async function(file, limit) {
        const self = this;
        const total = file.size;
        const chunks = Math.max(1, Math.ceil(total / limit));
        let chunkLine = null;
        for (let offset = 0, i = 1; offset < total || (total === 0 && i === 1); offset += limit, i++) {
            if (self.stopped) {
                throw new Error("Остановлено пользователем");
            }
            const part = file.slice(offset, offset + limit);
            const resp = await self.request("file", {filename: file.name}, part);
            if (!self.startsWith(resp.text, "success")) {
                throw new Error("mode=file (часть " + i + "/" + chunks + "): " + self.errorText(resp.text));
            }
            const percent = total > 0 ? Math.min(100, Math.round(Math.min(offset + limit, total) / total * 100)) : 100;
            const label = "Передача файла: часть " + i + "/" + chunks + " (" + percent + "%)";
            self.setProgress(Math.round(percent * 0.1), "Передача файла");
            if (chunkLine === null) {
                chunkLine = self.logLine(resp.text, label);
            } else {
                chunkLine.querySelector(".emu-msg").textContent = label;
            }
        }
        if (chunkLine !== null) {
            chunkLine.querySelector(".emu-msg").textContent = "Файл передан целиком (" + total + " байт, частей: " + chunks + ")";
        }
        self.setProgress(10, "Чтение XML ядром");
        self.logStatus("PROCESS", "Файл передан целиком (" + total + " байт, частей: " + chunks + ")");
    },

    runImportLoop: async function(fileName) {
        const self = this;
        const elementsRe = /Обработано\s+(\d+)\s+из\s+(\d+)\s+элементов?/i;
        const percentRe = /(\d+(?:[.,]\d+)?)\s*%/;
        let guard = 0;
        for (;;) {
            if (self.stopped) {
                throw new Error("Остановлено пользователем");
            }
            const resp = await self.request("import", {filename: fileName});
            if (self.startsWith(resp.text, "success")) {
                self.logLine(resp.text, "Импорт файла " + fileName + " завершён");
                self.setProgress(99);
                return;
            }
            if (!self.startsWith(resp.text, "progress")) {
                throw new Error("mode=import: " + self.errorText(resp.text));
            }
            const message = resp.text.replace(/^progress\s*\n?/, "");
            self.logLine(resp.text, message);
            const m = message.match(elementsRe);
            if (m) {
                self.processedElements = parseInt(m[1], 10);
                self.totalElements = parseInt(m[2], 10);
                if (self.totalElements > 0) {
                    const p = Math.floor(self.processedElements / self.totalElements * 39);
                    self.setProgress(Math.min(99, 60 + p), "Элементы: " + self.processedElements + " из " + self.totalElements);
                }
            } else {
                const p = message.match(percentRe);
                if (p) {
                    const read = Math.min(100, parseFloat(p[1].replace(",", ".")));
                    self.setProgress(Math.min(60, 10 + Math.round(read * 0.5)), "Чтение XML ядром");
                }
            }
            guard++;
            if (guard > 100000) {
                throw new Error("mode=import не завершается (защита от зацикливания)");
            }
        }
    },

    request: function(mode, params, body) {
        const self = this;
        const query = ["type=catalog", "mode=" + encodeURIComponent(mode)];
        Object.keys(params || {}).forEach(function(k) {
            query.push(encodeURIComponent(k) + "=" + encodeURIComponent(params[k]));
        });
        query.push("sessid=" + encodeURIComponent(self.sessid || ""));
        return new Promise(function(resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open(body ? "POST" : "GET", "/bitrix/admin/1c_exchange.php?" + query.join("&"), true);
            xhr.timeout = 0;
            xhr.onreadystatechange = function() {
                if (xhr.readyState !== 4) {
                    return;
                }
                if (xhr.status === 200) {
                    resolve({text: xhr.responseText});
                } else {
                    reject(new Error("HTTP " + xhr.status + " на mode=" + mode));
                }
            };
            xhr.onerror = function() {
                reject(new Error("Сервер не ответил (mode=" + mode + ")"));
            };
            xhr.send(body || null);
        });
    },

    logLine: function(responseText, message) {
        const self = this;
        self.step++;
        const duration = Math.round((Date.now() - self.stepStartedAt) / 1000);
        self.stepStartedAt = Date.now();
        const ok = self.startsWith(responseText, "success")
            || self.startsWith(responseText, "progress")
            || self.startsWith(responseText, "zip=");
        const label = "Шаг " + self.step + ": " + message + " (" + duration + " сек.)";
        const badge = self.startsWith(responseText, "success") ? "success" : (ok ? "process" : "error");
        const line = document.createElement("div");
        line.className = "emu-line" + (ok ? "" : " emu-line--error");
        line.innerHTML =
            '<span class="emu-time">' + self.escapeHtml(self.now()) + "</span>" +
            '<span class="emu-badge emu-badge--' + badge + '">' + badge.toUpperCase() + "</span>" +
            '<span class="emu-msg">' + self.escapeHtml(message) + "</span>" +
            '<span class="emu-dur">' + duration + " сек.</span>";
        self.log.appendChild(line);
        self.log.scrollTop = self.log.scrollHeight;
        self.logStatus(ok ? "PROCESS" : "WARNING", label);
        return line;
    },

    logStatus: function(status, message) {
        BX.ajax.post(
            window.location.href,
            {
                ajax_action: "log_status",
                sessid: BX.bitrix_sessid(),
                filename: this.fileName,
                status: status,
                message: message
            },
            function() {}
        );
    },

    startTimer: function() {
        const self = this;
        self.stepStartedAt = Date.now();
        if (self.timerId) {
            clearInterval(self.timerId);
        }
        self.timerId = setInterval(function() {
            const elapsed = Math.floor((Date.now() - self.startedAt) / 1000);
            let eta = "";
            if (self.totalElements > 0 && self.processedElements > 0) {
                const perElement = elapsed / self.processedElements;
                const left = Math.max(0, Math.round((self.totalElements - self.processedElements) * perElement));
                eta = " · осталось ≈ <b>" + Math.floor(left / 60) + " мин. " + (left % 60) + " сек.</b>";
            }
            self.timerEl.innerHTML = "Время: <b>" + Math.floor(elapsed / 60) + " мин. " + (elapsed % 60) + " сек.</b>" + eta;
        }, 1000);
    },

    renderProgress: function() {
        this.loadEl.style.display = "block";
        this.loadEl.innerHTML =
            '<div class="emu-progress-track">' +
            '<div class="emu-progress-bar"></div>' +
            "</div>" +
            '<div class="emu-progress-meta"><span class="emu-progress-text">0%</span></div>';
        this.progressBar = this.loadEl.querySelector(".emu-progress-bar");
        this.progressText = this.loadEl.querySelector(".emu-progress-text");
    },

    setProgress: function(percent, label) {
        if (this.progressBar) {
            this.progressBar.style.width = percent + "%";
            this.progressBar.classList.toggle("emu-progress-bar--done", percent >= 100);
            this.progressText.textContent = (label ? label + " · " : "") + percent + "%";
        }
        const pctEl = this.stepsEl && this.stepsEl.querySelector(".emu-step--current .emu-step-pct");
        if (pctEl) {
            pctEl.textContent = " · " + percent + "%";
        }
    },

    setState: function(text, cls) {
        if (!this.stateEl) {
            return;
        }
        this.stateEl.textContent = text;
        this.stateEl.className = "emu-state emu-state--" + cls;
    },

    hideEmpty: function() {
        const el = this.log.parentNode.querySelector(".emu-empty");
        if (el) {
            el.style.display = "none";
        }
    },

    renderSteps: function(names) {
        if (!this.stepsEl) {
            return;
        }
        let html = "";
        names.forEach(function(name, i) {
            if (i > 0) {
                html += '<span class="emu-arrow">─</span>';
            }
            html += '<span class="emu-step" data-step="' + name + '"><span class="emu-step-dot">○</span>' + name + '<span class="emu-step-pct"></span></span>';
        });
        this.stepsEl.innerHTML = html;
    },

    setStepState: function(name, cls) {
        if (!this.stepsEl) {
            return;
        }
        const el = this.stepsEl.querySelector('[data-step="' + name + '"]');
        if (!el) {
            return;
        }
        el.className = "emu-step" + (cls ? " emu-step--" + cls : "");
        el.querySelector(".emu-step-dot").textContent = cls === "done" ? "✓" : (cls === "error" ? "!" : (cls === "current" ? "●" : "○"));
        if (cls !== "current") {
            el.querySelector(".emu-step-pct").textContent = "";
        }
    },

    markCurrentStepError: function() {
        const cur = this.stepsEl && this.stepsEl.querySelector(".emu-step--current");
        if (cur) {
            this.setStepState(cur.getAttribute("data-step"), "error");
        }
    },

    setButtons: function(running) {
        if (this.startBtn) {
            this.startBtn.disabled = running;
        }
        if (this.stopBtn) {
            this.stopBtn.disabled = !running;
        }
    },

    stopImport: function() {
        if (this.processing && !this.stopped) {
            this.stopped = true;
            this.setState("Остановка…", "stop");
            this.logStatus("STOP", "Остановлено пользователем (дозавершение текущего запроса)");
        }
    },

    now: function() {
        return new Date().toLocaleTimeString("ru-RU", {hour12: false});
    },

    finish: function(status, message) {
        const self = this;
        if (self.timerId) {
            clearInterval(self.timerId);
            self.timerId = null;
        }
        self.processing = false;
        self.setButtons(false);
        const elapsed = Math.floor((Date.now() - self.startedAt) / 1000);
        self.timerEl.innerHTML = "Время: <b>" + Math.floor(elapsed / 60) + " мин. " + (elapsed % 60) + " сек.</b>";
        if (status === "success") {
            self.setProgress(100);
            self.setState("Завершено", "done");
            self.logStatus("SUCCESS", message + " за " + elapsed + " сек.");
        } else {
            self.loadEl.style.display = "none";
            self.markCurrentStepError();
            self.setState("Ошибка", "error");
            const line = document.createElement("div");
            line.className = "emu-line emu-line--error";
            line.innerHTML =
                '<span class="emu-time">' + self.escapeHtml(self.now()) + "</span>" +
                '<span class="emu-badge emu-badge--error">ERROR</span>' +
                '<span class="emu-msg">' + self.escapeHtml(message) + "</span>";
            self.log.appendChild(line);
            self.log.scrollTop = self.log.scrollHeight;
            self.logStatus("ERROR", message);
        }
    },

    startsWith: function(text, prefix) {
        return text.substr(0, prefix.length) === prefix;
    },

    errorText: function(text) {
        return String(text).replace(/\s+/g, " ").trim().slice(0, 300);
    },

    firstLine: function(text) {
        return String(text).split("\n")[0].replace(/\r$/, "");
    },

    matchOne: function(text, re) {
        const m = String(text).match(re);
        return m ? m[1] : null;
    },

    escapeHtml: function(text) {
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }
};
