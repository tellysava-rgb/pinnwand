(function () {
    const hidden = document.getElementById('pinnwand-primary-image');
    const tagInput = document.getElementById('pinnwand-tags');
    const tagLiveBox = document.getElementById('pinnwand-tag-live-suggestions');
    const l10n = window.pinnwandFormL10n || {};
    const tagSuggestUrl = l10n.ajaxUrl || '';
    const tagSuggestNonce = l10n.ajaxNonce || '';
    const uploadInput = document.getElementById('pinnwand-upload-images');
    const selectedImagesInfo = document.getElementById('pinnwand-selected-images-info');

    if (uploadInput && selectedImagesInfo) {
        const maxImages = parseInt(uploadInput.getAttribute('data-max-images') || '0', 10);
        const existingImages = parseInt(uploadInput.getAttribute('data-existing-images') || '0', 10);
        const remainingSlots = Math.max(0, maxImages - existingImages);
        let bufferedFiles = null;

        function fileKey(file) {
            return [file.name, file.size, file.lastModified].join('__');
        }

        function updateSelectedInfo() {
            const count = uploadInput.files ? uploadInput.files.length : 0;
            if (count <= 0) {
                selectedImagesInfo.textContent = l10n.noImages || 'Keine neuen Bilder ausgewaehlt.';
                return;
            }

            const remainingAfterSelection = Math.max(0, remainingSlots - count);
            if (count === 1) {
                selectedImagesInfo.textContent = (l10n.oneImage || '1 neues Bild ausgewaehlt.') + ' (' + (l10n.remaining || 'verbleibend:') + ' ' + remainingAfterSelection + ')';
                return;
            }

            selectedImagesInfo.textContent = count + ' ' + (l10n.manyImages || 'neue Bilder ausgewaehlt.') + ' (' + (l10n.remaining || 'verbleibend:') + ' ' + remainingAfterSelection + ')';
        }

        if (remainingSlots <= 0) {
            uploadInput.disabled = true;
            selectedImagesInfo.textContent = l10n.maxReached || 'Maximale Bildanzahl erreicht. Bitte zuerst ein Bild entfernen.';
        }

        uploadInput.addEventListener('change', function () {
            const selected = uploadInput.files ? Array.from(uploadInput.files) : [];
            if (!selected.length) {
                updateSelectedInfo();
                return;
            }

            if (typeof DataTransfer === 'undefined') {
                updateSelectedInfo();
                return;
            }

            if (!bufferedFiles) {
                bufferedFiles = new DataTransfer();
            }

            const seen = new Set(Array.from(bufferedFiles.files).map(fileKey));
            selected.forEach(function (file) {
                if (bufferedFiles.files.length >= remainingSlots) {
                    return;
                }
                const key = fileKey(file);
                if (seen.has(key)) {
                    return;
                }
                bufferedFiles.items.add(file);
                seen.add(key);
            });

            uploadInput.files = bufferedFiles.files;
            updateSelectedInfo();
        });
    }

    const editMainImage = document.getElementById('pw-edit-main-image');
    const editThumbButtons = document.querySelectorAll('.pinnwand-edit-thumb-btn');
    const editPrevButton = document.getElementById('pw-edit-prev');
    const editNextButton = document.getElementById('pw-edit-next');

    function setEditActiveByIndex(index) {
        const count = editThumbButtons.length;
        if (!editMainImage || count <= 0) {
            return;
        }
        const normalized = ((index % count) + count) % count;
        const btn = editThumbButtons[normalized];
        const newMainSrc = btn.getAttribute('data-main-src') || '';
        const newThumbSrc = btn.getAttribute('data-thumb-src') || '';
        const newAttachmentId = btn.getAttribute('data-attachment-id') || '';
        if (!newMainSrc || !newAttachmentId) {
            return;
        }

        editMainImage.setAttribute('src', newMainSrc);
        editMainImage.setAttribute('data-main-src', newMainSrc);
        editMainImage.setAttribute('data-thumb-src', newThumbSrc);
        editMainImage.setAttribute('data-attachment-id', newAttachmentId);

        editThumbButtons.forEach(function (other) {
            other.classList.remove('is-active');
        });
        btn.classList.add('is-active');

        if (hidden) {
            hidden.value = newAttachmentId;
        }
    }

    function getEditActiveIndex() {
        let activeIndex = 0;
        editThumbButtons.forEach(function (btn, idx) {
            if (btn.classList.contains('is-active')) {
                activeIndex = idx;
            }
        });
        return activeIndex;
    }

    editThumbButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const index = Array.prototype.indexOf.call(editThumbButtons, btn);
            setEditActiveByIndex(index);
        });
    });

    if (editPrevButton) {
        editPrevButton.addEventListener('click', function () {
            setEditActiveByIndex(getEditActiveIndex() - 1);
        });
    }
    if (editNextButton) {
        editNextButton.addEventListener('click', function () {
            setEditActiveByIndex(getEditActiveIndex() + 1);
        });
    }
    if (editThumbButtons.length > 1) {
        document.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft') {
                setEditActiveByIndex(getEditActiveIndex() - 1);
            } else if (event.key === 'ArrowRight') {
                setEditActiveByIndex(getEditActiveIndex() + 1);
            }
        });
    }

    if (!tagInput || !tagLiveBox || !tagSuggestUrl) {
        return;
    }

    let tagSuggestTimer = null;

    function getTagParts() {
        return tagInput.value
            .split(',')
            .map(function (item) { return item.trim(); })
            .filter(function (item) { return item.length > 0; });
    }

    function getCurrentToken() {
        const raw = tagInput.value;
        const lastComma = raw.lastIndexOf(',');
        return (lastComma >= 0 ? raw.slice(lastComma + 1) : raw).trim();
    }

    function applySuggestion(tagValue) {
        const raw = tagInput.value;
        const lastComma = raw.lastIndexOf(',');
        const baseRaw = lastComma >= 0 ? raw.slice(0, lastComma) : '';
        const baseParts = baseRaw
            .split(',')
            .map(function (item) { return item.trim(); })
            .filter(function (item) { return item.length > 0; });

        const normalized = tagValue.toLowerCase();
        const exists = baseParts.some(function (item) {
            return item.toLowerCase() === normalized;
        });
        if (!exists) {
            baseParts.push(tagValue);
        }

        tagInput.value = baseParts.join(', ') + ', ';
        tagLiveBox.innerHTML = '';
        tagLiveBox.hidden = true;
        tagInput.focus();
    }

    function renderLiveSuggestions(items) {
        if (!Array.isArray(items) || !items.length) {
            tagLiveBox.innerHTML = '';
            tagLiveBox.hidden = true;
            return;
        }

        const selected = getTagParts().map(function (item) { return item.toLowerCase(); });
        const filtered = items.filter(function (item) {
            return selected.indexOf(String(item).toLowerCase()) === -1;
        });
        if (!filtered.length) {
            tagLiveBox.innerHTML = '';
            tagLiveBox.hidden = true;
            return;
        }

        tagLiveBox.innerHTML = '';
        filtered.forEach(function (item) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pinnwand-tag-live-item';
            btn.textContent = String(item);
            btn.addEventListener('mousedown', function (event) {
                event.preventDefault();
                applySuggestion(String(item));
            });
            tagLiveBox.appendChild(btn);
        });
        tagLiveBox.hidden = false;
    }

    async function requestTagSuggestions(token) {
        try {
            const url = tagSuggestUrl + '?action=pinnwand_tag_suggestions&term=' + encodeURIComponent(token) + '&nonce=' + encodeURIComponent(tagSuggestNonce);
            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok) {
                renderLiveSuggestions([]);
                return;
            }
            const data = await response.json();
            const items = data && data.success && data.data && Array.isArray(data.data.items) ? data.data.items : [];
            renderLiveSuggestions(items);
        } catch (e) {
            renderLiveSuggestions([]);
        }
    }

    tagInput.addEventListener('input', function () {
        const token = getCurrentToken();
        if (token.length < 2) {
            renderLiveSuggestions([]);
            return;
        }
        if (tagSuggestTimer) {
            window.clearTimeout(tagSuggestTimer);
        }
        tagSuggestTimer = window.setTimeout(function () {
            requestTagSuggestions(token);
        }, 140);
    });

    tagInput.addEventListener('blur', function () {
        window.setTimeout(function () {
            tagLiveBox.hidden = true;
        }, 120);
    });

    tagLiveBox.addEventListener('mousedown', function (event) {
        event.preventDefault();
    });
})();
