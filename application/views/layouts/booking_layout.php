<!doctype html>
<html lang="<?= config('language_code') ?>" dir="<?= app_text_direction() ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#C9A961">
    <link rel="manifest" href="<?= e(base_url('manifest.webmanifest')) ?>">
    <link rel="canonical" href="<?= e(base_url()) ?>">
    <link rel="preload" as="image" href="<?= asset_url('assets/img/barber-hero.jpg') ?>" fetchpriority="high">
    <meta name="google" content="notranslate">

    <meta name="description" content="<?= e(lang('page_title') . ' ' . vars('company_name')) ?> — رزرو آنلاین خدمات آرایشگاه مردانه، با انتخاب آرایشگر و زمان دلخواه.">
    <meta property="og:title" content="<?= lang('page_title') . ' ' . e(vars('company_name')) ?> | <?= e(vars('company_name')) ?>"/>
    <meta property="og:description" content="رزرو آنلاین خدمات آرایشگاه مردانه؛ خدمت، آرایشگر و زمان دلخواهتان را انتخاب کنید."/>
    <meta property="og:url" content="<?= e(base_url()) ?>">
    <meta property="og:image" content="<?= e(base_url('assets/img/barber-hero.jpg')) ?>"/>
    <meta property="og:type" content="website">

    <?php slot('meta'); ?>

    <title><?= lang('page_title') . ' ' . e(vars('company_name')) ?> | Easy!Appointments</title>

    <link rel="icon" type="image/x-icon" href="<?= asset_url('assets/img/favicon.ico') ?>">
    <link rel="icon" sizes="192x192" href="<?= asset_url('assets/img/logo.png') ?>">

    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/vendor/cookieconsent/cookieconsent.min.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/vendor/flatpickr/flatpickr.min.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/vendor/flatpickr/material_green.min.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= app_asset_url('assets/css/themes/' . vars('theme') . '.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= app_asset_url('assets/css/general.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= app_asset_url('assets/css/frontend.css') ?>">
<?php if (is_rtl()): ?>
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/persian.css') ?>">
<?php endif; ?>
    <link rel="stylesheet" type="text/css" href="<?= app_asset_url('assets/css/brand.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= app_asset_url('assets/css/components/spotlight.css') ?>">

    <?php component('company_color_style', ['company_color' => vars('company_color')]); ?>

    <?php slot('styles'); ?>
</head>

<body class="ea-luxury ea-booking">
<div id="main" class="container min-vh-100">
    <div class="row wrapper min-vh-100 justify-content-center align-items-center py-0 py-md-3">
        <div id="book-appointment-wizard" class="col-12 col-lg-10 col-xl-8 col-xxl-7 bg-body overflow-hidden p-0 my-auto">

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

<?php if (vars('display_cookie_notice') === '1'): ?>
    <?php component('cookie_notice_modal', ['cookie_notice_content' => vars('cookie_notice_content')]); ?>
<?php endif; ?>

<?php if (vars('display_terms_and_conditions') === '1'): ?>
    <?php component('terms_and_conditions_modal', [
        'terms_and_conditions_content' => vars('terms_and_conditions_content'),
    ]); ?>
<?php endif; ?>

<?php if (vars('display_privacy_policy') === '1'): ?>
    <?php component('privacy_policy_modal', ['privacy_policy_content' => vars('privacy_policy_content')]); ?>
<?php endif; ?>

<script src="<?= asset_url('assets/vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/cookieconsent/cookieconsent.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@popperjs-core/popper.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/bootstrap/bootstrap.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment/moment.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment-timezone/moment-timezone-with-data.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/fontawesome.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/solid.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/tippy.js/tippy-bundle.umd.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/flatpickr/flatpickr.min.js') ?>"></script>

<script src="<?= asset_url('assets/js/app.js') ?>"></script>
<script>window.EA_BASE_URL = <?= json_encode(rtrim(base_url(), '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= asset_url('assets/js/utils/theme.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/pwa.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/spotlight-tour.js') ?>"></script>
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
<script src="<?= asset_url('assets/js/layouts/booking_layout.js') ?>"></script>
<script src="<?= asset_url('assets/js/http/localization_http_client.js') ?>"></script>

<?php component('js_vars_script'); ?>
<?php component('js_lang_script'); ?>

<?php component('google_analytics_script', ['google_analytics_code' => vars('google_analytics_code')]); ?>
<?php component('matomo_analytics_script', [
    'matomo_analytics_url' => vars('matomo_analytics_url'),
    'matomo_analytics_site_id' => vars('matomo_analytics_site_id'),
]); ?>

<?php slot('scripts'); ?>

</body>
</html>
