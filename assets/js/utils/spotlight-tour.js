/*
 * Lightweight, dependency-free spotlight tour.
 *
 * Supports progress/resume, keyboard navigation, focus trapping, responsive placement and
 * localStorage persistence. Tour copy and selectors live in assets/js/utils/tours/.
 */
(function (global) {
    'use strict';

    const STORAGE = {
        completed: 'tour-completed',
        step: 'tour-step',
        skipped: 'tour-skipped',
    };

    const rtl = document.documentElement.dir === 'rtl';
    const defaults = {
        later: rtl ? 'بعداً' : 'Later',
        skip: rtl ? 'رد کردن تور' : 'Skip tour',
        back: rtl ? 'قبلی' : 'Back',
        next: rtl ? 'بعدی' : 'Next',
        finish: rtl ? 'پایان' : 'Finish',
        close: rtl ? 'بستن راهنما' : 'Close guide',
        progress: rtl ? 'مرحله' : 'Step',
        of: rtl ? 'از' : 'of',
    };

    function readMap(key) {
        try {
            const value = global.localStorage.getItem(key);
            const parsed = value ? JSON.parse(value) : {};
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function writeMap(key, value) {
        try {
            global.localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {
            // Private browsing and storage quotas must not prevent a tour from working.
        }
    }

    function isVisible(element) {
        if (!element || !element.isConnected) return false;
        if (element.closest('[hidden], [aria-hidden="true"]')) return false;
        const style = global.getComputedStyle(element);
        return style.display !== 'none' && style.visibility !== 'hidden' && element.getClientRects().length > 0;
    }

    class SpotlightTour {
        constructor(options = {}) {
            this.id = options.id || 'default';
            this.steps = Array.isArray(options.steps) ? options.steps : [];
            this.showProgress = options.showProgress !== false;
            this.allowSkip = options.allowSkip !== false;
            this.keyboardNavigation = options.keyboardNavigation !== false;
            this.persistProgress = options.persistProgress !== false;
            this.labels = { ...defaults, ...(options.labels || {}) };
            this.onComplete = options.onComplete || null;
            this.onSkip = options.onSkip || null;
            this.active = false;
            this.index = 0;
            this.root = null;
            this.tooltip = null;
            this.ring = null;
            this.target = null;
            this.previousFocus = null;
            this.originalBodyOverflow = '';
            this.targetOriginalStyle = null;
            this.handlers = null;
        }

        start(options = {}) {
            if (this.active || !this.steps.length) return false;

            const completed = readMap(STORAGE.completed);
            const skipped = readMap(STORAGE.skipped);
            if (!options.force && (completed[this.id] || skipped[this.id])) return false;

            const saved = readMap(STORAGE.step);
            this.index = options.resume ? Math.min(Math.max(0, Number(saved[this.id] || 0)), this.steps.length - 1) : 0;
            this.previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            this.originalBodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            this.build();
            this.active = true;
            this.render();
            return true;
        }

        build() {
            document.getElementById('ea-tour-root')?.remove();

            const root = document.createElement('div');
            root.id = 'ea-tour-root';
            root.className = 'ea-tour-root';
            root.setAttribute('aria-live', 'polite');
            root.setAttribute('aria-atomic', 'true');

            const dimmers = ['top', 'right', 'bottom', 'left'].map((side) => {
                const panel = document.createElement('div');
                panel.className = `ea-tour-dimmer ea-tour-dimmer--${side}`;
                panel.setAttribute('aria-hidden', 'true');
                root.append(panel);
                return panel;
            });

            const ring = document.createElement('div');
            ring.className = 'ea-tour-ring';
            ring.setAttribute('aria-hidden', 'true');
            root.append(ring);

            const tooltip = document.createElement('section');
            tooltip.className = 'ea-tour-tooltip';
            tooltip.setAttribute('role', 'dialog');
            tooltip.setAttribute('aria-modal', 'true');
            tooltip.setAttribute('aria-labelledby', 'ea-tour-title');
            tooltip.setAttribute('aria-describedby', 'ea-tour-description');

            const progressLabel = document.createElement('div');
            progressLabel.className = 'ea-tour-tooltip__progress-label';
            progressLabel.setAttribute('data-tour-progress-label', '');

            const progressText = document.createElement('span');
            progressText.dataset.tourProgressText = '';
            progressLabel.append(progressText);

            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'ea-tour-tooltip__skip';
            closeButton.dataset.tourClose = '';
            closeButton.setAttribute('aria-label', this.labels.close);
            closeButton.textContent = '×';
            closeButton.addEventListener('click', () => this.pause());
            progressLabel.append(closeButton);
            tooltip.append(progressLabel);

            const progressTrack = document.createElement('div');
            progressTrack.className = 'ea-tour-tooltip__progress-track';
            progressTrack.setAttribute('role', 'progressbar');
            progressTrack.setAttribute('aria-label', this.labels.progress);
            progressTrack.setAttribute('aria-valuemin', '1');
            progressTrack.setAttribute('aria-valuemax', String(this.steps.length));
            const progressValue = document.createElement('span');
            progressValue.className = 'ea-tour-tooltip__progress-value';
            progressValue.dataset.tourProgressValue = '';
            progressTrack.append(progressValue);
            tooltip.append(progressTrack);

            const title = document.createElement('h2');
            title.id = 'ea-tour-title';
            title.className = 'ea-tour-tooltip__title';
            tooltip.append(title);

            const description = document.createElement('p');
            description.id = 'ea-tour-description';
            description.className = 'ea-tour-tooltip__description';
            tooltip.append(description);

            const actions = document.createElement('div');
            actions.className = 'ea-tour-tooltip__actions';

            const leftGroup = document.createElement('div');
            leftGroup.className = 'ea-tour-tooltip__group';
            const skip = document.createElement('button');
            skip.type = 'button';
            skip.className = 'ea-tour-tooltip__skip';
            skip.dataset.tourSkip = '';
            skip.textContent = this.labels.skip;
            skip.addEventListener('click', () => this.skip());
            if (this.allowSkip) leftGroup.append(skip);

            const rightGroup = document.createElement('div');
            rightGroup.className = 'ea-tour-tooltip__group';
            const later = document.createElement('button');
            later.type = 'button';
            later.className = 'ea-tour-tooltip__later';
            later.textContent = this.labels.later;
            later.addEventListener('click', () => this.pause());
            const back = document.createElement('button');
            back.type = 'button';
            back.className = 'ea-tour-tooltip__back';
            back.textContent = this.labels.back;
            back.addEventListener('click', () => this.previous());
            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'ea-tour-tooltip__next';
            next.addEventListener('click', () => this.next());
            rightGroup.append(later, back, next);

            actions.append(leftGroup, rightGroup);
            tooltip.append(actions);
            root.append(tooltip);
            document.body.append(root);

            this.root = root;
            this.tooltip = tooltip;
            this.ring = ring;
            this.dimmers = dimmers;
            this.progressText = progressText;
            this.progressValue = progressValue;
            this.progressTrack = progressTrack;
            this.title = title;
            this.description = description;
            this.backButton = back;
            this.nextButton = next;
            this.skipButton = skip;
            this.laterButton = later;

            this.handlers = {
                keydown: (event) => this.onKeydown(event),
                reposition: () => this.position(),
            };
            document.addEventListener('keydown', this.handlers.keydown, true);
            global.addEventListener('resize', this.handlers.reposition, { passive: true });
            global.addEventListener('scroll', this.handlers.reposition, { passive: true, capture: true });
        }

        render() {
            if (!this.active || !this.root) return;
            const step = this.steps[this.index];
            if (!step) return;

            this.restoreTarget();
            const candidate = typeof step.element === 'function' ? step.element() : step.element;
            this.target = typeof candidate === 'string' ? document.querySelector(candidate) : candidate;
            if (!isVisible(this.target)) this.target = null;

            if (this.target) {
                this.target.scrollIntoView({ block: 'center', inline: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
                this.targetOriginalStyle = {
                    position: this.target.style.position,
                    zIndex: this.target.style.zIndex,
                    hadClass: this.target.classList.contains('ea-tour-highlight'),
                };
                if (global.getComputedStyle(this.target).position === 'static') this.target.style.position = 'relative';
                this.target.style.zIndex = '9999';
                this.target.classList.add('ea-tour-highlight');
            }

            this.title.textContent = step.title || '';
            this.description.textContent = step.description || '';
            this.description.hidden = !step.description;
            this.progressText.textContent = this.showProgress
                ? `${this.labels.progress} ${this.index + 1} ${this.labels.of} ${this.steps.length}`
                : '';
            this.progressText.parentElement.hidden = !this.showProgress;
            this.progressTrack.hidden = !this.showProgress;
            this.progressValue.style.width = `${((this.index + 1) / this.steps.length) * 100}%`;
            this.progressTrack.setAttribute('aria-valuenow', String(this.index + 1));
            this.backButton.disabled = this.index === 0;
            this.backButton.hidden = this.index === 0;
            this.nextButton.textContent = this.index === this.steps.length - 1 ? this.labels.finish : this.labels.next;
            this.skipButton.hidden = !this.allowSkip;
            this.root.classList.toggle('ea-tour-root--centered', !this.target);

            if (this.persistProgress) {
                const saved = readMap(STORAGE.step);
                saved[this.id] = this.index;
                writeMap(STORAGE.step, saved);
            }

            if (typeof step.onShow === 'function') step.onShow(this);
            this.position();
            this.nextButton.focus({ preventScroll: true });
        }

        position() {
            if (!this.active || !this.tooltip || !this.root) return;
            const width = global.innerWidth;
            const height = global.innerHeight;
            const margin = 16;

            if (!this.target || !isVisible(this.target)) {
                this.target = null;
                this.ring.hidden = true;
                this.dimmers.forEach((panel) => {
                    panel.style.top = '0';
                    panel.style.right = '0';
                    panel.style.bottom = '0';
                    panel.style.left = '0';
                    panel.style.width = '100vw';
                    panel.style.height = '100vh';
                });
                this.tooltip.dataset.placement = 'center';
                this.tooltip.style.left = `${Math.max(margin, (width - this.tooltip.offsetWidth) / 2)}px`;
                this.tooltip.style.top = `${Math.max(margin, (height - this.tooltip.offsetHeight) / 2)}px`;
                return;
            }

            const rect = this.target.getBoundingClientRect();
            const pad = 8;
            const top = Math.max(0, rect.top - pad);
            const left = Math.max(0, rect.left - pad);
            const right = Math.min(width, rect.right + pad);
            const bottom = Math.min(height, rect.bottom + pad);
            this.ring.hidden = false;
            this.ring.style.top = `${top}px`;
            this.ring.style.left = `${left}px`;
            this.ring.style.width = `${Math.max(0, right - left)}px`;
            this.ring.style.height = `${Math.max(0, bottom - top)}px`;

            this.dimmers[0].style.cssText = `top:0;left:0;right:0;height:${top}px`;
            this.dimmers[1].style.cssText = `top:${top}px;left:${right}px;right:0;height:${bottom - top}px`;
            this.dimmers[2].style.cssText = `top:${bottom}px;left:0;right:0;bottom:0`;
            this.dimmers[3].style.cssText = `top:${top}px;left:0;width:${left}px;height:${bottom - top}px`;

            const tooltipRect = this.tooltip.getBoundingClientRect();
            const tooltipWidth = tooltipRect.width || Math.min(360, width - 32);
            const tooltipHeight = tooltipRect.height || 230;
            const requested = this.steps[this.index].position || 'bottom';
            let placement = requested;
            const spaces = {
                bottom: height - bottom - margin,
                top: top - margin,
                right: width - right - margin,
                left: left - margin,
            };
            const required = { top: tooltipHeight, bottom: tooltipHeight, left: tooltipWidth, right: tooltipWidth };

            if (spaces[placement] < required[placement]) {
                const opposite = { top: 'bottom', bottom: 'top', left: 'right', right: 'left' }[placement];
                if (spaces[opposite] >= required[opposite]) placement = opposite;
                else placement = spaces.bottom >= spaces.top ? 'bottom' : 'top';
            }

            let x = (left + right - tooltipWidth) / 2;
            let y = bottom + margin;
            if (placement === 'top') y = top - tooltipHeight - margin;
            if (placement === 'left') {
                x = left - tooltipWidth - margin;
                y = (top + bottom - tooltipHeight) / 2;
            }
            if (placement === 'right') {
                x = right + margin;
                y = (top + bottom - tooltipHeight) / 2;
            }
            x = Math.min(Math.max(margin, x), width - tooltipWidth - margin);
            y = Math.min(Math.max(margin, y), height - tooltipHeight - margin);

            this.tooltip.dataset.placement = placement;
            this.tooltip.style.left = `${x}px`;
            this.tooltip.style.top = `${y}px`;
        }

        next() {
            if (this.index >= this.steps.length - 1) {
                this.finish();
                return;
            }
            this.index += 1;
            this.render();
        }

        previous() {
            if (this.index <= 0) return;
            this.index -= 1;
            this.render();
        }

        pause() {
            if (!this.active) return;
            if (this.persistProgress) {
                const saved = readMap(STORAGE.step);
                saved[this.id] = this.index;
                writeMap(STORAGE.step, saved);
            }
            this.destroy();
        }

        skip() {
            const skipped = readMap(STORAGE.skipped);
            skipped[this.id] = true;
            writeMap(STORAGE.skipped, skipped);
            const saved = readMap(STORAGE.step);
            delete saved[this.id];
            writeMap(STORAGE.step, saved);
            if (typeof this.onSkip === 'function') this.onSkip();
            this.destroy();
        }

        finish() {
            const completed = readMap(STORAGE.completed);
            completed[this.id] = true;
            writeMap(STORAGE.completed, completed);
            const skipped = readMap(STORAGE.skipped);
            delete skipped[this.id];
            writeMap(STORAGE.skipped, skipped);
            const saved = readMap(STORAGE.step);
            delete saved[this.id];
            writeMap(STORAGE.step, saved);
            if (typeof this.onComplete === 'function') this.onComplete();
            this.destroy();
        }

        restoreTarget() {
            if (!this.target || !this.targetOriginalStyle) return;
            this.target.style.position = this.targetOriginalStyle.position;
            this.target.style.zIndex = this.targetOriginalStyle.zIndex;
            if (!this.targetOriginalStyle.hadClass) this.target.classList.remove('ea-tour-highlight');
            this.target = null;
            this.targetOriginalStyle = null;
        }

        onKeydown(event) {
            if (!this.active) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                this.pause();
                return;
            }

            if (this.keyboardNavigation && event.key === 'Enter') {
                if (event.target instanceof HTMLButtonElement) return;
                event.preventDefault();
                this.next();
                return;
            }
            const nextKey = rtl ? 'ArrowLeft' : 'ArrowRight';
            const previousKey = rtl ? 'ArrowRight' : 'ArrowLeft';
            if (this.keyboardNavigation && event.key === nextKey) {
                event.preventDefault();
                this.next();
                return;
            }
            if (this.keyboardNavigation && event.key === previousKey) {
                event.preventDefault();
                this.previous();
                return;
            }

            if (event.key === 'Tab' && this.tooltip) {
                const focusable = Array.from(this.tooltip.querySelectorAll('button:not([disabled]):not([hidden])'))
                    .filter((element) => !element.hidden && element.getClientRects().length > 0);
                if (!focusable.length) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && (document.activeElement === first || !this.tooltip.contains(document.activeElement))) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && (document.activeElement === last || !this.tooltip.contains(document.activeElement))) {
                    event.preventDefault();
                    first.focus();
                }
            }
        }

        destroy() {
            if (!this.active) return;
            this.active = false;
            this.restoreTarget();
            if (this.handlers) {
                document.removeEventListener('keydown', this.handlers.keydown, true);
                global.removeEventListener('resize', this.handlers.reposition);
                global.removeEventListener('scroll', this.handlers.reposition, true);
            }
            if (this.root) this.root.remove();
            this.root = null;
            this.tooltip = null;
            this.ring = null;
            document.body.style.overflow = this.originalBodyOverflow;
            if (this.previousFocus?.isConnected) this.previousFocus.focus({ preventScroll: true });
            this.previousFocus = null;
        }
    }

    global.SpotlightTour = SpotlightTour;
})(window);
