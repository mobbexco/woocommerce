import { useEffect } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import {
  redirectToCheckout,
  openCheckoutModal,
  executeWallet,
} from "../actions";

/**
 * Wires the WooCommerce Blocks checkout events: validates wallet fields on
 * onPaymentSetup and dispatches to the wallet / redirect / modal flow on
 * onCheckoutSuccess.
 */
export function useMobbexCheckout({
  eventRegistration,
  emitResponse,
  selectionRef,
}) {
  const { onPaymentSetup, onCheckoutSuccess } = eventRegistration;
  const responseTypes = emitResponse?.responseTypes || {
    SUCCESS: "success",
    ERROR: "error",
  };

  // Validate wallet fields before placing the order.
  useEffect(() => {
    if (!onPaymentSetup) return;

    return onPaymentSetup(() => {
      const selected = selectionRef.current;

      if (selected.type === "card") {
        if (!selected.installment)
          return {
            type: responseTypes.ERROR,
            message: __(
              "Debe seleccionar las cuotas",
              "mobbex-for-woocommerce",
            ),
          };

        if (!selected.securityCode)
          return {
            type: responseTypes.ERROR,
            message: __(
              "Debe ingresar el código de seguridad",
              "mobbex-for-woocommerce",
            ),
          };
      }

      return { type: responseTypes.SUCCESS };
    });
  }, [onPaymentSetup]);

  // Handle the gateway result once the order is placed.
  useEffect(() => {
    return onCheckoutSuccess((response) => {
      const selected = selectionRef.current;
      const details = response?.processingResponse?.paymentDetails || {};

      // Wallet flow: process client-side (returns an error notice on failure).
      if (selected.type === "card")
        return executeWallet(details, selected, emitResponse);

      // Method / default flow: redirect or open the modal.
      const redirect = response.redirect || details.redirect;
      if (redirect) return redirectToCheckout(redirect, selected.group);

      return openCheckoutModal(details, selected.group);
    });
  }, [onCheckoutSuccess]);
}
