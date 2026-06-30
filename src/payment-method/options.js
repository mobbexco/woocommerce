import { __ } from "@wordpress/i18n";
import {
  paymentMethods,
  walletCards,
  showMethods,
  showWallet,
} from "./config";

/**
 * Build the selectable options (payment methods and/or wallet cards).
 * Mirrors the classic templates/payment-options.php logic.
 */
function buildOptions() {
  const options = [];

  if (showMethods) {
    // Card method (credit/debit) first so it becomes the default.
    const sorted = [...paymentMethods].sort((a, b) => {
      const aCard = a.subgroup === "card_input" ? 0 : 1;
      const bCard = b.subgroup === "card_input" ? 0 : 1;
      return aCard - bCard;
    });

    sorted.forEach((method) => {
      options.push({
        id: `method:${method.group}:${method.subgroup}`,
        type: "method",
        group: `${method.group}:${method.subgroup}`,
        label: method.subgroup_title,
        logo: method.subgroup_logo,
      });
    });
  }

  if (showWallet) {
    // Wallet-only: a neutral "new card / other method" entry first (default).
    if (!showMethods) {
      options.push({
        id: "default",
        type: "default",
        group: null,
        label: __("Nueva tarjeta u otro medio", "mobbex-for-woocommerce"),
        isNew: true,
      });
    }

    walletCards.forEach((card, key) => {
      options.push({
        id: `card:${key}`,
        type: "card",
        key,
        label: card.name,
        logo: card?.source?.card?.product?.logo || "",
        cardNumber: card?.card?.card_number,
        installments: card.installments,
        codeName: card?.source?.card?.product?.code?.name || "CVV",
        codeLength: card?.source?.card?.product?.code?.length || 4,
      });
    });
  }

  return options;
}

// Built once: depends only on module-level settings.
export const paymentOptions = buildOptions();
