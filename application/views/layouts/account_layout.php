<!doctype html>
<html lang="<?= config('language_code') ?>" dir="<?= app_text_direction() ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#C9A961">
    <meta name="google" content="notranslate">

    <?php slot('meta'); ?>

    <title><?= vars('page_title') ?? lang('account') ?> | Easy!Appointments</title>

    <link rel="icon" type="image/x-icon" href="<?= asset_url('assets/img/favicon.ico') ?>">
    <link rel="icon" sizes="192x192" href="<?= asset_url('assets/img/logo.png') ?>">

    <link rel="stylesheet" type="text/css" href="<?= app_asset_url(
        'assets/css/themes/' . setting('theme', 'default') . '.css',
    ) ?>">
    <link rel="stylesheet" type="text/css" href="<?= app_asset_url('assets/css/general.css') ?>">
<?php if (is_rtl()): ?>
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/persian.css') ?>">
<?php endif; ?>
    <link rel="stylesheet" type="text/css" href="<?= app_asset_url('assets/css/brand.css') ?>">
    <!-- FORK: salon motion and accessible Jalali calendar styling. -->
    <link rel="stylesheet" href="<?= asset_url('assets/css/components/barber-motion.css') ?>">

    <?php slot('styles'); ?>
</head>
<body class="ea-luxury ea-account">
<?php /* FORK: shared progressively enhanced loading feedback. */ component('barber_loading'); ?>

<div class="d-flex align-items-center justify-content-center min-vh-100">
    <button type="button" class="ea-theme-toggle position-fixed top-0 end-0 m-3" data-ea-theme-toggle
            aria-label="<?= e(lang('theme')) ?>" title="<?= e(lang('theme')) ?>">
        <i class="fas fa-moon" aria-hidden="true"></i>
    </button>

    <div class="card w-100 shadow-sm min-vh-mobile" style="max-width: 500px;">
        <div class="card-body p-5">
            <?php slot('content'); ?>
        </div>

        <div class="card-footer text-center py-3">
            <small>
                Powered by
                <a href="https://easyappointments.org">Easy!Appointments</a>
            </small>
        </div>
    </div>

</div>

<script src="<?= asset_url('assets/vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@popperjs-core/popper.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/bootstrap/bootstrap.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment/moment.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment-timezone/moment-timezone-with-data.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/fontawesome.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/solid.min.js') ?>"></script>

<script src="<?= asset_url('assets/js/app.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/theme.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/date.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/jalali_date.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/jalali_picker.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/file.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/http.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/lang.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/message.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/string.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/url.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/validation.js') ?>"></script>
<?php if (setting('altcha_enabled') === '1'): ?>
<script src="<?= asset_url('assets/js/utils/altcha.js') ?>"></script>
<?php endif; ?>
<script src="<?= asset_url('assets/js/layouts/account_layout.js') ?>"></script>
<script src="<?= asset_url('assets/js/http/account_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/http/localization_http_client.js') ?>"></script>

<?php component('js_vars_script'); ?>
<?php component('js_lang_script'); ?>

<?php slot('scripts'); ?>

</body>
</html>
