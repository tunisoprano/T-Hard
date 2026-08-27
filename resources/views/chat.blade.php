<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>T-Hard Asistan</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #0f1115;
            color: #e6e6e6;
            display: flex;
            justify-content: center;
            padding: 24px;
        }
        .app {
            width: 100%;
            max-width: 640px;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 48px);
        }
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        h1 { font-size: 18px; margin: 0; }
        select {
            background: #1c1f26;
            color: #e6e6e6;
            border: 1px solid #2c2f38;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 14px;
        }
        .messages {
            flex: 1;
            overflow-y: auto;
            background: #14161c;
            border: 1px solid #2c2f38;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .msg {
            max-width: 80%;
            padding: 10px 14px;
            border-radius: 12px;
            line-height: 1.4;
            white-space: pre-wrap;
        }
        .msg.user {
            align-self: flex-end;
            background: #3a5bfd;
            color: white;
        }
        .msg.bot {
            align-self: flex-start;
            background: #22252d;
        }
        .msg.pending {
            align-self: flex-start;
            background: #22252d;
            color: #999;
            font-style: italic;
        }
        form {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        input[type=text] {
            flex: 1;
            background: #1c1f26;
            color: #e6e6e6;
            border: 1px solid #2c2f38;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 15px;
        }
        button {
            background: #3a5bfd;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0 20px;
            font-size: 15px;
            cursor: pointer;
        }
        button:disabled { opacity: 0.5; cursor: default; }
    </style>
</head>
<body>
    <div class="app">
        <header>
            <h1>T-Hard Asistan</h1>
            <select id="userSelect">
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->persona }})</option>
                @endforeach
            </select>
        </header>

        <div class="messages" id="messages"></div>

        <form id="chatForm">
            <input type="text" id="messageInput" placeholder="Bir şey sor..." autocomplete="off" required>
            <button type="submit" id="sendButton">Gönder</button>
        </form>
    </div>

    <script>
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
            const botBubble = addMessage('Yazıyor...', 'pending');

            try {
                const response = await fetch('/api/chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        user_id: userSelect.value,
                        message: message,
                    }),
                });

                if (!response.ok) {
                    const data = await response.json();
                    botBubble.className = 'msg bot';
                    botBubble.textContent = 'Hata: ' + (data.message || 'Bilinmeyen bir hata oluştu.');
                    return;
                }

                // Cevap artık tek seferde değil, parça parça (stream) geliyor —
                // her parça geldikçe balonun içeriğine ekliyoruz.
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let firstChunk = true;

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    if (firstChunk) {
                        botBubble.className = 'msg bot';
                        botBubble.textContent = '';
                        firstChunk = false;
                    }

                    botBubble.textContent += decoder.decode(value, { stream: true });
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                }
            } catch (err) {
                botBubble.className = 'msg bot';
                botBubble.textContent = 'Sunucuya ulaşılamadı: ' + err.message;
            } finally {
                sendButton.disabled = false;
                input.focus();
            }
        });
    </script>
</body>
</html>
