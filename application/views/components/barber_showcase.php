<?php
/**
 * Public-facing barber experience. Prices, durations and team names come from the live Easy!Appointments records.
 * Do not add fictional testimonials or pretend portfolio photos when the installation has no review/gallery data.
 *
 * @var array $available_services
 * @var array $available_providers
 * @var string $company_name
 */
$locale = strtolower((string) config('language_code', 'en'));
$is_persian = str_starts_with($locale, 'fa');
$copy = $is_persian
    ? [
        'eyebrow' => lang('showcase_eyebrow'),
        'headline' => lang('showcase_headline'),
        'description' => lang('showcase_description'),
        'book' => lang('showcase_book'),
        'guide' => lang('showcase_guide'),
        'services_kicker' => lang('showcase_services_kicker'),
        'services_title' => lang('showcase_services_title'),
        'services_description' => lang('showcase_services_description'),
        'team_kicker' => lang('showcase_team_kicker'),
        'team_title' => lang('showcase_team_title'),
        'team_description' => lang('showcase_team_description'),
        'promises_kicker' => lang('showcase_promises_kicker'),
        'promises_title' => lang('showcase_promises_title'),
        'promise_one_title' => lang('showcase_promise_one_title'),
        'promise_one_text' => lang('showcase_promise_one_text'),
        'promise_two_title' => lang('showcase_promise_two_title'),
        'promise_two_text' => lang('showcase_promise_two_text'),
        'promise_three_title' => lang('showcase_promise_three_title'),
        'promise_three_text' => lang('showcase_promise_three_text'),
        'minutes' => lang('showcase_minutes'),
        'select_service' => lang('showcase_select_service'),
        'select_provider' => lang('showcase_select_provider'),
        'starting_price' => lang('showcase_starting_price'),
        'contact_for_price' => lang('showcase_ask_for_price'),
        'team_member' => lang('showcase_team_member'),
        'no_service_description' => lang('showcase_service_description_fallback'),
        'book_service' => lang('showcase_book_service'),
        'carousel_services' => lang('showcase_carousel_services'),
        'carousel_team' => lang('showcase_carousel_team'),
        'carousel_item_label' => lang('showcase_carousel_item_label'),
        'controls' => lang('showcase_controls'),
        'slides' => lang('showcase_slides'),
        'previous' => lang('previous'),
        'next' => lang('next'),
        'scroll' => lang('showcase_scroll'),
        'help_label' => lang('showcase_help_label'),
        'intro_title' => lang('showcase_intro_title'),
        'intro_description' => lang('showcase_intro_description'),
        'booking_guide' => lang('showcase_booking_guide'),
    ]
    : [
        'eyebrow' => 'Grooming, with your signature',
        'headline' => 'Make time for the details.',
        'description' => 'From choosing a service to confirming your visit, every step is clear. Pick a time that works for you — we will be ready.',
        'book' => 'Book an appointment',
        'guide' => 'Quick guide',
        'services_kicker' => 'Considered services',
        'services_title' => 'The details make the difference.',
        'services_description' => 'Choose a service and we will carry your selection into the booking flow.',
        'team_kicker' => 'Our team',
        'team_title' => 'Choose your barber.',
        'team_description' => 'Selecting a team member will filter the services available with them.',
        'promises_kicker' => 'Your experience',
        'promises_title' => 'Calm, considered, on time.',
        'promise_one_title' => 'Clear choices',
        'promise_one_text' => 'See the duration and price before you confirm your appointment.',
        'promise_two_title' => 'Your time, your choice',
        'promise_two_text' => 'Pick the date and time that suits you from the booking calendar.',
        'promise_three_title' => 'A simple booking',
        'promise_three_text' => 'Short, clear steps — with your appointment details reviewed before confirmation.',
        'minutes' => 'min',
        'select_service' => 'Select service',
        'select_provider' => 'Select barber',
        'starting_price' => 'Price',
        'contact_for_price' => 'Ask for price',
        'team_member' => 'Team member',
        'no_service_description' => 'Select this service to see the available details.',
        'book_service' => 'Book this service',
        'carousel_services' => 'Bookable services',
        'carousel_team' => 'Available barbers',
        'carousel_item_label' => 'Go to slide',
        'controls' => 'controls',
        'slides' => 'slides',
        'previous' => 'Previous',
        'next' => 'Next',
        'scroll' => 'Scroll to explore services',
        'help_label' => 'Open booking guide',
        'intro_title' => 'Your next visit starts here.',
        'intro_description' => 'Choose a service, barber and time.',
        'booking_guide' => 'Booking guide',
    ];

