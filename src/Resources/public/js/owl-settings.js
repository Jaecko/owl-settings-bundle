/**
 * OwlSettings - Client-side settings controller
 * Vanilla JS, zero dependencies.
 */
(function () {
    'use strict';

    var PREFIX = 'owl-settings';

    function OwlSettings(containerEl) {
        this.container = containerEl;
        this._bindTabs();
        this._bindThemePreview();
        this._bindFilePreview();
        this._bindFormSubmission();
        this._bindAutoSave();
    }

    // ===================================================================
    // Tab switching
    // ===================================================================

    OwlSettings.prototype._bindTabs = function () {
        var self = this;
        var tabs = this.container.querySelectorAll('[data-owl-settings-tab]');

        for (var i = 0; i < tabs.length; i++) {
            (function (tab) {
                tab.addEventListener('click', function () {
                    self._activateTab(tab.getAttribute('data-owl-settings-tab'));
                });
            })(tabs[i]);
        }
    };

    OwlSettings.prototype._activateTab = function (groupKey) {
        // Deactivate all tabs and panels
        var tabs = this.container.querySelectorAll('[data-owl-settings-tab]');
        var panels = this.container.querySelectorAll('[data-owl-settings-panel]');

        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove(PREFIX + '__tab--active');
        }
        for (var i = 0; i < panels.length; i++) {
            panels[i].classList.remove(PREFIX + '__panel--active');
        }

        // Activate the selected tab and panel
        var activeTab = this.container.querySelector('[data-owl-settings-tab="' + groupKey + '"]');
        var activePanel = this.container.querySelector('[data-owl-settings-panel="' + groupKey + '"]');

        if (activeTab) {
            activeTab.classList.add(PREFIX + '__tab--active');
        }
        if (activePanel) {
            activePanel.classList.add(PREFIX + '__panel--active');
        }
    };

    // ===================================================================
    // Live preview for theme selection
    // ===================================================================

    OwlSettings.prototype._bindThemePreview = function () {
        var themeField = this.container.querySelector('[data-owl-settings-field="theme"]');
        if (!themeField) return;

        var themeSelect = themeField.querySelector('select');
        if (!themeSelect) return;

        themeSelect.addEventListener('change', function () {
            var value = themeSelect.value;

            // Remove existing theme classes
            document.body.classList.remove('owl-theme-light', 'owl-theme-dark', 'owl-theme-auto');

            // Add new theme class
            document.body.classList.add('owl-theme-' + value);

            // Set data attribute for CSS media query support
            document.documentElement.setAttribute('data-owl-theme', value);
        });
    };

    // ===================================================================
    // File upload preview
    // ===================================================================

    OwlSettings.prototype._bindFilePreview = function () {
        var fileInputs = this.container.querySelectorAll('input[type="file"]');

        for (var i = 0; i < fileInputs.length; i++) {
            (function (input) {
                input.addEventListener('change', function () {
                    if (!input.files || !input.files[0]) return;

                    var file = input.files[0];
                    if (!file.type.startsWith('image/')) return;

                    var reader = new FileReader();
                    reader.onload = function (e) {
                        var wrapper = input.closest('[data-owl-settings-field]');
                        if (!wrapper) return;

                        var preview = wrapper.querySelector('[data-owl-settings-file-preview]');
                        if (!preview) {
                            preview = document.createElement('div');
                            preview.className = PREFIX + '__file-preview';
                            preview.setAttribute('data-owl-settings-file-preview', '');
                            input.parentNode.insertBefore(preview, input);
                        }

                        preview.innerHTML = '';
                        var img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Preview';
                        img.className = PREFIX + '__file-image';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                });
            })(fileInputs[i]);
        }
    };

    // ===================================================================
    // AJAX form submission
    // ===================================================================

    OwlSettings.prototype._bindFormSubmission = function () {
        var self = this;
        var forms = this.container.querySelectorAll('[data-owl-settings-form], [data-owl-settings-user-form]');

        for (var i = 0; i < forms.length; i++) {
            (function (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    self._submitForm(form);
                });
            })(forms[i]);
        }
    };

    OwlSettings.prototype._submitForm = function (form) {
        var self = this;
        var formData = new FormData(form);
        var statusEl = form.querySelector('[data-owl-settings-status]');
        var submitBtn = form.querySelector('button[type="submit"]');

        // Disable submit button during request
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        var url = form.action || window.location.href;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onload = function () {
            if (submitBtn) {
                submitBtn.disabled = false;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        self._showStatus(statusEl, 'success', 'Enregistr\u00e9 !');
                    } else {
                        self._showStatus(statusEl, 'error', response.error || 'Erreur lors de la sauvegarde.');
                    }
                } catch (e) {
                    self._showStatus(statusEl, 'success', 'Enregistr\u00e9 !');
                }
            } else {
                var errorMsg = 'Erreur lors de la sauvegarde.';
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMsg = response.error;
                    }
                } catch (e) {}
                self._showStatus(statusEl, 'error', errorMsg);
            }
        };

        xhr.onerror = function () {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            self._showStatus(statusEl, 'error', 'Erreur r\u00e9seau.');
        };

        xhr.send(formData);
    };

    OwlSettings.prototype._showStatus = function (el, type, message) {
        if (!el) return;

        el.textContent = message;
        el.className = PREFIX + '__status ' + PREFIX + '__status--' + type;

        // Auto-hide after 3 seconds
        clearTimeout(el._owlTimeout);
        el._owlTimeout = setTimeout(function () {
            el.textContent = '';
            el.className = PREFIX + '__status';
        }, 3000);
    };

    // ===================================================================
    // Auto-save with debounce (optional)
    // ===================================================================

    OwlSettings.prototype._bindAutoSave = function () {
        var self = this;
        var autoSaveForms = this.container.querySelectorAll('[data-owl-settings-autosave]');

        for (var f = 0; f < autoSaveForms.length; f++) {
            var form = autoSaveForms[f];
            var inputs = form.querySelectorAll('input, select, textarea');

            for (var i = 0; i < inputs.length; i++) {
                (function (input, parentForm) {
                    var timer;
                    var eventType = (input.type === 'checkbox' || input.tagName === 'SELECT')
                        ? 'change' : 'input';

                    input.addEventListener(eventType, function () {
                        clearTimeout(timer);
                        timer = setTimeout(function () {
                            self._submitForm(parentForm);
                        }, 800);
                    });
                })(inputs[i], form);
            }
        }
    };

    // ===================================================================
    // Auto-initialization
    // ===================================================================

    function init() {
        var containers = document.querySelectorAll('[data-owl-settings], [data-owl-settings-user-prefs]');

        for (var i = 0; i < containers.length; i++) {
            if (!containers[i]._owlSettingsInstance) {
                containers[i]._owlSettingsInstance = new OwlSettings(containers[i]);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose for manual re-initialization
    window.OwlSettings = {
        init: init,
        Controller: OwlSettings
    };

})();
