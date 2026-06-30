import { getSetting } from "@woocommerce/settings";

/** Settings exposed by Model\BlockPaymentMethod::get_payment_method_data. */
export const settings = getSetting("mobbex_data", {});

export const paymentMethods = Array.isArray(settings.methods)
  ? settings.methods
  : [];

// Only saved cards that actually have installments are usable.
export const walletCards = (
  Array.isArray(settings.cards) ? settings.cards : []
).filter((card) => card.installments && card.installments.length);

const walletActive = settings.wallet === "yes";

// Wallet takes precedence: when active, the payment methods list is hidden.
export const showMethods =
  !walletActive &&
  settings.payment_methods === "yes" &&
  paymentMethods.length > 0;
export const showWallet = walletActive && walletCards.length > 0;

export const hasSelector = showMethods || showWallet;
export const accentColor = settings.color || "#7000ff";