$icon_sprite = asset_url('assets/img/icons/sprite.svg');
?>
<section id="barber-hero" class="barber-hero" aria-labelledby="barber-hero-title">
    <div class="barber-hero__inner">
        <div class="barber-hero__copy" data-tour="onboarding-hero">
            <p class="barber-hero__eyebrow"><?= e($copy['eyebrow']) ?></p>
            <h1 id="barber-hero-title"><?= e($copy['headline']) ?></h1>
            <p class="barber-hero__description"><?= e($copy['description']) ?></p>

            <div class="barber-hero__actions">
                <a href="#booking-start" class="btn btn-primary booking-start-button" data-booking-start>
                    <svg class="ea-icon" aria-hidden="true"><use href="<?= e($icon_sprite) ?>#barber-shop-scissors"></use></svg>
                    <?= e($copy['book']) ?>
                </a>
                <button type="button" class="btn btn-outline-light" data-start-onboarding-tour aria-label="<?= e($copy['guide']) ?>">
                    <svg class="ea-icon" aria-hidden="true"><use href="<?= e($icon_sprite) ?>#navigation-help"></use></svg>
                    <?= e($copy['guide']) ?>
                </button>
            </div>

            <div class="barber-hero__meta" aria-label="<?= e($copy['eyebrow']) ?>">
                <span><svg class="ea-icon" aria-hidden="true"><use href="<?= e($icon_sprite) ?>#status-verified"></use></svg><?= e($copy['services_kicker']) ?></span>
                <span><svg class="ea-icon" aria-hidden="true"><use href="<?= e($icon_sprite) ?>#navigation-calendar"></use></svg><?= e($copy['team_kicker']) ?></span>
                <span><svg class="ea-icon" aria-hidden="true"><use href="<?= e($icon_sprite) ?>#status-confirmed"></use></svg><?= e($copy['book']) ?></span>
            </div>
        </div>
    </div>
    <a class="barber-scroll-cue" href="#barber-services" aria-label="<?= e($copy['scroll']) ?>">
        <svg class="ea-icon" aria-hidden="true"><use href="<?= e($icon_sprite) ?>#navigation-forward"></use></svg>
    </a>
</section>

<section id="barber-services" class="showcase-section" aria-labelledby="barber-services-title" data-tour="onboarding-services">
    <div class="showcase-section__inner">
        <div class="showcase-section__heading">
            <div>
                <span class="showcase-section__eyebrow"><?= e($copy['services_kicker']) ?></span>
                <h2 id="barber-services-title"><?= e($copy['services_title']) ?></h2>
                <p><?= e($copy['services_description']) ?></p>
            </div>
        </div>

        <div class="ea-carousel" data-carousel data-autoplay="5000" data-carousel-item-label="<?= e($copy['carousel_item_label']) ?>" tabindex="0" role="region" aria-label="<?= e($copy['carousel_services']) ?>">
            <div class="ea-carousel__track" data-carousel-track>
                <?php foreach ($available_services as $index => $service): ?>
                    <?php
                    $duration = (int) ($service['duration'] ?? 0);
                    $price = (float) ($service['price'] ?? 0);
                    $currency = trim((string) ($service['currency'] ?? ''));
                    $price_label = $price > 0
                        ? trim(localize_digits(number_format($price, 0)) . ' ' . $currency)
                        : $copy['contact_for_price'];
                    $service_label = trim((string) ($service['name'] ?? ''));
                    ?>
                    <article class="service-showcase-card">
                        <span class="service-showcase-card__icon" aria-hidden="true">
                            <svg class="ea-icon"><use href="<?= e($icon_sprite) ?>#barber-shop-<?= $index % 2 === 0 ? 'scissors' : 'razor' ?>"></use></svg>
                        </span>
                        <span class="showcase-section__eyebrow"><?= e((string) ($service['service_category_name'] ?? $copy['services_kicker'])) ?></span>
                        <h3><?= e($service_label) ?></h3>
                        <p><?= e(trim((string) ($service['description'] ?? '')) ?: $copy['no_service_description']) ?></p>
                        <span class="service-showcase-card__facts">
                            <span class="service-showcase-card__duration">
                                <svg class="ea-icon" aria-hidden="true"><use href="<?= e($icon_sprite) ?>#navigation-clock"></use></svg>
                                <span><?= e(localize_digits($duration)) ?> <?= e($copy['minutes']) ?></span>
                            </span>
                            <span class="service-showcase-card__price">
                                <span class="visually-hidden"><?= e($copy['starting_price']) ?>: </span><?= e($price_label) ?>
                            </span>
                        </span>
                        <button type="button" class="service-showcase-card__action service-shortcut" data-service-id="<?= (int) $service['id'] ?>"
                                aria-label="<?= e($copy['select_service'] . ': ' . $service_label) ?>">
                            <?= e($copy['book_service']) ?>
                            <svg class="ea-icon" aria-hidden="true"><use href="<?= e($icon_sprite) ?>#actions-check"></use></svg>
                        </button>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="ea-carousel__controls" aria-label="<?= e($copy['carousel_services'] . ' ' . $copy['controls']) ?>">
                <button type="button" class="ea-carousel__arrow" data-carousel-prev aria-label="<?= e($copy['previous']) ?>"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                <div class="ea-carousel__dots" data-carousel-dots aria-label="<?= e($copy['carousel_services'] . ' ' . $copy['slides']) ?>"></div>
                <button type="button" class="ea-carousel__arrow" data-carousel-next aria-label="<?= e($copy['next']) ?>"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
            </div>
        </div>
    </div>
