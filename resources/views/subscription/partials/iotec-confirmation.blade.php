@once
    <dialog
        id="pm-iotec-confirmation-modal"
        class="pm-iotec-confirmation-dialog"
        aria-labelledby="pm-iotec-confirmation-title"
    >
        <div class="pm-iotec-confirmation-card">
            <button
                type="button"
                id="pm-iotec-confirmation-close"
                class="pm-iotec-confirmation-close"
                aria-label="Close payment confirmation"
            >
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>

            <div
                id="pm-iotec-confirmation-icon"
                class="pm-iotec-confirmation-icon is-pending"
                aria-hidden="true"
            >
                <span class="pm-iotec-confirmation-spinner"></span>
            </div>

            <div class="pm-iotec-confirmation-copy">
                <p
                    id="pm-iotec-confirmation-eyebrow"
                    class="pm-iotec-confirmation-eyebrow"
                >
                    Mobile Money
                </p>

                <h2
                    id="pm-iotec-confirmation-title"
                    class="pm-iotec-confirmation-title"
                >
                    Payment request sent
                </h2>

                <p
                    id="pm-iotec-confirmation-message"
                    class="pm-iotec-confirmation-message"
                >
                    Approve the payment prompt on your phone to complete your subscription.
                </p>
            </div>

            <div
                id="pm-iotec-confirmation-details"
                class="pm-iotec-confirmation-details"
                hidden
            >
                <div
                    id="pm-iotec-transaction-row"
                    class="pm-iotec-confirmation-detail-row"
                    hidden
                >
                    <span>Transaction</span>
                    <strong id="pm-iotec-confirmation-transaction">-</strong>
                </div>

                <div
                    id="pm-iotec-reference-row"
                    class="pm-iotec-confirmation-detail-row"
                    hidden
                >
                    <span>Reference</span>
                    <strong id="pm-iotec-confirmation-reference">-</strong>
                </div>
            </div>

            <div
                id="pm-iotec-confirmation-status"
                class="pm-iotec-confirmation-status is-pending"
                role="status"
                aria-live="polite"
            >
                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                <span>Waiting for approval</span>
            </div>

            <div class="pm-iotec-confirmation-actions">
                <button
                    type="button"
                    id="pm-iotec-confirmation-primary"
                    class="pm-iotec-confirmation-primary"
                >
                    Close
                </button>
            </div>

            <p class="pm-iotec-confirmation-note">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                Never enter your Mobile Money PIN on My Digital Diary.
                Approve the payment only from the secure prompt on your phone.
            </p>
        </div>
    </dialog>

    <style>
        .pm-iotec-confirmation-dialog {
            width: min(92vw, 440px);
            max-width: 440px;
            padding: 0;
            border: 0;
            border-radius: 24px;
            background: transparent;
            overflow: visible;
        }

        .pm-iotec-confirmation-dialog::backdrop {
            background: rgba(15, 23, 42, .58);
            backdrop-filter: blur(4px);
        }

        .pm-iotec-confirmation-card {
            position: relative;
            overflow: hidden;
            padding: 30px 24px 22px;
            border: 1px solid rgba(226, 232, 240, .95);
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 26px 80px rgba(15, 23, 42, .28);
            text-align: center;
        }

        .pm-iotec-confirmation-close {
            position: absolute;
            top: 12px;
            right: 12px;
            display: grid;
            place-items: center;
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 999px;
            background: #f1f5f9;
            color: #64748b;
            cursor: pointer;
            transition: background .2s ease, color .2s ease;
        }

        .pm-iotec-confirmation-close:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .pm-iotec-confirmation-icon {
            display: grid;
            place-items: center;
            width: 72px;
            height: 72px;
            margin: 0 auto 17px;
            border-radius: 999px;
            font-size: 32px;
        }

        .pm-iotec-confirmation-icon.is-pending {
            background: #ecfdf5;
            color: #047857;
        }

        .pm-iotec-confirmation-icon.is-success {
            background: #dcfce7;
            color: #15803d;
        }

        .pm-iotec-confirmation-icon.is-failed {
            background: #fee2e2;
            color: #b91c1c;
        }

        .pm-iotec-confirmation-spinner {
            width: 31px;
            height: 31px;
            border: 3px solid #a7f3d0;
            border-top-color: #059669;
            border-radius: 999px;
            animation: pm-iotec-spin .8s linear infinite;
        }

        @keyframes pm-iotec-spin {
            to { transform: rotate(360deg); }
        }

        .pm-iotec-confirmation-eyebrow {
            margin: 0 0 5px;
            color: #059669;
            font-size: 11px;
            line-height: 1.4;
            font-weight: 800;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .pm-iotec-confirmation-title {
            margin: 0;
            color: #0f172a;
            font-size: 22px;
            line-height: 1.25;
            font-weight: 800;
        }

        .pm-iotec-confirmation-message {
            max-width: 350px;
            margin: 9px auto 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        .pm-iotec-confirmation-details {
            margin-top: 18px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #f8fafc;
        }

        .pm-iotec-confirmation-detail-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 11px 13px;
            color: #64748b;
            font-size: 12px;
            text-align: left;
        }

        .pm-iotec-confirmation-detail-row + .pm-iotec-confirmation-detail-row {
            border-top: 1px solid #e2e8f0;
        }

        .pm-iotec-confirmation-detail-row strong {
            color: #0f172a;
            font-weight: 800;
            overflow-wrap: anywhere;
            text-align: right;
        }

        .pm-iotec-confirmation-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
            padding: 11px 13px;
            border-radius: 13px;
            font-size: 13px;
            font-weight: 750;
        }

        .pm-iotec-confirmation-status.is-pending {
            background: #fffbeb;
            color: #92400e;
        }

        .pm-iotec-confirmation-status.is-success {
            background: #f0fdf4;
            color: #166534;
        }

        .pm-iotec-confirmation-status.is-failed {
            background: #fef2f2;
            color: #991b1b;
        }

        .pm-iotec-confirmation-actions {
            margin-top: 16px;
        }

        .pm-iotec-confirmation-primary {
            width: 100%;
            padding: 12px 16px;
            border: 0;
            border-radius: 13px;
            background: var(--brand-1, #00897B);
            color: #fff;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: opacity .2s ease, transform .2s ease;
        }

        .pm-iotec-confirmation-primary:hover {
            opacity: .94;
        }

        .pm-iotec-confirmation-primary:active {
            transform: translateY(1px);
        }

        .pm-iotec-confirmation-note {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 7px;
            margin: 14px 0 0;
            color: #94a3b8;
            font-size: 11px;
            line-height: 1.5;
        }

        .pm-iotec-confirmation-note i {
            margin-top: 2px;
            color: #10b981;
        }

        @media (max-width: 640px) {
            .pm-iotec-confirmation-dialog {
                width: calc(100vw - 20px);
                max-width: calc(100vw - 20px);
            }

            .pm-iotec-confirmation-card {
                padding: 27px 18px 19px;
                border-radius: 20px;
            }

            .pm-iotec-confirmation-title {
                font-size: 20px;
            }
        }
    </style>

    <script>
        (function () {
            'use strict';

            var routeAction = @json(route('subscription.pay.iotec'));
            var statusTemplate = @json(
                route('subscription.pay.iotec.status', ['transaction' => '__TRANSACTION__'])
            );

            var pollTimer = null;
            var pollStartedAt = null;
            var lastTransactionId = null;
            var lastResultState = 'pending';

            function el(id) {
                return document.getElementById(id);
            }

            function modal() {
                return el('pm-iotec-confirmation-modal');
            }

            function showModal() {
                var dialog = modal();
                if (!dialog) return;

                if (typeof dialog.showModal === 'function') {
                    if (!dialog.open) dialog.showModal();
                    return;
                }

                dialog.setAttribute('open', 'open');
            }

            function closeModal() {
                stopPolling();

                var dialog = modal();
                if (!dialog) return;

                if (typeof dialog.close === 'function' && dialog.open) {
                    dialog.close();
                } else {
                    dialog.removeAttribute('open');
                }
            }

            function stopPolling() {
                if (pollTimer) {
                    window.clearTimeout(pollTimer);
                    pollTimer = null;
                }
            }

            function setIcon(state) {
                var icon = el('pm-iotec-confirmation-icon');
                if (!icon) return;

                icon.className = 'pm-iotec-confirmation-icon is-' + state;

                if (state === 'success') {
                    icon.innerHTML =
                        '<i class="fa-solid fa-check" aria-hidden="true"></i>';
                } else if (state === 'failed') {
                    icon.innerHTML =
                        '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
                } else {
                    icon.innerHTML =
                        '<span class="pm-iotec-confirmation-spinner"></span>';
                }
            }

            function setStatus(state, text) {
                var box = el('pm-iotec-confirmation-status');
                if (!box) return;

                box.className =
                    'pm-iotec-confirmation-status is-' + state;

                var icon = state === 'success'
                    ? 'fa-circle-check'
                    : (state === 'failed'
                        ? 'fa-circle-exclamation'
                        : 'fa-clock');

                box.innerHTML =
                    '<i class="fa-solid ' + icon + '" aria-hidden="true"></i>'
                    + '<span></span>';

                var span = box.querySelector('span');
                if (span) span.textContent = text;
            }

            function setDetails(transactionId, reference) {
                var details = el('pm-iotec-confirmation-details');
                var transactionRow = el('pm-iotec-transaction-row');
                var referenceRow = el('pm-iotec-reference-row');

                var hasTransaction =
                    transactionId !== null
                    && transactionId !== undefined
                    && String(transactionId).trim() !== '';

                var hasReference =
                    reference !== null
                    && reference !== undefined
                    && String(reference).trim() !== '';

                if (transactionRow) {
                    transactionRow.hidden = !hasTransaction;

                    if (hasTransaction) {
                        el('pm-iotec-confirmation-transaction').textContent =
                            '#' + String(transactionId);
                    }
                }

                if (referenceRow) {
                    referenceRow.hidden = !hasReference;

                    if (hasReference) {
                        el('pm-iotec-confirmation-reference').textContent =
                            String(reference);
                    }
                }

                if (details) {
                    details.hidden = !(hasTransaction || hasReference);
                }
            }

            function pendingState(data) {
                lastResultState = 'pending';
                setIcon('pending');

                el('pm-iotec-confirmation-eyebrow').textContent =
                    'Mobile Money';

                el('pm-iotec-confirmation-title').textContent =
                    'Payment request sent';

                el('pm-iotec-confirmation-message').textContent =
                    data.message
                    || 'Approve the payment prompt on your phone to complete your subscription.';

                setDetails(
                    data.transaction_id
                    || data.transactionId
                    || data.id
                    || null,
                    data.reference
                    || data.external_reference
                    || data.externalReference
                    || null
                );

                setStatus('pending', 'Waiting for approval');

                var primary = el('pm-iotec-confirmation-primary');
                if (primary) primary.textContent = 'Close';

                showModal();
            }

            function successState(data) {
                lastResultState = 'success';
                stopPolling();
                setIcon('success');

                el('pm-iotec-confirmation-eyebrow').textContent =
                    'Payment confirmed';

                el('pm-iotec-confirmation-title').textContent =
                    'Subscription payment successful';

                el('pm-iotec-confirmation-message').textContent =
                    data.message
                    || 'Your payment has been confirmed and your subscription is being updated.';

                setDetails(
                    data.transaction_id
                    || data.transactionId
                    || lastTransactionId
                    || null,
                    data.reference
                    || data.external_reference
                    || data.externalReference
                    || null
                );

                setStatus('success', 'Payment confirmed');

                var primary = el('pm-iotec-confirmation-primary');
                if (primary) primary.textContent = 'Continue';

                showModal();
            }

            function failedState(data) {
                lastResultState = 'failed';
                stopPolling();
                setIcon('failed');

                el('pm-iotec-confirmation-eyebrow').textContent =
                    'Payment status';

                el('pm-iotec-confirmation-title').textContent =
                    'Payment not completed';

                el('pm-iotec-confirmation-message').textContent =
                    data.message
                    || data.error
                    || 'The payment could not be completed. Please try again.';

                setDetails(
                    data.transaction_id
                    || data.transactionId
                    || lastTransactionId
                    || null,
                    data.reference
                    || data.external_reference
                    || data.externalReference
                    || null
                );

                setStatus('failed', 'Payment not completed');

                var primary = el('pm-iotec-confirmation-primary');
                if (primary) primary.textContent = 'Close';

                showModal();
            }

            function normaliseStatus(data) {
                return String(
                    data.status
                    || data.payment_status
                    || data.transaction_status
                    || data.state
                    || ''
                ).trim().toLowerCase();
            }

            function isSuccessStatus(status) {
                return [
                    'success',
                    'successful',
                    'completed',
                    'complete',
                    'paid',
                    'approved'
                ].indexOf(status) !== -1;
            }

            function isFailedStatus(status) {
                return [
                    'failed',
                    'failure',
                    'declined',
                    'rejected',
                    'cancelled',
                    'canceled',
                    'expired',
                    'error'
                ].indexOf(status) !== -1;
            }

            async function readJson(response) {
                var contentType =
                    response.headers.get('content-type') || '';

                if (contentType.indexOf('application/json') !== -1) {
                    return await response.json();
                }

                var text = await response.text();

                try {
                    return JSON.parse(text);
                } catch (error) {
                    return {
                        message: text || 'The server returned an unexpected response.'
                    };
                }
            }

            async function pollStatus(transactionId) {
                if (!transactionId) return;

                stopPolling();

                if (!pollStartedAt) {
                    pollStartedAt = Date.now();
                }

                // Stop automatic polling after five minutes. The user can
                // safely reload the billing history after that.
                if ((Date.now() - pollStartedAt) > 300000) {
                    setStatus(
                        'pending',
                        'Still waiting for confirmation'
                    );

                    el('pm-iotec-confirmation-message').textContent =
                        'The payment is still pending. You can close this message and check your billing history shortly.';

                    return;
                }

                try {
                    var url = statusTemplate.replace(
                        '__TRANSACTION__',
                        encodeURIComponent(String(transactionId))
                    );

                    var response = await fetch(url, {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    var data = await readJson(response);

                    if (!response.ok) {
                        // A temporary status-check error must not turn an
                        // already initiated payment into a failed payment.
                        pollTimer = window.setTimeout(function () {
                            pollStatus(transactionId);
                        }, 5000);
                        return;
                    }

                    var status = normaliseStatus(data);

                    if (isSuccessStatus(status)) {
                        successState(data);
                        return;
                    }

                    if (isFailedStatus(status)) {
                        failedState(data);
                        return;
                    }

                    pollTimer = window.setTimeout(function () {
                        pollStatus(transactionId);
                    }, 4000);
                } catch (error) {
                    pollTimer = window.setTimeout(function () {
                        pollStatus(transactionId);
                    }, 5000);
                }
            }

            function findChannel(form) {
                var selected = form.querySelector(
                    'input[name="payment_channel"]:checked'
                );

                if (selected) return selected.value;

                var fixed = form.querySelector(
                    'input[name="payment_channel"][type="hidden"]'
                );

                return fixed ? fixed.value : '';
            }

            function setSubmitting(form, submitting) {
                var button = form.querySelector(
                    'button[type="submit"]'
                );

                if (!button) return;

                if (submitting) {
                    if (!button.dataset.pmOriginalHtml) {
                        button.dataset.pmOriginalHtml = button.innerHTML;
                    }

                    button.disabled = true;
                    button.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>'
                        + '<span>Sending payment request...</span>';
                    return;
                }

                button.disabled = false;

                if (button.dataset.pmOriginalHtml) {
                    button.innerHTML = button.dataset.pmOriginalHtml;
                    delete button.dataset.pmOriginalHtml;
                }
            }

            document.addEventListener('submit', async function (event) {
                var form = event.target;

                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                var action = form.getAttribute('action') || '';

                // Only handle the ioTec subscription endpoint.
                if (
                    action !== routeAction
                    && !action.endsWith('/subscription/pay/iotec')
                ) {
                    return;
                }

                var channel = findChannel(form);

                // Card payments intentionally keep normal browser submission
                // because Laravel redirects the customer to ioTec's hosted
                // Visa/MasterCard checkout.
                if (channel === 'card') {
                    return;
                }

                if (channel !== 'mobile_money') {
                    return;
                }

                event.preventDefault();

                stopPolling();
                pollStartedAt = null;
                lastTransactionId = null;

                setSubmitting(form, true);

                try {
                    var response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        credentials: 'same-origin',
                        redirect: 'follow',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    var data = await readJson(response);

                    if (!response.ok) {
                        failedState({
                            message:
                                data.message
                                || data.error
                                || 'Could not start the ioTec payment. Please try again.'
                        });
                        return;
                    }

                    var status = normaliseStatus(data);
                    lastTransactionId =
                        data.transaction_id
                        || data.transactionId
                        || data.id
                        || null;

                    if (isSuccessStatus(status)) {
                        successState(data);
                        return;
                    }

                    if (isFailedStatus(status)) {
                        failedState(data);
                        return;
                    }

                    pendingState(data);

                    if (lastTransactionId) {
                        pollStatus(lastTransactionId);
                    }
                } catch (error) {
                    failedState({
                        message:
                            'We could not send the payment request. Please check your connection and try again.'
                    });
                } finally {
                    setSubmitting(form, false);
                }
            });

            document.addEventListener('DOMContentLoaded', function () {
                var closeButton =
                    el('pm-iotec-confirmation-close');

                var primaryButton =
                    el('pm-iotec-confirmation-primary');

                if (closeButton) {
                    closeButton.addEventListener(
                        'click',
                        closeModal
                    );
                }

                if (primaryButton) {
                    primaryButton.addEventListener(
                        'click',
                        function () {
                            var shouldReload =
                                lastResultState === 'success';

                            closeModal();

                            if (shouldReload) {
                                window.location.reload();
                            }
                        }
                    );
                }

                var dialog = modal();

                if (dialog) {
                    dialog.addEventListener('cancel', function () {
                        stopPolling();
                    });
                }
            });
        })();
    </script>
@endonce
