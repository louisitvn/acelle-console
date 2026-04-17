<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Support Console — {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #0d1117;
            --bg-alt: #161b22;
            --border: #30363d;
            --fg: #c9d1d9;
            --fg-dim: #8b949e;
            --accent: #58a6ff;
            --ok: #3fb950;
            --err: #f85149;
            --warn: #d29922;
            --mono: ui-monospace, "SF Mono", "Menlo", "Consolas", "Liberation Mono", monospace;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: var(--bg);
            color: var(--fg);
            font-family: var(--mono);
            font-size: 13.5px;
            line-height: 1.55;
        }

        #app {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 14px;
            background: var(--bg-alt);
            border-bottom: 1px solid var(--border);
            font-size: 12px;
            color: var(--fg-dim);
            flex-shrink: 0;
        }

        .topbar .left { display: flex; gap: 14px; align-items: center; }
        .topbar .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--ok);
            margin-right: 4px;
        }
        .topbar .dot.off { background: var(--err); }
        .topbar a { color: var(--accent); text-decoration: none; }
        .topbar a:hover { text-decoration: underline; }

        #output {
            flex: 1;
            overflow-y: auto;
            padding: 14px 16px 20px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        #output .line { margin: 0; padding: 2px 0; }
        #output .prompt-echo { color: var(--fg-dim); }
        #output .prompt-echo .user { color: var(--ok); }
        #output .prompt-echo .type { color: var(--warn); }
        #output .prompt-echo .cmd { color: var(--fg); }
        #output .ok { color: var(--ok); }
        #output .err { color: var(--err); }
        #output .stderr { color: #f85149bb; }
        #output .meta { color: var(--fg-dim); font-size: 12px; }
        #output .json { color: var(--fg); }
        #output .hint { color: var(--warn); }
        #output .banner {
            color: var(--fg-dim);
            padding: 4px 0 12px;
            border-bottom: 1px dashed var(--border);
            margin-bottom: 10px;
        }
        #output .banner .brand { color: var(--accent); font-weight: bold; }

        .promptbar {
            display: flex;
            align-items: stretch;
            gap: 0;
            background: var(--bg-alt);
            border-top: 1px solid var(--border);
            padding: 8px 10px;
            flex-shrink: 0;
        }

        .promptbar select, .promptbar input[type="number"] {
            background: var(--bg);
            color: var(--fg);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 4px 8px;
            font-family: var(--mono);
            font-size: 13px;
            margin-right: 8px;
        }

        .promptbar .sigil {
            display: flex;
            align-items: center;
            color: var(--ok);
            padding: 0 8px 0 4px;
            font-weight: bold;
            user-select: none;
        }

        .promptbar #input {
            flex: 1;
            background: transparent;
            color: var(--fg);
            border: none;
            outline: none;
            font-family: var(--mono);
            font-size: 14px;
            padding: 4px 0;
            resize: none;
            line-height: 1.4;
            min-height: 22px;
            max-height: 120px;
        }

        .promptbar #input:focus { outline: none; }

        .promptbar .redact {
            display: flex;
            align-items: center;
            gap: 4px;
            color: var(--fg-dim);
            font-size: 12px;
            margin-left: 8px;
            white-space: nowrap;
            user-select: none;
        }

        .promptbar .redact input { accent-color: var(--accent); }

        .promptbar.pending #input { opacity: 0.5; }
        .promptbar.pending .sigil::after {
            content: " …";
            animation: blink 1s steps(2) infinite;
        }

        @keyframes blink { 50% { opacity: 0.3; } }

        /* Scrollbar */
        #output::-webkit-scrollbar { width: 10px; }
        #output::-webkit-scrollbar-track { background: var(--bg); }
        #output::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        #output::-webkit-scrollbar-thumb:hover { background: #484f58; }
    </style>
