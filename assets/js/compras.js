/* Formulario de compras: líneas dinámicas y vista previa de totales (el servidor recalcula todo). */
(function () {
  var tbody = document.querySelector('#lines tbody');
  if (!tbody) return;
  function optionsHtml() {
    var h = '<option value="">Elegir producto…</option>';
    FC_PRODUCTS.forEach(function (p) { h += '<option value="' + p.id + '" data-cost="' + p.cost + '">' + p.label.replace(/</g, '&lt;') + '</option>'; });
    return h;
  }
  function addLine(prev) {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><select class="form-select form-select-sm" name="item_product[]">' + optionsHtml() + '</select></td>' +
      '<td><input class="form-control form-control-sm" type="number" min="1" step="1" name="item_qty[]"></td>' +
      '<td><input class="form-control form-control-sm" type="number" min="0" step="0.01" name="item_cost[]"></td>' +
      '<td class="text-end line-sub">$ 0,00</td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger rm" title="Quitar"><i class="bi bi-trash"></i></button></td>';
    tbody.appendChild(tr);
    if (prev) {
      tr.querySelector('select').value = prev.p || '';
      tr.querySelector('[name="item_qty[]"]').value = prev.q;
      tr.querySelector('[name="item_cost[]"]').value = prev.c;
    }
  }
  function recalc() {
    var sub = 0;
    tbody.querySelectorAll('tr').forEach(function (tr) {
      var q = parseFloat(tr.querySelector('[name="item_qty[]"]').value) || 0;
      var c = parseFloat(tr.querySelector('[name="item_cost[]"]').value) || 0;
      var l = Math.round(q * c * 100) / 100;
      tr.querySelector('.line-sub').textContent = fcMoney(l);
      sub += l;
    });
    var d = parseFloat(document.getElementById('discount').value) || 0;
    var x = parseFloat(document.getElementById('extra').value) || 0;
    document.getElementById('t-sub').textContent = fcMoney(sub);
    document.getElementById('t-disc').textContent = '- ' + fcMoney(d);
    document.getElementById('t-extra').textContent = fcMoney(x);
    document.getElementById('t-total').textContent = fcMoney(sub - d + x);
  }
  document.getElementById('add-line').addEventListener('click', function () { addLine(); });
  tbody.addEventListener('click', function (e) {
    var b = e.target.closest('.rm');
    if (b) { b.closest('tr').remove(); recalc(); }
  });
  tbody.addEventListener('input', recalc);
  tbody.addEventListener('change', function (e) {
    if (e.target.name === 'item_product[]') {
      var o = e.target.options[e.target.selectedIndex];
      var cost = e.target.closest('tr').querySelector('[name="item_cost[]"]');
      if (o && o.dataset.cost && !cost.value && parseFloat(o.dataset.cost) > 0) cost.value = o.dataset.cost;
    }
    recalc();
  });
  ['discount', 'extra'].forEach(function (id) { document.getElementById(id).addEventListener('input', recalc); });
  var ps = document.getElementById('pstatus');
  function togglePm() { document.getElementById('pm-wrap').style.display = ps.value === 'pagada' ? '' : 'none'; }
  ps.addEventListener('change', togglePm); togglePm();
  if (typeof FC_PREV !== 'undefined' && FC_PREV.length) { FC_PREV.forEach(function (p) { addLine(p); }); }
  else { addLine(); addLine(); addLine(); }
  recalc();
})();
