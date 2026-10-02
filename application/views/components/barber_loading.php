<?php
/** Non-blocking, progressively enhanced loading feedback shared by the branded layouts. */
$fa = str_starts_with(strtolower((string) config('language_code')), 'fa');
?>
<div id="ea-page-loader" class="ea-page-loader" hidden role="status" aria-live="polite">
    <div class="ea-loader-studio">
        <span class="ea-loader-kicker">GROOMING · WITH YOUR SIGNATURE</span>
        <svg class="ea-loader-scissors" viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
            <g class="ea-scissor-blade"><circle cx="34" cy="88" r="13"/><path d="M42 78 88 20 63 65"/></g>
            <g class="ea-scissor-blade"><circle cx="86" cy="88" r="13"/><path d="M78 78 32 20 57 65"/></g>
            <circle cx="60" cy="61" r="4" fill="currentColor"/>
        </svg>
        <strong><?= $fa ? 'آماده برای یک تغییر خوب' : 'Ready for a fresh look' ?></strong>
        <span><?= $fa ? 'در حال آماده‌سازی تجربهٔ شما…' : 'Preparing your experience…' ?></span>
        <span class="ea-barber-pole" aria-hidden="true"></span>
    </div>
</div>
<div id="ea-request-loader" class="ea-request-loader" hidden role="status" aria-live="polite">
    <span class="ea-barber-pole" aria-hidden="true"></span>
    <span><?= $fa ? 'در حال دریافت اطلاعات…' : 'Updating your details…' ?></span>
</div>
<script src="<?= asset_url('assets/js/utils/barber-motion.js') ?>"></script>
