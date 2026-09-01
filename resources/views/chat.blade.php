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
        .search-form { margin-top: 0; margin-bottom: 12px; }
        .search-results {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 12px;
        }
        .search-card {
            background: #14161c;
            border: 1px solid #2c2f38;
            border-radius: 10px;
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .search-card .info { font-size: 14px; }
        .search-card .meta { font-size: 11px; color: #8a93a6; margin-top: 2px; }
        .search-card .price { font-size: 14px; font-weight: 600; color: #6f8cff; white-space: nowrap; }
        .search-empty { color: #8a93a6; font-size: 13px; }
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

        <form id="searchForm" class="search-form">
            <input type="text" id="searchInput" placeholder="Ürünlerde anlamına göre ara... (örn. 'kışlık bir şeyler')" autocomplete="off">
            <button type="submit">Ara</button>
            <button type="button" id="clearSearchButton" hidden>Kapat</button>
        </form>
        <div class="search-results" id="searchResults"></div>

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

        // --- Semantik arama (tüm mağazalar) ---
        const searchForm = document.getElementById('searchForm');
        const searchInput = document.getElementById('searchInput');
        const clearSearchButton = document.getElementById('clearSearchButton');
        const searchResultsEl = document.getElementById('searchResults');

        function renderSearchCard(product) {
            const card = document.createElement('div');
            card.className = 'search-card';

            const info = document.createElement('div');
            info.className = 'info';

            const name = document.createElement('div');
            name.textContent = product.name;

            const meta = document.createElement('div');
            meta.className = 'meta';
            meta.textContent = product.category + ' · ' + product.store + ' · benzerlik: ' + product.score;

            info.append(name, meta);

            const price = document.createElement('div');
            price.className = 'price';
            price.textContent = product.price + ' TL';

            card.append(info, price);
            return card;
        }

        searchForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const q = searchInput.value.trim();
            if (!q) return;

            searchResultsEl.textContent = '';
            const loading = document.createElement('div');
            loading.className = 'search-empty';
            loading.textContent = 'Aranıyor...';
            searchResultsEl.appendChild(loading);
            clearSearchButton.hidden = false;

            try {
                const response = await fetch(`/api/search?q=${encodeURIComponent(q)}`);
                const data = await response.json();

                searchResultsEl.textContent = '';

                if (!data.data.length) {
                    const empty = document.createElement('div');
                    empty.className = 'search-empty';
                    empty.textContent = 'Sonuç bulunamadı.';
                    searchResultsEl.appendChild(empty);
                    return;
                }

                data.data.forEach(product => searchResultsEl.appendChild(renderSearchCard(product)));
            } catch (err) {
                searchResultsEl.textContent = '';
                const errEl = document.createElement('div');
                errEl.className = 'search-empty';
                errEl.textContent = 'Arama başarısız: ' + err.message;
                searchResultsEl.appendChild(errEl);
            }
        });

        clearSearchButton.addEventListener('click', () => {
            searchInput.value = '';
            searchResultsEl.textContent = '';
            clearSearchButton.hidden = true;
        });

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

                // Cevap artık streaming değil — Ollama tam cevabı üretene
                // kadar bekleyip tek seferde JSON olarak alıyoruz.
                const data = await response.json();
                botBubble.className = 'msg bot';
                botBubble.textContent = data.content;
                messagesEl.scrollTop = messagesEl.scrollHeight;
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