</section>

<section id="barber-team" class="showcase-section showcase-section--paper" aria-labelledby="barber-team-title" data-tour="onboarding-team">
    <div class="showcase-section__inner">
        <div class="showcase-section__heading">
            <div>
                <span class="showcase-section__eyebrow"><?= e($copy['team_kicker']) ?></span>
                <h2 id="barber-team-title"><?= e($copy['team_title']) ?></h2>
                <p><?= e($copy['team_description']) ?></p>
            </div>
        </div>

        <div class="ea-carousel" data-carousel data-autoplay="5000" data-carousel-item-label="<?= e($copy['carousel_item_label']) ?>" tabindex="0" role="region" aria-label="<?= e($copy['carousel_team']) ?>">
            <div class="ea-carousel__track" data-carousel-track>
                <?php foreach ($available_providers as $provider): ?>
                    <?php
                    $first_name = trim((string) ($provider['first_name'] ?? ''));
                    $last_name = trim((string) ($provider['last_name'] ?? ''));
                    $provider_name = trim($first_name . ' ' . $last_name) ?: $copy['team_member'];
                    $initials = mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1);
                    $service_ids = array_map('intval', $provider['services'] ?? []);
                    ?>
                    <article class="provider-showcase-card provider-shortcut" data-provider-id="<?= (int) $provider['id'] ?>"
                             data-provider-services="<?= e(implode(',', $service_ids)) ?>">
                        <div class="provider-showcase-card__avatar" aria-hidden="true"><?= e($initials ?: '✂') ?></div>
                        <h3><?= e($provider_name) ?></h3>
                        <p><?= e($copy['team_member']) ?></p>
                        <button type="button" class="btn btn-outline-primary mt-4" data-provider-select="<?= (int) $provider['id'] ?>"
                                aria-label="<?= e($copy['select_provider'] . ': ' . $provider_name) ?>">
                            <?= e($copy['select_provider']) ?>
                        </button>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="ea-carousel__controls" aria-label="<?= e($copy['carousel_team'] . ' ' . $copy['controls']) ?>">
                <button type="button" class="ea-carousel__arrow" data-carousel-prev aria-label="<?= e($copy['previous']) ?>"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                <div class="ea-carousel__dots" data-carousel-dots aria-label="<?= e($copy['carousel_team'] . ' ' . $copy['slides']) ?>"></div>
                <button type="button" class="ea-carousel__arrow" data-carousel-next aria-label="<?= e($copy['next']) ?>"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
            </div>
        </div>
    </div>
</section>

<section class="showcase-section" aria-labelledby="barber-promises-title">
    <div class="showcase-section__inner">
        <div class="showcase-section__heading">
            <div>
                <span class="showcase-section__eyebrow"><?= e($copy['promises_kicker']) ?></span>
                <h2 id="barber-promises-title"><?= e($copy['promises_title']) ?></h2>
            </div>
        </div>
        <div class="row g-4">
            <?php foreach ([
                ['scissors', $copy['promise_one_title'], $copy['promise_one_text']],
                ['calendar', $copy['promise_two_title'], $copy['promise_two_text']],
                ['check', $copy['promise_three_title'], $copy['promise_three_text']],
            ] as [$icon, $title, $text]): ?>
                <div class="col-12 col-md-4">
                    <article class="promise-card h-100">
                        <span class="promise-card__icon" aria-hidden="true"><svg class="ea-icon"><use href="<?= e($icon_sprite) ?>#<?= $icon === 'scissors' ? 'barber-shop-scissors' : ($icon === 'calendar' ? 'navigation-calendar' : 'status-confirmed') ?>"></use></svg></span>
                        <h3><?= e($title) ?></h3>
                        <p><?= e($text) ?></p>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div id="booking-start" class="booking-intro-bar" data-tour="booking-start">
    <div>
        <span class="showcase-section__eyebrow"><?= e($copy['book']) ?></span>
        <h2><?= e($copy['intro_title']) ?></h2>
        <p><?= e($copy['intro_description']) ?></p>
    </div>
    <button type="button" class="btn btn-primary" data-start-booking-tour aria-label="<?= e($copy['help_label']) ?>">
        <i class="fas fa-circle-question me-2" aria-hidden="true"></i><?= e($copy['booking_guide']) ?>
    </button>
</div>
