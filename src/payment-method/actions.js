import { __ } from "@wordpress/i18n";

/**
 * Imperative payment actions (navigation + Mobbex SDK) run after the order is
 * placed. No React state; they act on the checkout result.
 */

/** Redirect to the hosted checkout, optionally pre-selecting a method group. */
export function redirectToCheckout(redirect, group) {
  window.top.location = redirect + (group ? "?paymentMethod=" + group : "");
  return true;
}

/** Open the Mobbex checkout modal (embed). */
export function openCheckoutModal(response, group) {
  let paymentData = false;

  const options = {
    id: response.checkout_id,
    type: "checkout",
    paymentMethod: group || null,

    onResult: (data) => {
      location.href = `${response.return_url}&fromCallback=onResult&status=${data.status.code}`;
    },
    onPayment: (data) => {
      paymentData = data.data;
    },
    onClose: () => {
      location.href = `${response.return_url}&fromCallback=onClose&status=${
        paymentData ? paymentData.status.code : "500"
      }`;
    },
    onError: () => {
      location.href = `${response.return_url}&fromCallback=onError&status=${
        paymentData ? paymentData.status.code : "500"
      }`;
    },
  };

  window.MobbexEmbed.init(options).open();
  return true;
}

/**
 * Per-card intent token map from the checkout response. Prefers the
 * string-safe `wallet_tokens` (the Store API casts payment_details to string),
 * falling back to the raw checkout data.
 */
function getWalletTokens(response) {
  if (response.wallet_tokens) {
    try {
      return JSON.parse(response.wallet_tokens);
    } catch (e) {
      console.error("[Mobbex] Could not parse wallet tokens:", e);
      return [];
    }
  }

  if (response.data && Array.isArray(response.data.wallet)) {
    return response.data.wallet.map((card) => ({
      card_number: card?.card?.card_number,
      it: card?.it,
    }));
  }

  return [];
}

/** Error response shown as a checkout notice (keeps the shopper on checkout). */
function walletError(message, emitResponse) {
  return {
    type: emitResponse?.responseTypes?.ERROR || "error",
    message,
    messageContext: emitResponse?.noticeContexts?.PAYMENTS,
  };
}

/**
 * Process a saved-card (wallet) payment client-side via the Mobbex SDK.
 * Navigates to the return URL on success; resolves to an error notice on
 * a missing card, rejection or SDK failure.
 */
export async function executeWallet(response, selected, emitResponse) {
  const card = getWalletTokens(response).find(
    (c) => c.card_number == selected.cardNumber,
  );

  if (!card || !card.it) {
    console.error("[Mobbex] Wallet card not found in checkout response");
    return walletError(
      __(
        "No se pudo procesar la tarjeta seleccionada. Intente nuevamente.",
        "mobbex-for-woocommerce",
      ),
      emitResponse,
    );
  }

  try {
    const data = await window.MobbexJS.operation.process({
      intentToken: card.it,
      installment: selected.installment,
      securityCode: selected.securityCode,
    });

    if (data.result === true) {
      location.href = `${response.return_url}&status=${data.data.status.code}`;
      return true;
    }

    console.error("[Mobbex] Wallet payment error:", data);
    return walletError(
      __(
        "El pago fue rechazado. Verifique los datos de la tarjeta e intente nuevamente.",
        "mobbex-for-woocommerce",
      ),
      emitResponse,
    );
  } catch (error) {
    console.error("[Mobbex] Wallet payment exception:", error);
    return walletError(
      __(
        "Ocurrió un error al procesar el pago. Intente nuevamente.",
        "mobbex-for-woocommerce",
      ),
      emitResponse,
    );
  }
}
