<?php
/** Cabecera común: barra lateral (escritorio) / menú desplegable (celular), encabezado con usuario y fecha. */
require_once ROOT_DIR . '/includes/sidebar.php';
$u = current_user();
$pageTitle = $pageTitle ?? 'FerreControl';
$active = $active ?? '';
$dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · FerreControl</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="fc-layout">
  <aside class="fc-sidebar d-none d-lg-flex flex-column">
    <a class="fc-brand" href="<?= e(url('dashboard.php')) ?>"><i class="bi bi-tools"></i> FerreControl</a>
    <nav class="fc-menu"><?php render_menu($active); ?></nav>
  </aside>

  <div class="offcanvas offcanvas-start fc-offcanvas" tabindex="-1" id="fcMenu" aria-labelledby="fcMenuLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="fcMenuLabel"><i class="bi bi-tools"></i> FerreControl</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body p-0"><nav class="fc-menu"><?php render_menu($active); ?></nav></div>
  </div>

  <div class="fc-content">
    <header class="fc-topbar no-print">
      <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#fcMenu" aria-controls="fcMenu" aria-label="Abrir menú">
        <i class="bi bi-list"></i>
      </button>
      <div class="me-auto ms-2 ms-lg-0 d-none d-md-block text-muted small"><?= e($dias[(int)date('w')] . ' ' . date('d/m/Y')) ?></div>
      <div class="text-end lh-sm me-3">
        <div class="fw-semibold"><?= e($u['full_name'] ?? '') ?></div>
        <span class="badge fc-role"><?= e(role_label($u['role'] ?? '')) ?></span>
      </div>
      <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
        <?= csrf_field() ?>
        <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-box-arrow-right"></i> <span class="d-none d-sm-inline">Cerrar sesión</span></button>
      </form>
    </header>
    <main class="fc-main">
      <?= flash_render() ?>
