<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $store->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #0f1115;
            color: #e6e6e6;
        }
        .topbar {
            background: #14161c;
            border-bottom: 1px solid #2c2f38;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .brand { font-size: 20px; font-weight: 600; }
        .topbar-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .store-links a {
            color: #8a93a6;
            text-decoration: none;
            font-size: 13px;
            margin-left: 10px;
        }
        .store-links a:hover { color: #e6e6e6; }
        select {
            background: #1c1f26;
            color: #e6e6e6;
            border: 1px solid #2c2f38;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 14px;
        }
        .layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
            align-items: start;
        }
        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
        }
        h2 {
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #8a93a6;
            margin: 28px 0 12px;
        }
        h2:first-child { margin-top: 0; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 12px;
        }
        .card {
            background: #14161c;
            border: 1px solid #2c2f38;
            border-radius: 10px;
            padding: 14px;
        }
        .card .name { font-size: 14px; margin-bottom: 8px; }
        .card .price { font-size: 16px; font-weight: 600; color: #6f8cff; }
        .chat-panel {
            background: #14161c;
            border: 1px solid #2c2f38;
            border-radius: 12px;
            padding: 16px;
            position: sticky;
            top: 24px;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 120px);
        }
        .chat-panel h3 { margin: 0 0 12px; font-size: 15px; }
        .messages {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 12px;
        }
        .msg {
            max-width: 85%;
            padding: 9px 12px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.45;
            white-space: pre-wrap;
        }
        .msg.user { align-self: flex-end; background: #3a5bfd; color: white; }
        .msg.bot { align-self: flex-start; background: #22252d; }
        .msg.pending { align-self: flex-start; background: #22252d; color: #8a93a6; font-style: italic; }
        form { display: flex; gap: 8px; }
        input[type=text] {
            flex: 1;
            background: #1c1f26;
            color: #e6e6e6;
            border: 1px solid #2c2f38;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
        }
        button {
            background: #3a5bfd;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0 16px;
            font-size: 14px;
            cursor: pointer;
        }
        button:disabled { opacity: 0.5; cursor: default; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand">{{ $store->name }}</div>
        <div class="topbar-right">
            <span class="store-links">
                Diğer mağazalar:
                @foreach ($otherStores as $other)
                    <a href="/magaza/{{ $other->id }}">{{ $other->name }}</a>
                @endforeach
            </span>
            <select id="userSelect">
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->persona }})</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="layout">
        <main>
            @foreach ($productsByCategory as $categoryName => $products)
                <h2>{{ $categoryName }}</h2>
                <div class="grid">
                    @foreach ($products as $product)
                        <div class="card">
                            <div class="name">{{ $product->name }}</div>
                            <div class="price">{{ $product->price }} TL</div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </main>

        <aside class="chat-panel">
            <h3>{{ $store->name }} Asistanı</h3>
            <div class="messages" id="messages"></div>
            <form id="chatForm">
                <input type="text" id="messageInput" placeholder="Ne arıyorsun?" autocomplete="off" required>
                <button type="submit" id="sendButton">Gönder</button>
            </form>
        </aside>
    </div>

    <script>
        const storeId = {{ $store->id }};
        const messagesEl = document.getElementById('messages');
        const form = document.getElementById('chatForm');
        const input = document.getElementById('messageInput');
        const userSelect = document.getElementById('userSelect');
        const sendButton = document.getElementById('sendButton');

        function addMessage(text, role) {
            const div = document.createElement('div');
            div.className = 'msg ' + role;
            div.textContent = text;
            messagesEl.appendChild(div);
            messagesEl.scrollTop = messagesEl.scrollHeight;
            return div;
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = input.value.trim();
            if (!message) return;

            addMessage(message, 'user');
            input.value = '';
            sendButton.disabled = true;
            const pending = addMessage('Yazıyor...', 'pending');

            try {
                const response = await fetch('/api/chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        user_id: userSelect.value,
                        store_id: storeId,
                        message: message,
                    }),
                });

                const data = await response.json();
                pending.remove();
                addMessage(response.ok ? data.reply : ('Hata: ' + (data.message || 'Bilinmeyen hata')), 'bot');
            } catch (err) {
                pending.remove();
                addMessage('Sunucuya ulaşılamadı: ' + err.message, 'bot');
            } finally {
                sendButton.disabled = false;
                input.focus();
            }
        });
    </script>
</body>
</html>
