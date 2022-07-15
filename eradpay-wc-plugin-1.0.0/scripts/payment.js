(() => {
    console.log('==SCRIPT CONNECTED!');
    const data = eradpay_wc_payment_vars;
    console.log('=DTA:', data);
    console.log('$', $);

    const addEvent = (element, event, cb) => {
        if (element.attachEvent) {
            return element.attachEvent('on' + event, cb);
        } else return element.addEventListener(event, cb, false);
    }

    const onSubmitHandler = () => {
        // window.open('https://dev-app.erad.co/eradpay?amount=100&currency=usd&payment_id=1234567&mode=sandbox&token=0821b30e-0500-4bcb-b84a-9aa1c877c532&redirect_url=url', '_blank').focus();
        // console.log('=XXX', x);
        const iframeUrl = 'https://dev-app.erad.co/eradpay?amount=100&currency=usd&payment_id=1234567&mode=sandbox&token=0821b30e-0500-4bcb-b84a-9aa1c877c532&redirect_url=url';
        const win = window.open('');
        win.document.write('<iframe width="100%" height="100%" src="' + iframeUrl + '" frameborder="0"></iframe>')
        win.addEventListener('onhashchange', (event) => {
            // Log the state data to the console
            console.log('onhashchange', event.state);
        });
        win.addEventListener('onpopstate', (event) => {
            // Log the state data to the console
            console.log('onpopstate', event.state);
        });
        window.win = win;
    };

    const onCancelHandler = () => {
        window.location.href = data.cancel_url;
    };

    const $submitBtn = document.getElementById("btn-eradpay-submit");
    const $cancelBtn = document.getElementById("btn-eradpay-cancel");


    addEvent($submitBtn, 'click', onSubmitHandler);
    addEvent($cancelBtn, 'click', onCancelHandler);
})();
