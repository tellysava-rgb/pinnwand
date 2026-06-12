(function () {
    const form = document.getElementById('pinnwand-search-form');
    const input = document.getElementById('pw-keyword');
    if (!form || !input) {
        return;
    }

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            form.submit();
        }
    });

    const keywordButtons = document.querySelectorAll('.pinnwand-keyword-link');
    keywordButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const keyword = button.getAttribute('data-tag') || '';
            input.value = keyword;
            form.submit();
        });
    });
})();
