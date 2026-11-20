/* Punto de venta: carrito en el navegador (solo vista previa). El servidor vuelve a calcular precios y totales. */
(function () {
  var cart = {};
  var $ = function (id) { return document.getElementById(id); };
  var search = $('pos-search'), results = $('pos-results'), tbody = $('pos-cart');
  if (!search) return;
  var timer = null, lastResults = [];

  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  function totals() {
    var sub = 0;
    Object.keys(cart).forEach(function (k) { sub += Math.round(cart[k].price * cart[k].qty * 100) / 100; });
    var d = Math.min(Math.max(parseFloat($('pos-discount').value) || 0, 0), 100);
    var da = Math.round(sub * d) / 100;
    return { sub: sub, disc: da, total: Math.round((sub - da) * 100) / 100 };
  }
  function render() {
    var keys = Object.keys(cart);
    if (!keys.length) {
      tbody.innerHTML = '<tr class="empty-row"><td colspan="5" class="text-center text-muted py-4">El carrito está vacío.</td></tr>';
    } else {
      tbody.innerHTML = keys.map(function (k) {
        var i = cart[k];
        return '<tr data-id="' + i.id + '"><td>' + esc(i.name) + '<div class="small text-muted">' + esc(i.code) + ' · stock: ' + i.stock + '</div></td>' +
          '<td class="text-end">' + fcMoney(i.price) + '</td>' +
          '<td><input type="number" class="form-control form-control-sm qty" min="1" max="' + i.stock + '" step="1" value="' + i.qty + '"></td>' +
          '<td class="text-end">' + fcMoney(i.price * i.qty) + '</td>' +
          '<td><button type="button" class="btn btn-sm btn-outline-danger rm"><i class="bi bi-x-lg"></i></button></td></tr>';
      }).join('');
    }
    updateTotals();
  }
  function updateTotals() {
    var t = totals();
    $('pos-subtotal').textContent = fcMoney(t.sub);
    $('pos-disc-amount').textContent = '- ' + fcMoney(t.disc);
    $('pos-total').textContent = fcMoney(t.total);
    var opt = $('pos-method').options[$('pos-method').selectedIndex];
    var isCash = opt && opt.dataset.cash === '1';
    var paying = $('pos-method').value !== '0';
    $('pos-received-wrap').style.display = isCash ? '' : 'none';
    $('pos-amount-wrap').style.display = paying ? '' : 'none';
    var due = parseFloat($('pos-pay-amount').value) > 0 ? Math.min(parseFloat($('pos-pay-amount').value), t.total) : t.total;
    var rec = parseFloat($('pos-received').value) || 0;
    $('pos-change').textContent = fcMoney(isCash && rec > due ? rec - due : 0);
  }
  function add(p) {
    if (p.stock <= 0) { alert('«' + p.name + '» no tiene stock disponible.'); return; }
    if (cart[p.id]) {
      if (cart[p.id].qty < p.stock) cart[p.id].qty++;
      else alert('No hay más stock de «' + p.name + '» (disponible: ' + p.stock + ').');
    } else {
      cart[p.id] = { id: p.id, code: p.code, name: p.name, price: p.price, stock: p.stock, qty: 1 };
    }
    results.style.display = 'none'; search.value = ''; render(); search.focus();
  }
  function doSearch() {
    var q = search.value.trim();
    if (!q) { results.style.display = 'none'; return; }
    fetch(FC_SEARCH_URL + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (rows) {
        lastResults = Array.isArray(rows) ? rows : [];
        if (!lastResults.length) { results.innerHTML = '<div class="list-group-item text-muted">Sin resultados</div>'; }
        else {
          results.innerHTML = lastResults.map(function (p, i) {
            return '<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between" data-i="' + i + '"><span>' + esc(p.name) +
              ' <small class="text-muted">' + esc(p.code) + '</small></span><span>' + fcMoney(p.price) + ' <small class="' + (p.stock > 0 ? 'text-muted' : 'text-danger') + '">stock ' + p.stock + '</small></span></button>';
          }).join('');
        }
        results.style.display = '';
      }).catch(function () { results.innerHTML = '<div class="list-group-item text-danger">Error al buscar</div>'; results.style.display = ''; });
  }
  search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(doSearch, 250); });
  search.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); if (lastResults.length && results.style.display !== 'none') add(lastResults[0]); else doSearch(); }
  });
  results.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-i]'); if (b) add(lastResults[parseInt(b.dataset.i, 10)]);
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('#pos-results') && e.target !== search) results.style.display = 'none'; });
  tbody.addEventListener('input', function (e) {
    if (!e.target.classList.contains('qty')) return;
    var id = e.target.closest('tr').dataset.id, item = cart[id];
    var n = parseInt(e.target.value, 10);
    if (!n || n < 1) n = 1;
    if (n > item.stock) { n = item.stock; alert('Stock disponible de «' + item.name + '»: ' + item.stock); }
    item.qty = n; e.target.value = n; updateTotals();
    e.target.closest('tr').children[3].textContent = fcMoney(item.price * item.qty);
  });
  tbody.addEventListener('click', function (e) {
    var b = e.target.closest('.rm'); if (!b) return;
    delete cart[b.closest('tr').dataset.id]; render();
  });
  ['pos-discount', 'pos-method', 'pos-pay-amount', 'pos-received'].forEach(function (id) { $(id).addEventListener('input', updateTotals); $(id).addEventListener('change', updateTotals); });
  $('pos-form').addEventListener('submit', function (e) {
    var keys = Object.keys(cart);
    if (!keys.length) { e.preventDefault(); alert('Agregá al menos un producto al carrito.'); return; }
    $('cart_json').value = JSON.stringify(keys.map(function (k) { return { p: cart[k].id, q: cart[k].qty }; }));
  });
  if (typeof FC_PREV_CART !== 'undefined') { FC_PREV_CART.forEach(function (i) { cart[i.id] = i; }); }
  render();
})();
