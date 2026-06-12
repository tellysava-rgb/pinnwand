(function () {
    const checkbox = document.getElementById('pw-registration-captcha-enabled');
    const block = document.getElementById('pw-captcha-details');
    const copyButton = document.querySelector('.pw-copy-registration-link');
    const copiedText = (window.pinnwandSettingsL10n && window.pinnwandSettingsL10n.copied) ? window.pinnwandSettingsL10n.copied : 'Kopiert';
    const copyLinkText = (window.pinnwandSettingsL10n && window.pinnwandSettingsL10n.copyLink) ? window.pinnwandSettingsL10n.copyLink : 'Link kopieren';

    if (checkbox && block) {
        function toggleBlock() {
            block.style.display = checkbox.checked ? '' : 'none';
        }

        checkbox.addEventListener('change', toggleBlock);
        toggleBlock();
    }

    if (!copyButton) {
        return;
    }

    copyButton.addEventListener('click', async function () {
        const copyUrl = copyButton.getAttribute('data-copy-url') || '';
        if (!copyUrl) {
            return;
        }

        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(copyUrl);
            } else {
                const temp = document.createElement('textarea');
                temp.value = copyUrl;
                temp.setAttribute('readonly', 'readonly');
                temp.style.position = 'absolute';
                temp.style.left = '-9999px';
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
            }
            copyButton.textContent = copiedText;
            window.setTimeout(function () {
                copyButton.textContent = copyLinkText;
            }, 1400);
        } catch (e) {
            // Ignore clipboard errors.
        }
    });
})();
