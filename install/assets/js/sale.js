/**
 * Эмуляция экспорта заказов в 1С (B1): checkauth → init → query×N → success, XML — в файл
 */
window.PsEmulator1CSale = {
    init: function(options) {
        this.options = options || {};
        this.log = document.getElementById(this.options.logId || "log");
        this.timerEl = document.getElementById(this.options.timerId || "timer");
        this.loadEl = document.getElementById(this.options.loadId || "load");
        this.startBtn = document.getElementById(this.options.startBtnId || "sale_start_btn");
        this.stopBtn = document.getElementById(this.options.stopBtnId || "sale_stop_btn");
        this.stateEl = document.getElementById(this.options.stateId || "emu_state");
        this.stepsEl = document.getElementById(this.options.stepsId || "emu_steps");

        this.processing = false;
        this.stopped = false;
        this.timerId = null;
        this.startedAt = 0;
        this.stepStartedAt = 0;
        this.totalOrders = 0;

        this.renderSteps(["checkauth", "init", "query", "success"]);

        const self = this;
        if (this.startBtn) {
            this.startBtn.addEventListener("click", function() {
                self.startOrdersExport();
            });
        }
        if (this.stopBtn) {
            this.stopBtn.addEventListener("click", function() {
                self.stopExport();
            });
        }
    },

    startOrdersExport: async function() {
        const self = this;
        if (self.processing) {
            return;
        }
        self.processing = true;
        self.stopped = false;
        self.totalOrders = 0;
        self.startedAt = Date.now();
        self.setButtons(true);
        self.setState("Выполняется", "run");
        self.hideEmpty();
        self.log.innerHTML = "";
        self.renderProgress();
        self.startTimer();
        self.setStepState("checkauth", "current");

        try {
            const auth = await self.request("checkauth");
            const sessid = self.matchOne(auth.text, /sessid=([^\s\r\n]+)/);
            if (!sessid) {
                throw new Error("checkauth не вернул sessid: " + self.firstLine(auth.text));
            }
            self.sessid = sessid;
            self.setStepState("checkauth", "done");
            self.setStepState("init", "current");
            self.logLine("success", "Сессия обмена установлена");

            const init = await self.request("init", {version: "2.09"});
            const zip = self.matchOne(init.text, /zip=(\w+)/);
            const fileLimit = self.matchOne(init.text, /file_limit=(\d+)/);
            self.logLine("zip=" + zip, "init: zip=" + zip + ", file_limit=" + fileLimit + ", версия протокола 2.09 (пошаговый query)");
            if (!self.startsWith(init.text, "zip=")) {
                throw new Error(self.firstLine(init.text));
            }
            self.setStepState("init", "done");
            self.setStepState("query", "current");

            const parts = await self.runQueryLoop();
            self.setStepState("query", "done");
            self.setStepState("success", "current");

            const success = await self.request("success");
            self.logLine(success.text.startsWith("success") ? "success" : "failure", "Подтверждение выгрузки (курсор last_export_time_committed зафиксирован)");
            if (!self.startsWith(success.text, "success")) {
                throw new Error("mode=success ответил error — нет cookie сессии обмена");
            }
            self.setStepState("success", "done");

            const xml = self.assembleXml(parts);
            self.offerDownload(xml);
            self.finish("success", "Экспорт завершён: заказов " + self.totalOrders + ", порций " + parts.length);
        } catch (e) {
            self.finish("error", e.message);
            alert("Экспорт прерван: " + e.message);
        }
    },

    runQueryLoop: async function() {
        const self = this;
        const parts = [];
        const decoder = new TextDecoder("windows-1251");
        let guard = 0;
        for (;;) {
            if (self.stopped) {
                throw new Error("Остановлено пользователем");
            }
            const resp = await self.requestRaw("query");
            if (resp.status !== 200) {
                throw new Error("HTTP " + resp.status + " на mode=query");
            }
            const text = decoder.decode(resp.buffer);
            if (self.startsWith(text, "failure")) {
                throw new Error("mode=query: " + self.firstLine(text));
            }
            const orders = (text.match(/<Документ/g) || []).length;
            parts.push(text);
            if (orders > 0) {
                self.totalOrders += orders;
                self.logLine("progress", "Порция " + parts.length + ": заказов " + orders);
                self.setProgress(Math.min(99, parts.length * 10));
            } else {
                self.logLine("progress", "Порция " + parts.length + " без заказов — выгрузка исчерпана (заказов всего " + self.totalOrders + ")");
                self.setProgress(99);
                return parts;
            }
            guard++;
            if (guard > 100000) {
                throw new Error("mode=query не завершается (защита от зацикливания)");
            }
        }
    },

    /**
     * Порции имеют одинаковый корень с закрытием; склейка = шапка первой + все <Документ> + корень
     */
    assembleXml: function(parts) {
        const rootRe = /<\?xml[^>]*\?>\s*<КоммерческаяИнформация([^>]*)>([\s\S]*)<\/КоммерческаяИнформация>/;
        if (!parts.length) {
            throw new Error("Нет ни одной порции query");
        }
        const first = parts[0].match(rootRe);
        if (!first) {
            throw new Error("Ответ query не является XML выгрузки CommerceML");
        }
        const header = parts[0].substr(0, parts[0].indexOf("<КоммерческаяИнформация"))
            .replace(/encoding="[^"]*"/i, 'encoding="UTF-8"') + "<КоммерческаяИнформация" + first[1] + ">";
        let body = first[2];
        for (let i = 1; i < parts.length; i++) {
            const m = parts[i].match(rootRe);
            if (!m) {
                // ответ, не являющийся порцией CommerceML (HTML ошибки/логина), молча допишет мусор в файл
                throw new Error("Порция " + (i + 1) + " не является XML выгрузки CommerceML");
            }
            body += m[2];
        }
        return header + body + "</КоммерческаяИнформация>";
    },

    offerDownload: function(xml) {
        const d = new Date();
        const pad = function(n) { return (n < 10 ? "0" : "") + n; };
        const name = "orders_" + d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()) + ".xml";
        const blob = new Blob([xml], {type: "application/xml"});
        const url = URL.createObjectURL(blob);
        const line = document.createElement("div");
        line.className = "emu-download";
        const link = document.createElement("a");
        link.href = url;
        link.download = name;
        link.target = "_blank";
        link.rel = "noopener";
        link.className = "emu-dl-chip";
        link.textContent = "Скачать " + name + " (" + blob.size + " байт) — то, что получила бы 1С";
        // синхронный revoke в клике отменяет загрузку в Chromium — откладываем
        link.addEventListener("click", function() {
            setTimeout(function() { URL.revokeObjectURL(url); }, 60000);
        }, {once: true});
        line.appendChild(link);
        this.log.appendChild(line);
        this.log.scrollTop = this.log.scrollHeight;
    },

    request: function(mode, params) {
        return this.requestRaw(mode, params).then(function(resp) {
            const decoder = new TextDecoder("windows-1251");
            return {text: decoder.decode(resp.buffer), status: resp.status};
        });
    },

    requestRaw: function(mode, params) {
        const self = this;
        const query = ["type=sale", "mode=" + encodeURIComponent(mode)];
        Object.keys(params || {}).forEach(function(k) {
            query.push(encodeURIComponent(k) + "=" + encodeURIComponent(params[k]));
        });
        query.push("sessid=" + encodeURIComponent(self.sessid || ""));
        return new Promise(function(resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open("GET", "/bitrix/admin/1c_exchange.php?" + query.join("&"), true);
            xhr.responseType = "arraybuffer";
            xhr.timeout = 0;
            xhr.onreadystatechange = function() {
                if (xhr.readyState !== 4) {
                    return;
                }
                resolve({buffer: xhr.response, status: xhr.status});
            };
            xhr.onerror = function() {
                reject(new Error("Сервер не ответил (mode=" + mode + ")"));
            };
            xhr.send(null);
        });
    },

    logLine: function(prefix, message) {
        const self = this;
        self.step = (self.step || 0) + 1;
        const duration = Math.round((Date.now() - self.stepStartedAt) / 1000);
        self.stepStartedAt = Date.now();
        const ok = prefix === "success" || prefix === "progress" || self.startsWith(prefix, "zip=");
        const label = "Шаг " + self.step + ": " + message + " (" + duration + " сек.)";
        const badge = prefix === "success" ? "success" : (ok ? "process" : "error");
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
                filename: "orders_export",
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
            self.timerEl.innerHTML = "Время: <b>" + Math.floor(elapsed / 60) + " мин. " + (elapsed % 60) + " сек.</b> · заказов: <b>" + self.totalOrders + "</b>";
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

    setProgress: function(percent) {
        if (this.progressBar) {
            this.progressBar.style.width = percent + "%";
            this.progressBar.classList.toggle("emu-progress-bar--done", percent >= 100);
            this.progressText.textContent = percent + "%";
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

    stopExport: function() {
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
