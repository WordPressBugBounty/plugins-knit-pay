jQuery(document).ready(function($) {
    // Function to add missing payment gateways
    function addMissingPaymentGateways() {
        // Check for both classes to ensure we're targeting the correct container
        const gatewayContainer = $('.wptravel-onepage-payment-gateway .wptravel-onpage-radiobtn-handle');
        if (!gatewayContainer.length || typeof window._wp_travel_active_payment === 'undefined') {
            return;
        }

        // Get existing gateways
        const existingGateways = {};
        gatewayContainer.find('input[name="wp_travel_payment_gateway"]').each(function() {
            existingGateways[$(this).val()] = true;
        });

        // Add missing gateways
        Object.entries(window._wp_travel_active_payment).forEach(([gateway, label]) => {
            if (!existingGateways[gateway]) {
                const input = $('<input>', {
                    name: 'wp_travel_payment_gateway',
                    type: 'radio',
                    id: 'wp-travel-payment-gateway',
                    class: 'wp-travel-radio-group wp-travel-payment-field f-booking-with-payment f-partial-payment f-full-payment',
                    value: gateway,
                    'data-parsley-multiple': 'wp_travel_payment_gateway'
                });

                const labelElement = $('<label>', {
                    for: gateway,
                    text: label
                });

                gatewayContainer.append(input)
                    .append(' ')
                    .append(labelElement)
                    .append('<br>');
            }
        });
    }

    // Initial call
    addMissingPaymentGateways();

    // Optional: Watch for dynamic content changes (if the gateway container is loaded dynamically)
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                addMissingPaymentGateways();
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});
