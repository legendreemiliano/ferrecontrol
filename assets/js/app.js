/* FerreControl - comportamiento general */
document.addEventListener('DOMContentLoaded', function () {
  // Confirmación antes de acciones destructivas: <form data-confirm="..."> o <button data-confirm="...">
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
  document.addEventListener('click', function (e) {
    var el = e.target.closest ? e.target.closest('button[data-confirm]') : null;
    if (el && !el.form.hasAttribute('data-confirm') && !window.confirm(el.getAttribute('data-confirm'))) e.preventDefault();
  });
  // Los mensajes de éxito se cierran solos
  document.querySelectorAll('.fc-flash.alert-success').forEach(function (a) {
    setTimeout(function () { if (window.bootstrap) bootstrap.Alert.getOrCreateInstance(a).close(); }, 6000);
  });
  // Botón imprimir
  document.querySelectorAll('[data-print]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });
});

function fcMoney(n) {
  return '$ ' + Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