</head>
<body>
<div id="app">
    <div class="topbar">
        <div class="left">
            <span><span class="dot {{ $enabled ? '' : 'off' }}"></span>{{ $enabled ? 'feature: enabled' : 'feature: DISABLED' }}</span>
            <span>{{ $user->email }}</span>
            <span>{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost' }}</span>
        </div>
        <div class="right">
            <a href="{{ route('plugin.acelle.console.dashboard') }}">settings</a>
            &nbsp;·&nbsp;
            <a href="{{ route('plugin.acelle.console.logs') }}">audit log</a>
            &nbsp;·&nbsp;
            <a href="#" id="btn-clear">clear</a>
        </div>
    </div>

    <div id="output" tabindex="0"></div>

    <form class="promptbar" id="promptbar" autocomplete="off">
        <select id="type" title="Executor type (Tab to cycle)">
            <option value="shell">shell</option>
            <option value="tinker">tinker</option>
            <option value="artisan">artisan</option>
        </select>
        <span class="sigil" id="sigil">$</span>
        <textarea id="input" rows="1" placeholder="type a command — Enter to run, Shift+Enter newline, ↑/↓ history, Tab cycles type, Ctrl+L clear" autofocus></textarea>
        <input type="number" id="timeout" value="30" min="1" max="120" title="Timeout (seconds), max 120" style="width: 60px;">
        <label class="redact"><input type="checkbox" id="redact"> redact</label>
    </form>
</div>

<script>
(function () {
    const SUPPORT = {
        token: @json($apiToken),
        base:  @json($baseUrl),
        user:  @json(['id' => $user->id, 'email' => $user->email]),
    };

    const $output  = document.getElementById('output');
    const $input   = document.getElementById('input');
    const $type    = document.getElementById('type');
    const $timeout = document.getElementById('timeout');
    const $redact  = document.getElementById('redact');
    const $sigil   = document.getElementById('sigil');
    const $bar     = document.getElementById('promptbar');
    const $clear   = document.getElementById('btn-clear');

    const HISTORY_KEY = 'support_console_history_v1';
    let history = [];
    try { history = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]'); } catch (e) { history = []; }
    let histIdx = history.length;
    let pending = null; // AbortController

    // ─── output helpers ─────────────────────────────────────────────

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function write(html, cls) {
        const div = document.createElement('div');
        div.className = 'line' + (cls ? ' ' + cls : '');
        div.innerHTML = html;
        $output.appendChild(div);
        $output.scrollTop = $output.scrollHeight;
    }

    function writeText(text, cls) {
        if (text == null || text === '') return;
        write(esc(text), cls);
    }

    function echoPrompt(type, command) {
        const host = (location.hostname || 'localhost');
        write(
            '<span class="user">' + esc(SUPPORT.user.email) + '@' + esc(host) + '</span>' +
            ' <span class="type">[' + esc(type) + ']</span>' +
            ' <span class="meta">$</span> ' +
            '<span class="cmd">' + esc(command) + '</span>',
            'prompt-echo'
        );
    }

    function banner() {
        const host = location.hostname || 'localhost';
        write(
            '<span class="brand">Acelle Support Console</span>   ' +
            '<span class="meta">host:</span> ' + esc(host) +
            '   <span class="meta">user:</span> ' + esc(SUPPORT.user.email) + '\n' +
            '<span class="meta">Type <b>help</b> for built-in commands. All other input is sent to the server executor.</span>',
            'banner'
        );
    }

    // ─── HTTP ───────────────────────────────────────────────────────

    async function api(path, opts = {}) {
        pending = new AbortController();
        $bar.classList.add('pending');
        $input.disabled = true;
        try {
            const res = await fetch(SUPPORT.base + path, Object.assign({
                headers: {
                    'Authorization': 'Bearer ' + SUPPORT.token,
                    'Accept': 'application/json',
                },
                signal: pending.signal,
            }, opts));
            const text = await res.text();
            let json;
            try { json = JSON.parse(text); } catch (e) { json = { _raw: text }; }
            return { status: res.status, ok: res.ok, json };
        } finally {
            $bar.classList.remove('pending');
            $input.disabled = false;
            pending = null;
            $input.focus();
        }
    }

    async function runExec(type, command) {
        const body = {
            type: type,
            command: command,
            timeout: parseInt($timeout.value, 10) || 30,
            redact: $redact.checked,
        };

        try {
            const r = await api('/exec', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + SUPPORT.token, 'Accept': 'application/json' },
                body: JSON.stringify(body),
            });

            if (!r.ok) {
                const err = r.json && r.json.error ? r.json.error : ('HTTP ' + r.status);
                writeText('✖ ' + err, 'err');
                if (r.json && r.json.message) writeText(r.json.message, 'err');
                if (r.json && r.json.errors) writeText(JSON.stringify(r.json.errors, null, 2), 'err');
                return;
            }

            const d = r.json;
            if (d.stdout) writeText(d.stdout.replace(/\n$/, ''));
            if (d.stderr) writeText(d.stderr.replace(/\n$/, ''), 'stderr');
            if (d.truncated) writeText('[output truncated]', 'hint');

            const cls = d.exit_code === 0 ? 'ok' : 'err';
            const sym = d.exit_code === 0 ? '✓' : '✖';
            write(
                sym + ' exit=' + esc(d.exit_code) +
                '  <span class="meta">(' + esc(d.duration_ms) + 'ms, ' + esc(d.output_bytes) + 'B)</span>',
                cls
            );
        } catch (e) {
            if (e.name === 'AbortError') writeText('[aborted]', 'hint');
            else writeText('✖ network error: ' + e.message, 'err');
        }
    }

    async function runGet(path, label) {
        try {
            const r = await api(path);
            if (!r.ok) {
                writeText('✖ ' + (r.json.error || ('HTTP ' + r.status)), 'err');
                return;
            }
            writeText(JSON.stringify(r.json, null, 2), 'json');
            if (label) write('<span class="meta">— ' + esc(label) + '</span>', 'meta');
        } catch (e) {
            if (e.name === 'AbortError') writeText('[aborted]', 'hint');
            else writeText('✖ network error: ' + e.message, 'err');
        }
    }

    // ─── built-in commands ──────────────────────────────────────────

    function builtinHelp() {
        write([
            '<span class="meta">Built-in commands (client side):</span>',
            '  help                this screen',
            '  clear / cls         clear output',
            '  history             show command history',
            '  :shell | :tinker | :artisan   switch executor type',
            '  :timeout N          set timeout seconds (1..120)',
            '  :redact on|off      toggle output redaction',
            '',
            '<span class="meta">Meta commands (hit support API):</span>',
            '  :whoami             GET /whoami',
            '  :bundle             GET /bundle (diagnostic snapshot)',
            '  :logs [N]           GET /logs?limit=N',
            '',
            '<span class="meta">Keyboard:</span>',
            '  Enter               run (Shift+Enter = newline)',
            '  ↑ / ↓               history',
            '  Tab                 cycle type (shell → tinker → artisan)',
            '  Ctrl+L              clear output',
            '  Esc                 abort running request',
            '',
            '<span class="meta">Current type is <b>' + esc($type.value) + '</b>. Anything else is sent to /exec with that type.</span>',
        ].join('\n'), 'meta');
    }

    function handleBuiltin(raw) {
        const cmd = raw.trim();
        const lc = cmd.toLowerCase();

        if (lc === 'help' || lc === '?') { builtinHelp(); return true; }
        if (lc === 'clear' || lc === 'cls') { $output.innerHTML = ''; banner(); return true; }
        if (lc === 'history') {
            write(history.map((h, i) => '  ' + (i + 1) + '  ' + esc(h)).join('\n') || '<span class="meta">(empty)</span>', 'meta');
            return true;
        }

        if (lc === ':shell')   { $type.value = 'shell';   writeText('[type=shell]',   'hint'); return true; }
        if (lc === ':tinker')  { $type.value = 'tinker';  writeText('[type=tinker]',  'hint'); return true; }
        if (lc === ':artisan') { $type.value = 'artisan'; writeText('[type=artisan]', 'hint'); return true; }

        let m;
        if (m = lc.match(/^:timeout\s+(\d+)$/)) {
            const n = Math.max(1, Math.min(120, parseInt(m[1], 10)));
            $timeout.value = n;
            writeText('[timeout=' + n + 's]', 'hint');
            return true;
        }

        if (m = lc.match(/^:redact\s+(on|off|true|false|yes|no)$/)) {
            $redact.checked = /^(on|true|yes)$/.test(m[1]);
            writeText('[redact=' + $redact.checked + ']', 'hint');
            return true;
        }

        if (lc === ':whoami') { runGet('/whoami', 'whoami'); return true; }
        if (lc === ':bundle') { runGet('/bundle', 'bundle snapshot'); return true; }

        if (m = cmd.match(/^:logs(?:\s+(\d+))?$/i)) {
            const n = m[1] ? parseInt(m[1], 10) : 20;
            runGet('/logs?limit=' + n, 'last ' + n + ' audit rows');
            return true;
        }

        return false;
    }

    // ─── submit flow ────────────────────────────────────────────────

    async function submit() {
        const raw = $input.value;
        if (!raw.trim()) return;

        echoPrompt($type.value, raw);

        // history
        if (history[history.length - 1] !== raw) {
            history.push(raw);
            if (history.length > 200) history.shift();
            try { localStorage.setItem(HISTORY_KEY, JSON.stringify(history)); } catch (e) {}
        }
        histIdx = history.length;

        const val = $input.value;
        $input.value = '';
        autoResize();

        if (handleBuiltin(val)) return;

        await runExec($type.value, val);
    }

    // ─── input behavior ─────────────────────────────────────────────

    function autoResize() {
        $input.style.height = 'auto';
        $input.style.height = Math.min($input.scrollHeight, 120) + 'px';
    }

    $input.addEventListener('input', autoResize);

    $input.addEventListener('keydown', function (e) {
        // Enter = submit, Shift+Enter = newline
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            submit();
            return;
        }

        // Ctrl+L = clear
        if (e.ctrlKey && (e.key === 'l' || e.key === 'L')) {
            e.preventDefault();
            $output.innerHTML = '';
            banner();
            return;
        }

        // Escape = abort
        if (e.key === 'Escape') {
            if (pending) {
                e.preventDefault();
                pending.abort();
            }
            return;
        }

        // Tab = cycle executor
        if (e.key === 'Tab' && !e.shiftKey) {
            e.preventDefault();
            const order = ['shell', 'tinker', 'artisan'];
            $type.value = order[(order.indexOf($type.value) + 1) % order.length];
            return;
        }

        // History up/down — only at caret boundaries and single-line
        if (e.key === 'ArrowUp' && !$input.value.includes('\n')) {
            if (histIdx > 0) {
                histIdx--;
                $input.value = history[histIdx] || '';
                autoResize();
                e.preventDefault();
            }
            return;
        }

        if (e.key === 'ArrowDown' && !$input.value.includes('\n')) {
            if (histIdx < history.length - 1) {
                histIdx++;
                $input.value = history[histIdx] || '';
            } else {
                histIdx = history.length;
                $input.value = '';
            }
            autoResize();
            e.preventDefault();
            return;
        }
    });

    $bar.addEventListener('submit', function (e) { e.preventDefault(); submit(); });

    $clear.addEventListener('click', function (e) {
        e.preventDefault();
        $output.innerHTML = '';
        banner();
        $input.focus();
    });

    // Focus input when clicking anywhere outside controls
    $output.addEventListener('click', function () { $input.focus(); });

    // ─── boot ───────────────────────────────────────────────────────

    banner();
    (async function () {
        await runGet('/whoami', 'connected');
        $input.focus();
    })();
})();
</script>
</body>
</html>
