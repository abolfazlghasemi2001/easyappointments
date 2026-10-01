/* Minimal, short-lived gold confetti for a successful booking. */
(function () {
    'use strict';

    const panel = document.getElementById('booking-success-panel');
    if (!panel || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;

    const canvas = document.createElement('canvas');
    canvas.className = 'ea-confetti';
    canvas.setAttribute('aria-hidden', 'true');
    document.body.append(canvas);
    const context = canvas.getContext('2d');
    if (!context) {
        canvas.remove();
        return;
    }

    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    const colors = ['#c9a961', '#e5c88a', '#8b6f47', '#f5f3ee'];
    const particles = [];
    const count = 56;
    let frame = 0;
    let startTime = 0;

    function resize() {
        canvas.width = Math.round(window.innerWidth * ratio);
        canvas.height = Math.round(window.innerHeight * ratio);
        canvas.style.width = `${window.innerWidth}px`;
        canvas.style.height = `${window.innerHeight}px`;
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
    }

    resize();
    window.addEventListener('resize', resize, { passive: true });

    for (let index = 0; index < count; index += 1) {
        particles.push({
            x: Math.random() * window.innerWidth,
            y: -Math.random() * window.innerHeight * 0.45,
            width: 4 + Math.random() * 5,
            height: 7 + Math.random() * 8,
            speed: 1.6 + Math.random() * 2.3,
            drift: (Math.random() - 0.5) * 1.4,
            rotation: Math.random() * Math.PI,
            spin: (Math.random() - 0.5) * 0.12,
            color: colors[index % colors.length],
            opacity: 1,
        });
    }

    function draw(time) {
        if (!startTime) startTime = time;
        const elapsed = time - startTime;
        context.clearRect(0, 0, window.innerWidth, window.innerHeight);

        particles.forEach((particle) => {
            particle.y += particle.speed;
            particle.x += particle.drift;
            particle.rotation += particle.spin;
            particle.opacity = Math.max(0, 1 - Math.max(0, elapsed - 1400) / 800);
            context.save();
            context.globalAlpha = particle.opacity;
            context.translate(particle.x, particle.y);
            context.rotate(particle.rotation);
            context.fillStyle = particle.color;
            context.fillRect(-particle.width / 2, -particle.height / 2, particle.width, particle.height);
            context.restore();
        });

        if (elapsed < 2200) frame = window.requestAnimationFrame(draw);
        else {
            window.cancelAnimationFrame(frame);
            window.removeEventListener('resize', resize);
            canvas.remove();
        }
    }

    frame = window.requestAnimationFrame(draw);
})();
