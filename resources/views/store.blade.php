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
        .card .meta { font-size: 11px; color: #8a93a6; margin-top: 4px; }
        .card .add-to-cart-btn {
            margin-top: 8px;
            width: 100%;
            background: #22252d;
            color: #e6e6e6;
            border: 1px solid #2c2f38;
            border-radius: 6px;
            padding: 6px 8px;
            font-size: 12px;
            cursor: pointer;
        }
        .card .add-to-cart-btn:hover { background: #2c2f38; }
        .card .add-to-cart-btn.added { background: #1e3a2b; border-color: #2f6b46; color: #7ee2a8; }
        .cart-button {
            position: relative;
            background: #1c1f26;
            color: #e6e6e6;
            border: 1px solid #2c2f38;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 14px;
            cursor: pointer;
        }
        .cart-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #3a5bfd;
            color: white;
            border-radius: 999px;
            font-size: 11px;
            padding: 1px 6px;
            min-width: 16px;
        }
        .cart-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            justify-content: flex-end;
            z-index: 100;
        }
        .cart-overlay:not([hidden]) { display: flex; }
        .cart-panel {
            width: 380px;
            max-width: 100%;
            height: 100%;
            background: #14161c;
            border-left: 1px solid #2c2f38;
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .cart-panel h2 { margin-top: 0; display: flex; justify-content: space-between; align-items: center; }
        .cart-panel h2 button {
            background: none;
            border: none;
            color: #8a93a6;
            font-size: 20px;
            cursor: pointer;
        }
        .cart-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #2c2f38;
        }
        .cart-line .name { font-size: 14px; }
        .cart-line .unit-price { font-size: 12px; color: #8a93a6; }
        .cart-line .qty-controls { display: flex; align-items: center; gap: 6px; }
        .cart-line .qty-controls button {
            background: #1c1f26;
            color: #e6e6e6;
            border: 1px solid #2c2f38;
            border-radius: 6px;
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        .cart-line .remove-btn { color: #e07171; background: none; border: none; cursor: pointer; font-size: 12px; }
        .cart-total {
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            font-weight: 600;
            margin: 16px 0;
        }
        .checkout-btn {
            background: #3a5bfd;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 15px;
            cursor: pointer;
        }
        .checkout-btn:disabled { opacity: 0.5; cursor: default; }
        .cart-empty { color: #8a93a6; font-size: 13px; }
        .order-card {
            background: #1c1f26;
            border: 1px solid #2c2f38;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 12px;
        }
        .order-card .order-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 8px;
        }
        .order-card .order-no { font-weight: 600; font-size: 14px; }
        .order-card .order-date { font-size: 12px; color: #8a93a6; }
        .order-card ul { margin: 0 0 8px; padding-left: 16px; font-size: 13px; color: #c4c9d4; line-height: 1.6; }
        .order-card .order-total { font-weight: 600; color: #6f8cff; text-align: right; }
        .search-form {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }
        .search-form input {
            flex: 1;
            background: #1c1f26;
            color: #e6e6e6;
            border: 1px solid #2c2f38;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
        }
        .search-form button {
            background: #3a5bfd;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0 16px;
            font-size: 14px;
            cursor: pointer;
        }
        .search-form button#clearSearchButton {
            background: #22252d;
            color: #c4c9d4;
        }
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
        .history {
            max-width: 1400px;
            margin: 24px auto 0;
            padding: 0 24px;
        }
        .history h2 { margin-top: 0; }
        .history-stores {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .history-store {
            background: #14161c;
            border: 1px solid #2c2f38;
            border-radius: 10px;
            padding: 12px 14px;
        }
        .history-store.current {
            border-color: #3a5bfd;
            background: #171b2c;
        }
        .history-store .store-name {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .history-store .badge {
            font-size: 10px;
            background: #3a5bfd;
            color: white;
            padding: 2px 6px;
            border-radius: 999px;
            text-transform: uppercase;
        }
        .history-store ul {
            margin: 0;
            padding-left: 16px;
            font-size: 13px;
            color: #c4c9d4;
            line-height: 1.6;
        }
        .history-empty {
            color: #8a93a6;
            font-size: 13px;
        }
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
            <button type="button" class="cart-button" id="ordersButton">📦 Siparişlerim</button>
            <button type="button" class="cart-button" id="cartButton">
                🛒 Sepetim
                <span class="cart-badge" id="cartBadge" hidden>0</span>
            </button>
        </div>
    </div>

    <div class="cart-overlay" id="cartOverlay" hidden>
        <div class="cart-panel">
            <h2>
                Sepetim
                <button type="button" id="closeCartButton">&times;</button>
            </h2>
            <div id="cartLines"></div>
            <div class="cart-total">
                <span>Toplam</span>
                <span id="cartTotal">0 TL</span>
            </div>
            <button type="button" class="checkout-btn" id="checkoutButton">Siparişi Tamamla</button>

            <h2 style="margin-top: 24px; font-size: 15px;">Sana Özel Öneriler</h2>
            <div class="grid" id="cartRecommendations"></div>
        </div>
    </div>

    <div class="cart-overlay" id="ordersOverlay" hidden>
        <div class="cart-panel">
            <h2>
                Siparişlerim — {{ $store->name }}
                <button type="button" id="closeOrdersButton">&times;</button>
            </h2>
            <div id="ordersList"></div>
        </div>
    </div>

    <div class="history">
        <h2>Müşteri Geçmişi (Tüm Mağazalar)</h2>
        <div class="history-stores" id="historyStores"></div>
    </div>

    <div class="layout">
        <main>
            <form id="searchForm" class="search-form">
                <input type="text" id="searchInput" placeholder="Anlamına göre ara... (örn. 'kışlık bir şeyler')" autocomplete="off">
                <button type="submit">Ara</button>
                <button type="button" id="clearSearchButton" hidden>Aramayı Temizle</button>
            </form>

            <div id="searchResultsView" hidden>
                <h2>Arama Sonuçları</h2>
                <div class="grid" id="searchResultsGrid"></div>
            </div>

            <div id="catalogView">
                @foreach ($productsByCategory as $categoryName => $products)
                    <h2>{{ $categoryName }}</h2>
                    <div class="grid">
                        @foreach ($products as $product)
                            <div class="card">
                                <div class="name">{{ $product->name }}</div>
                                <div class="price">{{ $product->price }} TL</div>
                                <button type="button" class="add-to-cart-btn" data-product-id="{{ $product->id }}">Sepete Ekle</button>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
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
        const historyStoresEl = document.getElementById('historyStores');

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function loadHistory() {
            historyStoresEl.textContent = 'Yükleniyor...';

            try {
                const response = await fetch(`/api/users/${userSelect.value}/orders`);
                const data = await response.json();
                const stores = data.data;

                if (!stores.length) {
                    historyStoresEl.textContent = 'Bu kullanıcının hiçbir mağazada geçmiş siparişi yok.';
                    return;
                }

                historyStoresEl.innerHTML = stores.map(store => {
                    const isCurrent = store.store_id === storeId;
                    const items = store.products
                        .map(p => `<li>${escapeHtml(p.name)} (${escapeHtml(p.category)}) × ${p.quantity}</li>`)
                        .join('');

                    return `
                        <div class="history-store ${isCurrent ? 'current' : ''}">
                            <div class="store-name">
                                ${escapeHtml(store.store_name)}
                                ${isCurrent ? '<span class="badge">Bu mağaza</span>' : ''}
                            </div>
                            <ul>${items}</ul>
                        </div>
                    `;
                }).join('');
            } catch (err) {
                historyStoresEl.textContent = 'Geçmiş yüklenemedi: ' + err.message;
            }
        }

        userSelect.addEventListener('change', () => {
            messagesEl.innerHTML = '';
            loadHistory();
            loadCart();
        });

        loadHistory();

        // --- Semantik arama ---
        const searchForm = document.getElementById('searchForm');
        const searchInput = document.getElementById('searchInput');
        const clearSearchButton = document.getElementById('clearSearchButton');
        const searchResultsView = document.getElementById('searchResultsView');
        const searchResultsGrid = document.getElementById('searchResultsGrid');
        const catalogView = document.getElementById('catalogView');

        function renderProductCard(product) {
            const card = document.createElement('div');
            card.className = 'card';

            const name = document.createElement('div');
            name.className = 'name';
            name.textContent = product.name;

            const price = document.createElement('div');
            price.className = 'price';
            price.textContent = product.price + ' TL';

            const meta = document.createElement('div');
            meta.className = 'meta';
            meta.textContent = product.score !== undefined
                ? product.category + ' · benzerlik: ' + product.score
                : product.category;

            const addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'add-to-cart-btn';
            addButton.dataset.productId = product.id;
            addButton.textContent = 'Sepete Ekle';

            card.append(name, price, meta, addButton);
            return card;
        }

        searchForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const q = searchInput.value.trim();
            if (!q) return;

            searchResultsGrid.textContent = 'Aranıyor...';
            searchResultsView.hidden = false;
            catalogView.hidden = true;
            clearSearchButton.hidden = false;

            try {
                const response = await fetch(`/api/search?q=${encodeURIComponent(q)}&store_id=${storeId}`);
                const data = await response.json();

                searchResultsGrid.textContent = '';

                if (!data.data.length) {
                    searchResultsGrid.textContent = 'Sonuç bulunamadı.';
                    return;
                }

                data.data.forEach(product => searchResultsGrid.appendChild(renderProductCard(product)));
            } catch (err) {
                searchResultsGrid.textContent = 'Arama başarısız: ' + err.message;
            }
        });

        clearSearchButton.addEventListener('click', () => {
            searchInput.value = '';
            searchResultsView.hidden = true;
            catalogView.hidden = false;
            clearSearchButton.hidden = true;
        });

        // --- Sepet ---
        const cartButton = document.getElementById('cartButton');
        const cartBadge = document.getElementById('cartBadge');
        const cartOverlay = document.getElementById('cartOverlay');
        const closeCartButton = document.getElementById('closeCartButton');
        const cartLinesEl = document.getElementById('cartLines');
        const cartTotalEl = document.getElementById('cartTotal');
        const checkoutButton = document.getElementById('checkoutButton');

        let currentCart = { items: [], total: 0 };

        function updateCartBadge() {
            const count = currentCart.items.reduce((sum, item) => sum + item.quantity, 0);
            cartBadge.textContent = count;
            cartBadge.hidden = count === 0;
        }

        function renderCartLine(item) {
            const line = document.createElement('div');
            line.className = 'cart-line';

            const info = document.createElement('div');
            const name = document.createElement('div');
            name.className = 'name';
            name.textContent = item.name;
            const unitPrice = document.createElement('div');
            unitPrice.className = 'unit-price';
            unitPrice.textContent = item.price + ' TL × ' + item.quantity + ' = ' + item.subtotal + ' TL';
            info.append(name, unitPrice);

            const controls = document.createElement('div');
            controls.className = 'qty-controls';

            const decreaseBtn = document.createElement('button');
            decreaseBtn.type = 'button';
            decreaseBtn.textContent = '−';
            decreaseBtn.addEventListener('click', () => changeQuantity(item.id, item.quantity - 1));

            const qtyLabel = document.createElement('span');
            qtyLabel.textContent = item.quantity;

            const increaseBtn = document.createElement('button');
            increaseBtn.type = 'button';
            increaseBtn.textContent = '+';
            increaseBtn.addEventListener('click', () => changeQuantity(item.id, item.quantity + 1));

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-btn';
            removeBtn.textContent = 'Kaldır';
            removeBtn.addEventListener('click', () => removeCartItem(item.id));

            controls.append(decreaseBtn, qtyLabel, increaseBtn, removeBtn);
            line.append(info, controls);
            return line;
        }

        function renderCart() {
            cartLinesEl.textContent = '';

            if (!currentCart.items.length) {
                const empty = document.createElement('div');
                empty.className = 'cart-empty';
                empty.textContent = 'Sepetiniz boş.';
                cartLinesEl.appendChild(empty);
            } else {
                currentCart.items.forEach(item => cartLinesEl.appendChild(renderCartLine(item)));
            }

            cartTotalEl.textContent = currentCart.total + ' TL';
            checkoutButton.disabled = currentCart.items.length === 0;
            updateCartBadge();
        }

        async function loadCart() {
            try {
                const response = await fetch(`/api/cart?user_id=${userSelect.value}&store_id=${storeId}`);
                currentCart = await response.json();
                renderCart();
            } catch (err) {
                cartLinesEl.textContent = 'Sepet yüklenemedi: ' + err.message;
            }
        }

        async function changeQuantity(itemId, newQuantity) {
            if (newQuantity < 1) {
                return removeCartItem(itemId);
            }
            const response = await fetch(`/api/cart/items/${itemId}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ quantity: newQuantity }),
            });
            currentCart = await response.json();
            renderCart();
        }

        async function removeCartItem(itemId) {
            const response = await fetch(`/api/cart/items/${itemId}`, { method: 'DELETE' });
            currentCart = await response.json();
            renderCart();
        }

        const cartRecommendationsEl = document.getElementById('cartRecommendations');

        async function loadCartRecommendations() {
            cartRecommendationsEl.textContent = 'Yükleniyor...';

            try {
                const response = await fetch(`/api/cart/recommendations?user_id=${userSelect.value}&store_id=${storeId}`);
                const data = await response.json();

                cartRecommendationsEl.textContent = '';

                if (!data.data.length) {
                    const empty = document.createElement('div');
                    empty.className = 'cart-empty';
                    empty.textContent = 'Henüz öneri yok.';
                    cartRecommendationsEl.appendChild(empty);
                    return;
                }

                data.data.forEach(product => cartRecommendationsEl.appendChild(renderProductCard(product)));
            } catch (err) {
                cartRecommendationsEl.textContent = 'Öneriler yüklenemedi: ' + err.message;
            }
        }

        cartButton.addEventListener('click', () => {
            loadCart();
            loadCartRecommendations();
            cartOverlay.hidden = false;
        });

        closeCartButton.addEventListener('click', () => {
            cartOverlay.hidden = true;
        });

        cartOverlay.addEventListener('click', (e) => {
            if (e.target === cartOverlay) {
                cartOverlay.hidden = true;
            }
        });

        checkoutButton.addEventListener('click', async () => {
            checkoutButton.disabled = true;
            checkoutButton.textContent = 'Gönderiliyor...';

            try {
                const response = await fetch('/api/cart/checkout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userSelect.value, store_id: storeId }),
                });
                const data = await response.json();

                if (!response.ok) {
                    alert(data.message || 'Sipariş oluşturulamadı.');
                    return;
                }

                alert('Siparişiniz oluşturuldu! Toplam: ' + data.total_amount + ' TL');
                cartOverlay.hidden = true;
                await loadCart();
                loadHistory(); // müşteri geçmişi panelini de tazele
            } finally {
                checkoutButton.textContent = 'Siparişi Tamamla';
                checkoutButton.disabled = currentCart.items.length === 0;
            }
        });

        // "Sepete Ekle" butonları hem statik katalogda hem dinamik arama
        // sonuçlarında olduğu için, tek bir delegasyon dinleyicisiyle
        // ikisini birden yakalıyoruz.
        document.body.addEventListener('click', async (e) => {
            const button = e.target.closest('.add-to-cart-btn');
            if (!button) return;

            const productId = button.dataset.productId;
            button.disabled = true;

            try {
                const response = await fetch('/api/cart/items', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: userSelect.value,
                        store_id: storeId,
                        product_id: productId,
                    }),
                });
                currentCart = await response.json();
                renderCart();

                button.classList.add('added');
                button.textContent = 'Eklendi ✓';
                setTimeout(() => {
                    button.classList.remove('added');
                    button.textContent = 'Sepete Ekle';
                    button.disabled = false;
                }, 1200);
            } catch (err) {
                button.disabled = false;
            }
        });

        loadCart();

        // --- Siparişlerim ---
        const ordersButton = document.getElementById('ordersButton');
        const ordersOverlay = document.getElementById('ordersOverlay');
        const closeOrdersButton = document.getElementById('closeOrdersButton');
        const ordersListEl = document.getElementById('ordersList');

        function renderOrderCard(order) {
            const card = document.createElement('div');
            card.className = 'order-card';

            const head = document.createElement('div');
            head.className = 'order-head';

            const orderNo = document.createElement('div');
            orderNo.className = 'order-no';
            orderNo.textContent = 'Sipariş #' + order.order_id;

            const orderDate = document.createElement('div');
            orderDate.className = 'order-date';
            orderDate.textContent = new Date(order.order_date).toLocaleDateString('tr-TR');

            head.append(orderNo, orderDate);

            const list = document.createElement('ul');
            order.items.forEach(item => {
                const li = document.createElement('li');
                li.textContent = item.name + ' (' + item.category + ') × ' + item.quantity + ' — ' + item.unit_price + ' TL';
                list.appendChild(li);
            });

            const total = document.createElement('div');
            total.className = 'order-total';
            total.textContent = 'Toplam: ' + order.total_amount + ' TL';

            card.append(head, list, total);
            return card;
        }

        async function loadOrders() {
            ordersListEl.textContent = 'Yükleniyor...';

            try {
                const response = await fetch(`/api/users/${userSelect.value}/orders/detailed?store_id=${storeId}`);
                const data = await response.json();

                ordersListEl.textContent = '';

                if (!data.data.length) {
                    const empty = document.createElement('div');
                    empty.className = 'cart-empty';
                    empty.textContent = 'Bu mağazada henüz siparişiniz yok.';
                    ordersListEl.appendChild(empty);
                    return;
                }

                data.data.forEach(order => ordersListEl.appendChild(renderOrderCard(order)));
            } catch (err) {
                ordersListEl.textContent = 'Siparişler yüklenemedi: ' + err.message;
            }
        }

        ordersButton.addEventListener('click', () => {
            loadOrders();
            ordersOverlay.hidden = false;
        });

        closeOrdersButton.addEventListener('click', () => {
            ordersOverlay.hidden = true;
        });

        ordersOverlay.addEventListener('click', (e) => {
            if (e.target === ordersOverlay) {
                ordersOverlay.hidden = true;
            }
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
                        store_id: storeId,
                        message: message,
                    }),
                });

                if (!response.ok) {
                    const data = await response.json();
                    botBubble.className = 'msg bot';
                    botBubble.textContent = 'Hata: ' + (data.message || 'Bilinmeyen hata');
                    return;
                }

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
