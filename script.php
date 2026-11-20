      </main>
      <footer class="foot">
        <span>SIPN &middot; SMK Sangkuriang 1 Cimahi</span>
        <span><?= date('d/m/Y') ?></span>
      </footer>
<?php if (isset($_SESSION['user_id'])): ?>
    </div><!-- /main-col -->
  </div><!-- /shell -->
<?php else: ?>
  </div><!-- /sheet -->
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script>
  // sidebar di HP: buka / tutup
  (function () {
    var s = document.getElementById('side'), b = document.getElementById('sideBtn'), k = document.getElementById('sideBack');
    if (!s || !b) return;
    function tog(buka) { s.classList.toggle('open', buka); k.classList.toggle('open', buka); document.body.classList.toggle('no-scroll', buka); }
    b.addEventListener('click', function () { tog(!s.classList.contains('open')); });
    k.addEventListener('click', function () { tog(false); });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') tog(false); });
  })();

  // grup menu: klik untuk buka/tutup; di desktop hanya satu terbuka dan klik di luar menutupnya
  (function () {
    var grups = [].slice.call(document.querySelectorAll('.grp'));
    function tutup(kecuali) {
      grups.forEach(function (g) {
        if (g !== kecuali) { g.classList.remove('buka'); g.querySelector('.grp-btn').setAttribute('aria-expanded', 'false'); }
      });
    }
    grups.forEach(function (g) {
      var b = g.querySelector('.grp-btn');
      b.addEventListener('click', function (ev) {
        ev.stopPropagation();
        var buka = !g.classList.contains('buka');
        if (window.matchMedia('(min-width: 992px)').matches) tutup(g);
        g.classList.toggle('buka', buka);
        b.setAttribute('aria-expanded', buka ? 'true' : 'false');
      });
    });
    document.addEventListener('click', function () { tutup(null); });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') tutup(null); });
  })();

  // beri label kolom pada tiap sel tabel (dipakai tampilan HP)
  document.querySelectorAll('.table-responsive > .table').forEach(function (t) {
    var h = [].map.call(t.querySelectorAll('thead th'), function (x) { return x.textContent.trim(); });
    t.querySelectorAll('tbody tr').forEach(function (tr) {
      var utama = false;
      [].forEach.call(tr.children, function (td, i) {
        td.setAttribute('data-label', h[i] || '');
        if (td.textContent.trim() === '-') td.classList.add('kosong');
        if (td.hasAttribute('colspan')) return;
        var lbl = (h[i] || '').toLowerCase();
        if (td.querySelector('.btn') && (lbl === 'aksi' || lbl === '')) { td.classList.add('cell-act'); return; }
        if (!utama && lbl !== 'no' && lbl !== '') { td.classList.add('cell-main'); utama = true; }
      });
    });
  });
</script>
  </body>
</html>
