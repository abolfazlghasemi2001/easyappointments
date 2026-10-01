<!doctype html>
<html lang="<?= config('language_code') ?>" dir="<?= app_text_direction() ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#C9A961">
    <meta name="google" content="notranslate">
    <meta name="robots" content="noindex, nofollow">

    <?php slot('meta'); ?>

    <title><?= e(vars('page_title')) ?> | <?= e(vars('company_name')) ?></title>

    <link rel="icon" type="image/x-icon" href="<?= asset_url('assets/img/favicon.ico') ?>">
    <link rel="icon" sizes="192x192" href="<?= asset_url('assets/img/logo.png') ?>">

    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/fontawesome.min.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/solid.min.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= app_asset_url('assets/css/themes/' . vars('theme') . '.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= app_asset_url('assets/css/general.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= app_asset_url('assets/css/frontend.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= app_asset_url('assets/css/customer_portal.css') ?>">
<?php if (is_rtl()): ?>
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/persian.css') ?>">
<?php endif; ?>
    <link rel="stylesheet" type="text/css" href="<?= app_asset_url('assets/css/brand.css') ?>">

    <?php component('company_color_style', ['company_color' => vars('company_color')]); ?>

    <?php slot('styles'); ?>
</head>

<body class="ea-luxury ea-customer-portal">
<div id="main" class="container min-vh-100">
    <div class="row wrapper min-vh-100 justify-content-center align-items-start py-3">
        <div id="customer-portal" class="col-12 col-lg-10 col-xl-8 bg-body overflow-hidden p-0 my-auto">

            <?php component('booking_header', [
                'company_name' => vars('company_name'),
                'company_logo' => vars('company_logo'),
            ]); ?>

            <?php slot('content'); ?>

            <?php component('booking_footer', [
                'display_login_button' => vars('display_login_button'),
                'legal_notice_url' => vars('legal_notice_url'),
                'imprint_url' => vars('imprint_url'),
            ]); ?>

        </div>
    </div>
</div>

<script src="<?= asset_url('assets/vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@popperjs-core/popper.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/bootstrap/bootstrap.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment/moment.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment-timezone/moment-timezone-with-data.min.js') ?>"></script>

<script src="<?= asset_url('assets/js/app.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/theme.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/date.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/jalali_date.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/http.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/lang.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/message.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/string.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/url.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/validation.js') ?>"></script>

<script src="<?= asset_url('assets/js/http/customer_portal_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/customer_portal.js') ?>"></script>

<?php component('js_vars_script'); ?>
<?php component('js_lang_script'); ?>

<?php slot('scripts'); ?>

</body>
</html>
