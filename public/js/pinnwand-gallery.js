(function () {
    const mainImage = document.getElementById('pw-main-image');
    const buttons = document.querySelectorAll('.pinnwand-thumb-btn');
    const prevButton = document.getElementById('pw-detail-prev');
    const nextButton = document.getElementById('pw-detail-next');
    if (!mainImage || !buttons.length) {
        return;
    }

    function setActiveByIndex(index) {
        const count = buttons.length;
        if (count <= 0) {
            return;
        }
        const normalized = ((index % count) + count) % count;
        const active = buttons[normalized];
        const src = active.getAttribute('data-main-src');
        if (!src) {
            return;
        }
        mainImage.setAttribute('src', src);
        buttons.forEach(function (other) {
            other.classList.remove('is-active');
        });
        active.classList.add('is-active');
    }

    function getActiveIndex() {
        let activeIndex = 0;
        buttons.forEach(function (btn, idx) {
            if (btn.classList.contains('is-active')) {
                activeIndex = idx;
            }
        });
        return activeIndex;
    }

    buttons.forEach(function (btn, idx) {
        btn.addEventListener('click', function () {
            setActiveByIndex(idx);
        });
    });

    if (prevButton) {
        prevButton.addEventListener('click', function () {
            setActiveByIndex(getActiveIndex() - 1);
        });
    }
    if (nextButton) {
        nextButton.addEventListener('click', function () {
            setActiveByIndex(getActiveIndex() + 1);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowLeft') {
            setActiveByIndex(getActiveIndex() - 1);
        } else if (event.key === 'ArrowRight') {
            setActiveByIndex(getActiveIndex() + 1);
        }
    });
})();
