<?php extend('layouts/customer_portal_layout'); ?>

<?php section('content'); ?>

<div class="customer-portal p-3 p-md-4">

    <!-- Login step -->

    <div id="customer-portal-login">
        <h4 class="text-center mb-3">
            <i class="fas fa-user-circle me-1"></i>
            <?= lang('customer_portal_login_heading') ?>
        </h4>

        <p class="text-center text-muted mb-4"><?= lang('customer_portal_login_hint') ?></p>

        <div class="alert alert-danger d-none" id="customer-portal-error" role="alert"></div>

        <form id="customer-portal-phone-form" class="mb-2" autocomplete="on">
            <div class="mb-3">
                <label class="form-label" for="customer-portal-phone">
                    <?= lang('customer_portal_phone_number') ?>
                </label>
                <input type="tel" class="form-control" id="customer-portal-phone"
                       inputmode="tel" dir="ltr" placeholder="09123456789"
                       value="<?= e(vars('customer')['phone_number'] ?? '') ?>" required>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="customer-portal-send-code">
                <i class="fas fa-comment-sms me-1"></i>
                <?= lang('customer_portal_send_code') ?>
            </button>
        </form>

        <form id="customer-portal-code-form" class="d-none mt-3">
            <div class="mb-3">
                <label class="form-label" for="customer-portal-code">
                    <?= lang('customer_portal_code') ?>
                </label>
                <input type="text" class="form-control text-center" id="customer-portal-code"
                       inputmode="numeric" autocomplete="one-time-code" maxlength="<?= (int) vars('otp_code_length') ?>"
                       dir="ltr" required>
                <div class="form-text" id="customer-portal-code-hint">
                    <?= str_replace(
                        ['{length}', '{minutes}'],
                        [(string) vars('otp_code_length'), (string) ceil((int) vars('otp_ttl_seconds') / 60)],
                        lang('customer_portal_code_hint'),
                    ) ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="customer-portal-verify">
                <i class="fas fa-right-to-bracket me-1"></i>
                <?= lang('customer_portal_verify') ?>
            </button>

            <div class="d-flex justify-content-between mt-3">
                <button type="button" class="btn btn-link p-0" id="customer-portal-resend-code">
                    <?= lang('customer_portal_resend_code') ?>
                </button>
                <button type="button" class="btn btn-link p-0" id="customer-portal-change-number">
                    <?= lang('customer_portal_change_number') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Appointments -->

    <div id="customer-portal-appointments" class="d-none">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">
                <i class="fas fa-calendar-check me-1"></i>
                <?= lang('customer_portal_my_appointments') ?>
            </h4>

            <div>
                <span class="text-muted me-3" dir="ltr" id="customer-portal-current-phone"></span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="customer-portal-logout">
                    <i class="fas fa-right-from-bracket me-1"></i>
                    <?= lang('customer_portal_logout') ?>
                </button>
            </div>
        </div>

        <div class="alert alert-danger d-none" id="customer-portal-appointments-error" role="alert"></div>

        <h6 class="text-muted mt-4"><?= lang('customer_portal_upcoming') ?></h6>
        <div id="customer-portal-upcoming" class="list-group mb-4">
            <div class="text-muted small"><?= lang('customer_portal_loading') ?></div>
        </div>

        <h6 class="text-muted"><?= lang('customer_portal_past') ?></h6>
        <div id="customer-portal-past" class="list-group mb-4">
            <div class="text-muted small"><?= lang('customer_portal_loading') ?></div>
        </div>

        <a href="<?= site_url('booking') ?>" class="btn btn-primary w-100">
            <i class="fas fa-plus me-1"></i>
            <?= lang('customer_portal_book_new') ?>
        </a>
    </div>
</div>

<!-- Appointment template (filled by the page script) -->

<template id="customer-portal-appointment-template">
    <div class="list-group-item">
        <div class="d-flex w-100 justify-content-between align-items-start">
            <div>
                <div class="fw-bold customer-portal-appointment-datetime"></div>
                <div class="text-muted small customer-portal-appointment-service"></div>
                <div class="text-muted small customer-portal-appointment-provider"></div>
            </div>
            <div class="text-end">
                <span class="badge text-bg-secondary customer-portal-appointment-status"></span>
                <button type="button"
                        class="btn btn-sm btn-outline-danger d-block mt-2 customer-portal-appointment-cancel">
                    <?= lang('customer_portal_cancel') ?>
                </button>
            </div>
        </div>
    </div>
</template>

<?php end_section('content'); ?>
