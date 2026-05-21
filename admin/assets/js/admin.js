(function () {
    'use strict';

    function formatUsd(amount) {
        return '$' + Number(amount || 0).toFixed(4);
    }

    function estimateTokens(text) {
        return Math.max(1, Math.ceil(String(text || '').length / 4));
    }

    function updateCostBox() {
        if (typeof aiPublisherCostData === 'undefined') {
            return;
        }

        var input = document.getElementById('article_input');
        var box = document.getElementById('ai-publisher-cost-estimate');
        var checkbox = document.querySelector('input[name="generate_image"]');

        if (!input || !box) {
            return;
        }

        var inputTokens = estimateTokens(input.value);
        var outputTokens = Number(aiPublisherCostData.fallbackOutputTokens || 2500);
        var inputRate = Number(aiPublisherCostData.inputUsdPer1m || 0);
        var outputRate = Number(aiPublisherCostData.outputUsdPer1m || 0);
        var textCost = (inputTokens / 1000000 * inputRate) + (outputTokens / 1000000 * outputRate);
        var includeImage = checkbox ? checkbox.checked : false;
        var imageSize = String(aiPublisherCostData.imageSize || '');
        var imageCosts = aiPublisherCostData.imageCosts || {};
        var imageCost = includeImage ? Number(imageCosts[imageSize] || 0) : 0;
        var totalCost = textCost + imageCost;
        var labels = aiPublisherCostData.labels || {};

        if (!labels.estimatedCost || !labels.inputTokens || !labels.outputTokens || !labels.textCost || !labels.imageCost || !labels.totalCost) {
            return;
        }

        box.innerHTML = '' +
            '<strong>' + labels.estimatedCost + '</strong>' +
            '<ul>' +
                '<li>' + inputTokens + ' ' + labels.inputTokens + '</li>' +
                '<li>' + outputTokens + ' ' + labels.outputTokens + '</li>' +
                '<li>' + labels.textCost + ': ' + formatUsd(textCost) + '</li>' +
                '<li>' + labels.imageCost + ': ' + formatUsd(imageCost) + '</li>' +
                '<li><strong>' + labels.totalCost + ': ' + formatUsd(totalCost) + '</strong></li>' +
            '</ul>';
    }

    function getBusyText(form) {
        var fallback = 'Waiting for GPT response…';

        if (typeof aiPublisherCostData !== 'undefined' && aiPublisherCostData.labels && aiPublisherCostData.labels.busyMessage) {
            fallback = aiPublisherCostData.labels.busyMessage;
        }

        return form.getAttribute('data-ai-publisher-busy-message') || fallback;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = String(text || '');
        return div.innerHTML;
    }

    function createBusyOverlay(message) {
        var overlay = document.createElement('div');
        overlay.className = 'ai-publisher-busy-overlay';
        overlay.setAttribute('role', 'alert');
        overlay.setAttribute('aria-live', 'assertive');
        overlay.innerHTML = '' +
            '<div class="ai-publisher-busy-box">' +
                '<span class="ai-publisher-busy-spinner" aria-hidden="true"></span>' +
                '<strong>' + escapeHtml(message) + '</strong>' +
            '</div>';

        return overlay;
    }

    function setBusyState(form) {
        if (document.body.classList.contains('ai-publisher-is-busy')) {
            return;
        }

        document.body.classList.add('ai-publisher-is-busy');
        form.classList.add('ai-publisher-submitting');
        form.setAttribute('aria-busy', 'true');
        document.body.appendChild(createBusyOverlay(getBusyText(form)));

        var submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        submitButtons.forEach(function (button) {
            button.disabled = true;
        });
    }

    function initBusyForms() {
        var forms = document.querySelectorAll('form[data-ai-publisher-busy="1"]');

        forms.forEach(function (form) {
            form.addEventListener('submit', function () {
                if (!form.checkValidity || form.checkValidity()) {
                    setBusyState(form);
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('article_input');
        var checkbox = document.querySelector('input[name="generate_image"]');

        if (input) {
            input.addEventListener('input', updateCostBox);
        }

        if (checkbox) {
            checkbox.addEventListener('change', updateCostBox);
        }

        updateCostBox();
        initBusyForms();
    });
}());
