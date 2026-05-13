/**
 * CMS Engine Event Tracker
 *
 * Tracks conversion events: phone reveals, form submissions, CTA clicks, WhatsApp
 * Include this script in your theme's base layout.
 *
 * Events tracked:
 * - phone_reveal: When user clicks to reveal phone number (with location)
 * - phone_click: When user clicks revealed phone to call (with location)
 * - whatsapp_click: When user clicks WhatsApp link (with location)
 * - cta_click: When user clicks quote/booking buttons
 * - email_click: When user clicks email link
 * - form_start: When user starts filling a form
 * - form_submit: When form is submitted
 */
(function() {
    'use strict';

    // Track event via API
    window.trackEvent = function(eventType, meta) {
        const data = {
            event: eventType,
            page: window.location.pathname,
            meta: meta || {}
        };

        // Use sendBeacon for reliability (works even on page unload)
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/track-event.php', JSON.stringify(data));
        } else {
            fetch('/api/track-event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                keepalive: true
            }).catch(function() {});
        }
    };

    // Phone reveal handler (used by phone_widget())
    window.revealPhone = function(element) {
        const fullNumber = element.dataset.phone;
        const displayNumber = element.dataset.display || fullNumber;
        const location = element.dataset.location || 'unknown';

        if (!fullNumber) return;

        // Track the reveal with location
        trackEvent('phone_reveal', {
            phone: fullNumber.replace(/\d{4}$/, '****'),
            location: location
        });

        // Update display with clickable link
        const linkClass = element.classList.contains('text-white')
            ? 'text-white font-bold underline'
            : 'text-primary-600 hover:text-primary-700 font-bold';
        element.innerHTML = '<a href="tel:' + fullNumber + '" class="' + linkClass + '">' + displayNumber + '</a>';
        element.onclick = null;
        element.classList.remove('cursor-pointer');

        // Track if they click to call
        element.querySelector('a').addEventListener('click', function() {
            trackEvent('phone_click', {
                phone: fullNumber.replace(/\d{4}$/, '****'),
                location: location
            });
        });
    };

    // Email reveal handler
    window.revealEmail = function(element) {
        const email = element.dataset.email;
        const location = element.dataset.location || 'unknown';

        if (!email) return;

        // Track the reveal
        trackEvent('email_reveal', {
            email: email.replace(/@.*/, '@***'),
            location: location
        });

        // Update display with clickable link
        const linkClass = element.classList.contains('text-white') || element.classList.contains('text-gray-300')
            ? 'text-white font-bold underline'
            : 'text-primary-600 hover:text-primary-700 font-bold';
        element.innerHTML = '<a href="mailto:' + email + '" class="' + linkClass + '">' + email + '</a>';
        element.onclick = null;
        element.classList.remove('cursor-pointer');

        // Track if they click to email
        element.querySelector('a').addEventListener('click', function() {
            trackEvent('email_click', {
                email: email.replace(/@.*/, '@***'),
                location: location
            });
        });
    };

    // Auto-track clicks
    document.addEventListener('click', function(e) {
        const target = e.target.closest('a, button');
        if (!target) return;

        const href = target.getAttribute('href') || '';
        const text = target.textContent.trim().toLowerCase();

        // Track quote/booking buttons
        if (href.includes('/book') || href.includes('/contact') ||
            href.includes('/quote') || text.includes('quote') || text.includes('book')) {
            trackEvent('cta_click', {
                text: target.textContent.trim().substring(0, 50),
                href: href
            });
        }

        // Track email clicks
        if (href.startsWith('mailto:')) {
            trackEvent('email_click', {
                email: href.replace('mailto:', '').split('?')[0]
            });
        }

        // Track WhatsApp clicks
        if (href.includes('wa.me') || href.includes('whatsapp.com')) {
            trackEvent('whatsapp_click', {
                location: target.dataset.location || 'unknown'
            });
        }
    });

    // Track form submissions
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || form.tagName !== 'FORM') return;

        trackEvent('form_submit', {
            action: form.action || window.location.pathname,
            id: form.id || null
        });
    });

    // Track form field focus (form_start - first interaction)
    let formStarted = {};
    document.addEventListener('focusin', function(e) {
        const form = e.target.closest('form');
        if (!form) return;

        const formId = form.id || form.action || 'unknown';
        if (formStarted[formId]) return;

        formStarted[formId] = true;
        trackEvent('form_start', {
            action: form.action || window.location.pathname,
            id: form.id || null
        });
    });

})();
