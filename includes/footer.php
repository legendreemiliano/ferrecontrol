    </main>
    <footer class="fc-footer no-print">FerreControl · Trabajo práctico escolar · Los informes son estimaciones de gestión, no documentación fiscal.</footer>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($useCharts)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?php if (!empty($inlineJs)) echo '<script>' . $inlineJs . '</script>'; ?>
<?php if (!empty($extraJs)): foreach ((array)$extraJs as $js): ?>
<script src="<?= e(url($js)) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
