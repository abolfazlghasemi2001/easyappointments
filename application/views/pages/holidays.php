<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="holidays-page">

    <div class="row">
        <div class="col-lg-3 mb-4">
            <?php component('settings_nav'); ?>
        </div>

        <div class="col-lg-9">
            <div class="row" id="holidays">

                <div id="filter-holidays" class="filter-records column col-12 mb-4">
                    <div class="btn-toolbar mb-3">
                        <button id="add-holiday" class="btn btn-primary">
                            <i class="fas fa-plus-square me-2"></i>
                            <?= lang('add_holiday') ?>
                        </button>

                        <button id="import-holidays" class="btn btn-outline-primary ms-2">
                            <i class="fas fa-file-import me-2"></i>
                            <?= lang('import_official_holidays') ?>
                        </button>
                    </div>

                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <?= lang('holidays_hint') ?>
                    </div>

                    <form class="input-append mb-4">
                        <div class="input-group">
                            <input type="text" class="key form-control" aria-label="keyword"
                                   placeholder="<?= lang('search') ?>">

                            <button class="filter btn btn-outline-secondary" type="submit"
                                    data-tippy-content="<?= lang('filter') ?>">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>

                    <h4 class="mb-3 fw-light">
                        <?= lang('holidays') ?>
                    </h4>

                    <div class="results overflow-auto" style="max-height: 650px;">
                        <!-- JS -->
                    </div>
                </div>

                <div class="record-details col-12 mb-4">
                    <div class="btn-toolbar mb-4">
                        <div class="add-edit-delete-group btn-group">
                            <button id="edit-holiday" class="btn btn-outline-secondary" disabled="disabled">
                                <i class="fas fa-edit me-2"></i>
                                <?= lang('edit') ?>
                            </button>
                        </div>

                        <div class="save-cancel-group" style="display:none;">
                            <button id="save-holiday" class="btn btn-primary">
                                <i class="fas fa-check-square me-2"></i>
                                <?= lang('save') ?>
                            </button>
                            <button id="cancel-holiday" class="btn btn-outline-secondary">
                                <?= lang('cancel') ?>
                            </button>
                            <button id="delete-holiday" class="btn btn-outline-danger ms-2">
                                <i class="fas fa-trash-alt me-2"></i>
                                <?= lang('delete') ?>
                            </button>
                        </div>
                    </div>

                    <h4 class="mb-3 fw-light">
                        <?= lang('details') ?>
                    </h4>

                    <div class="form-message alert" style="display:none;"></div>

                    <input type="hidden" id="id">

                    <div class="mb-3">
                        <label class="form-label" for="title">
                            <?= lang('title') ?>
                            <span class="text-danger" hidden>*</span>
                        </label>
                        <input id="title" class="form-control required" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="holiday-date">
                            <?= lang('date') ?>
                            <span class="text-danger" hidden>*</span>
                        </label>
                        <input id="holiday-date" class="form-control required" disabled>
                        <div class="form-text" id="holiday-date-hint"></div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is-recurring" disabled>
                            <label class="form-check-label" for="is-recurring">
                                <?= lang('recurring_annually') ?>
                            </label>
                        </div>
                        <div class="form-text">
                            <?= lang('recurring_annually_hint') ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">
                            <?= lang('description') ?>
                        </label>
                        <textarea id="description" class="form-control" rows="3" disabled></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/holidays_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/holidays.js') ?>"></script>

<?php end_section('scripts'); ?>
