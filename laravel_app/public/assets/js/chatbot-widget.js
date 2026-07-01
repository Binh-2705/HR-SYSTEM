(function () {
    function init() {
        var panel = document.getElementById('ai-chat-panel');
        var fab = document.getElementById('ai-chat-fab');
        var messages = document.getElementById('ai-chat-messages');
        var input = document.getElementById('ai-chat-input');
        var sendBtn = document.getElementById('ai-chat-send');
        var suggWrap = document.getElementById('ai-suggestions');
        var draftZone = document.getElementById('ai-draft-zone');
        var statusText = document.getElementById('ai-chat-status-text');
        var badge = document.getElementById('ai-chat-badge');
        var welcome = document.getElementById('ai-welcome-state');

        if (!panel || !fab || !messages || !input || !sendBtn || !suggWrap || !draftZone || !statusText || !badge || !welcome) {
            return;
        }

        var isOpen = false;
        var isBusy = false;
        var unread = 0;
        var askUrl = panel.getAttribute('data-ask-url') || '';
        var confirmUrl = panel.getAttribute('data-confirm-url') || '';
        var chartsUrl = panel.getAttribute('data-charts-url') || '';
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrf = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';

        var chartPanel = document.getElementById('ai-chart-panel');
        var chartSelect = document.getElementById('ai-chart-select');
        var chartLoading = document.getElementById('ai-chart-loading');
        var chartCanvas = document.getElementById('ai-chart-canvas');
        var chartToggleBtn = document.getElementById('ai-chart-toggle');
        var chartInstance = null;
        var chartDataCache = null;
        var chartOpen = false;
        var chartJsPromise = null;

        var PALETTE = ['#4f46e5', '#7c3aed', '#2563eb', '#0891b2', '#059669', '#d97706', '#dc2626', '#db2777'];
        var DEFAULT_SUGGESTIONS = [
            'Tổng số nhân viên là bao nhiêu?',
            'Nghỉ phép đang chờ duyệt?',
            'Hợp đồng sắp hết hạn',
            'Tóm tắt lương tháng này',
            'Chấm công tháng này'
        ];

        function escHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function scrollBottom() {
            messages.scrollTop = messages.scrollHeight;
        }

        function renderSuggestions(list) {
            suggWrap.innerHTML = '';
            if (!list || !list.length) return;
            list.slice(0, 5).forEach(function (s) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'ai-suggestion-chip';
                chip.textContent = s;
                chip.addEventListener('click', function () {
                    sendMessage(s);
                });
                suggWrap.appendChild(chip);
            });
        }

        function appendMessage(role, text, source) {
            if (welcome && welcome.parentNode) welcome.parentNode.removeChild(welcome);

            var wrap = document.createElement('div');
            wrap.className = 'ai-msg ' + (role === 'user' ? 'user' : 'bot');

            var avatar = document.createElement('div');
            avatar.className = 'ai-msg-avatar';
            avatar.textContent = role === 'user' ? '👤' : '🤖';

            var inner = document.createElement('div');

            var bubble = document.createElement('div');
            bubble.className = 'ai-msg-bubble';
            bubble.textContent = text;

            inner.appendChild(bubble);

            if (source && role !== 'user') {
                var src = document.createElement('div');
                src.className = 'ai-msg-source';
                src.textContent = source === 'openai' ? '✦ GPT' : source === 'rule_based' ? '⚙ Rule-based' : source;
                inner.appendChild(src);
            }

            wrap.appendChild(avatar);
            wrap.appendChild(inner);
            messages.appendChild(wrap);
            scrollBottom();

            if (!isOpen) {
                unread++;
                badge.textContent = unread > 9 ? '9+' : String(unread);
                badge.classList.add('visible');
            }
        }

        function showTyping() {
            var t = document.createElement('div');
            t.className = 'ai-msg bot';
            t.id = 'ai-typing-indicator';
            var avatar = document.createElement('div');
            avatar.className = 'ai-msg-avatar';
            avatar.textContent = '🤖';
            var dots = document.createElement('div');
            dots.className = 'ai-typing';
            dots.innerHTML = '<span></span><span></span><span></span>';
            t.appendChild(avatar);
            t.appendChild(dots);
            messages.appendChild(t);
            scrollBottom();
        }

        function hideTyping() {
            var t = document.getElementById('ai-typing-indicator');
            if (t) t.parentNode.removeChild(t);
        }

        function showChart(boxId, canvasId) {
            var box = document.getElementById(boxId);
            var canvas = document.getElementById(canvasId);
            var loader = box ? box.querySelector('.chart-loading') : null;
            if (loader) loader.style.display = 'none';
            if (canvas) canvas.style.display = '';
            return canvas;
        }

        function showEmpty(boxId) {
            var box = document.getElementById(boxId);
            if (!box) return;
            var loader = box.querySelector('.chart-loading');
            if (loader) loader.innerHTML = '<span class="muted">Không có dữ liệu</span>';
        }

        function ensureChartJs() {
            if (window.Chart) {
                return Promise.resolve();
            }

            if (chartJsPromise) {
                return chartJsPromise;
            }

            chartJsPromise = new Promise(function (resolve, reject) {
                var script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                script.async = true;
                script.onload = function () { resolve(); };
                script.onerror = function () { reject(new Error('Failed to load Chart.js')); };
                document.head.appendChild(script);
            });

            return chartJsPromise;
        }

        function fetchChartsData(cb) {
            if (chartDataCache) {
                cb(chartDataCache);
                return;
            }

            fetch(chartsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    chartDataCache = d.charts || {};
                    cb(chartDataCache);
                })
                .catch(function () { cb(null); });
        }

        function renderMiniChart(key) {
            if (chartInstance) {
                chartInstance.destroy();
                chartInstance = null;
            }
            chartCanvas.style.display = 'none';
            chartLoading.style.display = 'block';
            chartLoading.textContent = 'Đang tải…';

            fetchChartsData(function (data) {
                if (!data) {
                    chartLoading.textContent = 'Không thể tải dữ liệu.';
                    return;
                }
                var c = data[key];
                if (!c || !c.labels || !c.labels.length) {
                    chartLoading.textContent = 'Không có dữ liệu.';
                    return;
                }

                chartLoading.style.display = 'none';
                chartCanvas.style.display = 'block';

                var type = (key === 'leave' || key === 'recruitment') ? 'doughnut' : (key === 'attendance' ? 'line' : 'bar');
                var datasets = [{
                    data: c.values,
                    backgroundColor: type === 'line' ? 'rgba(79,70,229,.15)' : PALETTE.slice(0, c.values.length),
                    borderColor: type === 'line' ? '#4f46e5' : undefined,
                    borderWidth: type === 'line' ? 2 : 0,
                    borderRadius: type === 'bar' ? 5 : 0,
                    fill: type === 'line',
                    tension: type === 'line' ? 0.4 : 0,
                    pointRadius: type === 'line' ? 3 : 0,
                    pointBackgroundColor: type === 'line' ? '#4f46e5' : undefined,
                    hoverOffset: type === 'doughnut' ? 6 : 0,
                }];

                chartInstance = new Chart(chartCanvas, {
                    type: type,
                    data: { labels: c.labels, datasets: datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: type === 'doughnut', position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } }
                        },
                        scales: type !== 'doughnut' ? { y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } }, x: { ticks: { font: { size: 10 } } } } : {}
                    }
                });
            });
        }

        function openPanel() {
            isOpen = true;
            panel.classList.add('is-open');
            fab.classList.add('is-open');
            unread = 0;
            badge.classList.remove('visible');
            badge.textContent = '';
            input.focus();
            scrollBottom();
            if (!suggWrap.children.length) renderSuggestions(DEFAULT_SUGGESTIONS);
        }

        function closePanel() {
            isOpen = false;
            panel.classList.remove('is-open');
            fab.classList.remove('is-open');
        }

        function executeDraft(token, reason) {
            var fd = new FormData();
            fd.append('_token', csrf);
            fd.append('action_token', token);
            if (reason) fd.append('confirm_reason', reason);

            fetch(confirmUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    draftZone.innerHTML = '';
                    appendMessage('bot', d.ok ? '✅ ' + (d.message || 'Thực thi thành công!') : '❌ ' + (d.message || 'Thực thi thất bại.'), 'system');
                })
                .catch(function () {
                    draftZone.innerHTML = '';
                    appendMessage('bot', '❌ Không thể thực thi. Vui lòng thử lại.', 'system');
                });
        }

        function renderDraft(draft) {
            draftZone.innerHTML = '';
            if (!draft) return;
            var banner = document.createElement('div');
            banner.className = 'ai-draft-banner';
            banner.innerHTML = '<strong>⚡ ' + escHtml(draft.title || 'Xác nhận hành động') + '</strong>' +
                '<div>' + escHtml(draft.summary || '') + '</div>';

            var actions = document.createElement('div');
            actions.className = 'ai-draft-banner-actions';

            var confirmBtn = document.createElement('button');
            confirmBtn.type = 'button';
            confirmBtn.className = 'ai-draft-confirm';
            confirmBtn.textContent = draft.confirm_label || 'Xác nhận thực thi';
            confirmBtn.addEventListener('click', function () {
                confirmBtn.disabled = true;
                confirmBtn.textContent = 'Đang thực thi…';
                executeDraft(draft.token, '');
            });

            var cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'ai-draft-cancel';
            cancelBtn.textContent = 'Huỷ';
            cancelBtn.addEventListener('click', function () { draftZone.innerHTML = ''; });

            actions.appendChild(confirmBtn);
            actions.appendChild(cancelBtn);
            banner.appendChild(actions);
            draftZone.appendChild(banner);
        }

        function sendMessage(text) {
            text = text.trim();
            if (!text || isBusy) return;

            isBusy = true;
            sendBtn.disabled = true;
            input.value = '';
            autoResize();
            statusText.textContent = 'Đang xử lý…';
            suggWrap.innerHTML = '';
            draftZone.innerHTML = '';

            appendMessage('user', text);
            showTyping();

            var fd = new FormData();
            fd.append('_token', csrf);
            fd.append('message', text);

            fetch(askUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: fd })
                .then(function (r) {
                    if (r.status === 401) { window.location.reload(); return null; }
                    return r.json();
                })
                .then(function (d) {
                    hideTyping();
                    if (!d) return;
                    if (!d.ok) {
                        appendMessage('bot', '⚠️ ' + (d.message || 'Lỗi không xác định.'), 'error');
                    } else {
                        appendMessage('bot', d.reply || '…', d.source || '');
                        renderSuggestions(d.suggestions || DEFAULT_SUGGESTIONS);
                        if (d.action_draft) renderDraft(d.action_draft);
                    }
                    statusText.textContent = 'Sẵn sàng';
                })
                .catch(function () {
                    hideTyping();
                    appendMessage('bot', '⚠️ Không thể kết nối tới bot service. Đảm bảo Python service đang chạy.', 'error');
                    statusText.textContent = 'Lỗi kết nối';
                })
                .finally(function () {
                    isBusy = false;
                    sendBtn.disabled = false;
                    input.focus();
                });
        }

        chartToggleBtn.addEventListener('click', function () {
            chartOpen = !chartOpen;
            chartPanel.style.display = chartOpen ? 'block' : 'none';
            chartToggleBtn.style.background = chartOpen ? 'rgba(255,255,255,0.35)' : '';
            if (chartOpen) {
                chartLoading.textContent = 'Đang tải…';
                chartLoading.style.display = 'block';
                chartCanvas.style.display = 'none';
                ensureChartJs().then(function () {
                    renderMiniChart(chartSelect.value);
                }).catch(function () {
                    chartLoading.textContent = 'Không tải được Chart.js.';
                });
            }
        });

        chartSelect.addEventListener('change', function () {
            if (chartOpen) renderMiniChart(chartSelect.value);
        });

        fab.addEventListener('click', function () { isOpen ? closePanel() : openPanel(); });
        document.getElementById('ai-chat-close').addEventListener('click', closePanel);
        document.getElementById('ai-chat-clear').addEventListener('click', function () {
            messages.innerHTML = '';
            messages.appendChild(welcome);
            suggWrap.innerHTML = '';
            draftZone.innerHTML = '';
            renderSuggestions(DEFAULT_SUGGESTIONS);
        });

        sendBtn.addEventListener('click', function () { sendMessage(input.value); });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage(input.value);
            }
        });

        function autoResize() {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 120) + 'px';
        }
        input.addEventListener('input', autoResize);

        renderSuggestions(DEFAULT_SUGGESTIONS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
