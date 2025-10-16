(function () {
    'use strict';

    const initGallery = () => {
        const gallery = document.querySelector('.estate-office-offer__gallery');

        if (!gallery) {
            return;
        }

        const mainImage = gallery.querySelector('[data-estate-office-offer-main]');
        const caption = gallery.querySelector('[data-estate-office-offer-caption]');
        const thumbnails = gallery.querySelectorAll('[data-estate-office-offer-thumb]');

        if (!mainImage || thumbnails.length === 0) {
            return;
        }

        thumbnails.forEach((thumbnail) => {
            thumbnail.addEventListener('click', (event) => {
                event.preventDefault();

                const target = thumbnail.getAttribute('data-full');
                const alt = thumbnail.getAttribute('data-alt') || '';
                const captionText = thumbnail.getAttribute('data-caption') || '';

                if (!target || mainImage.getAttribute('src') === target) {
                    return;
                }

                mainImage.setAttribute('src', target);
                mainImage.setAttribute('alt', alt);

                if (caption) {
                    caption.textContent = captionText;
                }

                thumbnails.forEach((item) => {
                    item.classList.remove('is-active');
                    item.setAttribute('aria-pressed', 'false');
                });

                thumbnail.classList.add('is-active');
                thumbnail.setAttribute('aria-pressed', 'true');
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGallery);
    } else {
        initGallery();
    }
})();
