<!doctype html>
<html lang="<?= service('request')->getLocale() ?>">

<head>
    <?= $viewMeta->render('meta') ?>

    <?= $viewMeta->render('title') ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <?= asset_link('auth/css/auth.css', 'css') ?>
    <?= asset_link('other/components/font-awesome/css/all.css', 'css') ?>
    <?= $this->renderSection('styles') ?>
    <?= $viewMeta->render('style') ?>
</head>

<body>

    <aside id="alerts-wrapper">
        {alerts}
    </aside>

    <header class="navbar navbar-light bg-none flex-md-nowrap p-0 shadow-sm">
        <a class="px-3 d-block fs-3 text-dark text-decoration-none col-md-3 col-lg-2 me-0" href="<?= site_url(ADMIN_AREA) ?>">
            <?= setting('Site.siteName') ?? 'Bonfire' ?>
        </a>
    </header>

    <div class="container-fluid main">
        <main class="ms-sm-auto px-md-4">
            <?= $this->renderSection('main') ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/alpinejs@3.14.8/dist/cdn.min.js" integrity="sha384-X9kJyAubVxnP0hcA+AMMs21U445qsnqhnUF8EBlEpP3a42Kh/JwWjlv2ZcvGfphb" crossorigin="anonymous"></script>
    <?= $this->renderSection('scripts') ?>
    <?= $viewMeta->render('script') ?>
</body>

</html>