(() => {
    document.addEventListener("DOMContentLoaded", () => {
        const API_BASE_URL = 'https://app.erad.co/eradpay';
        const data = eradpay_wc_payment_vars;
        const cancelUrl = data.cancel_url;
        const callbackUrl = data.callback_url;
        const token = data.token;
        const amount = data.amount;
        const currency = data.currency.toLowerCase();
        const payment_id = data.order_id;

        const cssClasses = {
            bodyModalOpened: 'eradpay-modal-open',
            modalWindowContentIframe: 'eradpay-modal-window__content-iframe',
        };

        const cssSelectors = {
            modalWindow: '.js-eradpay-modal-window',
            modalWindowContent: '.js-eradpay-modal-window-content',
            modalFader: '.js-eradpay-modal-fader',
            modalCloseControl: '.js-eradpay-modal-close-control',
            btnSubmit: '.js-eradpay-submit',
            btnCancel: '.js-eradpay-cancel',
        };

        const showModalWindow = (buttonEl) => {
            const modalTarget = "#" + buttonEl.getAttribute("data-target");
            const $modalFader = document.querySelector(cssSelectors.modalFader);
            const $modal = document.querySelector(modalTarget);
            const $modalContent = $modal.querySelector(cssSelectors.modalWindowContent);
            $modalFader.className += " active";
            $modal.className += " active";

            const queryStr = new URLSearchParams({
                token,
                amount,
                currency,
                payment_id,
                mode: data.mode,
                platform: 'wc',
                webhook_url: callbackUrl,
            }).toString();
            const iframeUrl = `${API_BASE_URL}?${queryStr}`;
            const $iframe = document.createElement('iframe');
            $iframe.src = iframeUrl;
            $iframe.className = cssClasses.modalWindowContentIframe;
            $modalContent.innerHTML = '';
            $modalContent.appendChild($iframe);

            document.body.classList.add(cssClasses.bodyModalOpened);
        }

        const hideAllModalWindows = () => {
            const $modalFader = document.querySelector(cssSelectors.modalFader);
            const $modalWindows = document.querySelectorAll(cssSelectors.modalWindow);

            if ($modalFader.className.indexOf("active") !== -1) {
                $modalFader.className = $modalFader.className.replace("active", "");
            }

            $modalWindows.forEach((modalWindow) => {
                if (modalWindow.className.indexOf("active") !== -1) {
                    modalWindow.className = modalWindow.className.replace("active", "");
                    document.body.classList.remove(cssClasses.bodyModalOpened);
                }
            });
        }

        const addEvent = (element, event, cb) => {
            if (element.attachEvent) {
                return element.attachEvent('on' + event, cb);
            } else return element.addEventListener(event, cb, false);
        }

        const onSubmitHandler = (e) => {
            e.preventDefault();
            hideAllModalWindows();
            showModalWindow(e.target);
        };

        const onCancelHandler = (e) => {
            e.preventDefault();
            window.location.href = cancelUrl;
        };

        const $submitBtn = document.querySelector(cssSelectors.btnSubmit);
        const $cancelBtn = document.querySelector(cssSelectors.btnCancel);
        const $modalCloseControl = document.querySelector(cssSelectors.modalCloseControl);

        addEvent($submitBtn, 'click', onSubmitHandler);
        addEvent($cancelBtn, 'click', onCancelHandler);
        addEvent($modalCloseControl, 'click', hideAllModalWindows);

        $submitBtn.click();
    });
})();
