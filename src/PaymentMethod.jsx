const { useEffect, useState, useRef } = window.wp.element;
const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const settings = window.wc.wcSettings.getSetting("mobbex_data", {});
const { decodeEntities } = window.wp.htmlEntities;

const { __ } = window.wp.i18n;

const paymentMethods = Array.isArray(settings.methods) ? settings.methods : [];

// Only saved cards that actually have installments can be rendered/used.
const walletCards = (
  Array.isArray(settings.cards) ? settings.cards : []
).filter((card) => card.installments && card.installments.length);

const walletActive = settings.wallet === "yes";

// Wallet takes precedence over the in-site payment methods
const showMethods =
  !walletActive &&
  settings.payment_methods === "yes" &&
  paymentMethods.length > 0;
const showWallet = walletActive && walletCards.length > 0;

/**
 * Label component shown next to the Mobbex method radio.
 *
 * @param {*} props Props from payment API.
 */
const Label = ({ components }) => {
  const { PaymentMethodLabel } = components;

  return (
    <>
      {settings.payment_method_image != "" && (
        <img
          src={settings.payment_method_image}
          style={{ marginRight: "5px" }}
        />
      )}
      <PaymentMethodLabel text={settings.title} />
    </>
  );
};

/**
 * Build the selectable options (payment methods and/or wallet cards).
 * Mirrors the classic templates/payment-options.php logic.
 */
function buildOptions() {
  const options = [];

  if (showMethods) {
    // Show the card method (credit/debit) first so it becomes the default,
    // regardless of the order returned by the API.
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
    // Shows default "new card / other method" entry first
    if (!showMethods) {
      options.push({
        id: "default",
        type: "default",
        group: null,
        label: __("Nueva tarjeta u otro medio", "mobbex-for-woocommerce"),
        logo: "",
        isNew: true,
      });
    }

    walletCards.forEach((card, key) => {
      if (!card.installments || !card.installments.length) return;

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

/**
 * Content rendered when the Mobbex method is selected.
 */
const Content = ({ eventRegistration, emitResponse }) => {
  const { onCheckoutSuccess, onPaymentSetup } = eventRegistration;
  const responseTypes = emitResponse?.responseTypes || {
    SUCCESS: "success",
    ERROR: "error",
  };

  const options = buildOptions();
  const hasSelector = showMethods || showWallet;

  const [selectedId, setSelectedId] = useState(
    options[0] ? options[0].id : "default",
  );
  // Per-card wallet input state, keyed by card key.
  const [walletInstallments, setWalletInstallments] = useState({});
  const [walletCvv, setWalletCvv] = useState({});
  // Wallet card logos that failed to load (so we can drop them gracefully).
  const [failedLogos, setFailedLogos] = useState({});

  // Currently selected saved card (if any), used to render its wallet fields.
  const selectedCard = options.find(
    (o) => o.id === selectedId && o.type === "card",
  );

  // Keep the current selection available inside the success callback closure.
  const selectionRef = useRef({});

  useEffect(() => {
    const selected =
      options.find((o) => o.id === selectedId) || options[0] || {};
    selectionRef.current = {
      ...selected,
      installment:
        selected.key !== undefined ? walletInstallments[selected.key] : null,
      securityCode: selected.key !== undefined ? walletCvv[selected.key] : null,
    };
  }, [selectedId, walletInstallments, walletCvv]);

  // Validate wallet fields before placing the order.
  useEffect(() => {
    if (!onPaymentSetup) return;

    const unsubscribe = onPaymentSetup(() => {
      const selected = selectionRef.current;

      if (selected.type === "card") {
        if (!selected.installment) {
          return {
            type: responseTypes.ERROR,
            message: __(
              "Debe seleccionar las cuotas",
              "mobbex-for-woocommerce",
            ),
          };
        }
        if (!selected.securityCode) {
          return {
            type: responseTypes.ERROR,
            message: __(
              "Debe ingresar el código de seguridad",
              "mobbex-for-woocommerce",
            ),
          };
        }
      }

      return { type: responseTypes.SUCCESS };
    });

    return unsubscribe;
  }, [onPaymentSetup]);

  // Handle the result returned by the gateway when the order is placed.
  useEffect(() => {
    const unsubscribe = onCheckoutSuccess((response) => {
      const selected = selectionRef.current;
      const details = response?.processingResponse?.paymentDetails || {};

      // Saved card (wallet) flow: process the operation client-side.
      if (selected.type === "card") {
        return executeWallet(details, selected);
      }

      // Payment method / default flow: redirect or open the modal.
      const redirect = response.redirect || details.redirect;
      if (redirect) {
        return redirectToCheckout(redirect, selected.group);
      }

      return openCheckoutModal(details, selected.group);
    });

    return unsubscribe;
  }, [onCheckoutSuccess]);

  return (
    <div
      className="mobbex-block-content"
      style={{ "--mbbx-color": settings.color || "#7000ff" }}
    >
      {settings.description != "" && (
        <p>{decodeEntities(settings.description)}</p>
      )}

      {settings.checkout_banner != "" && (
        <img
          src={settings.checkout_banner}
          alt="mobbex metodos de pagos"
          style={{ maxWidth: "100%", borderRadius: "0%" }}
        />
      )}

      {hasSelector && (
        <div className="mobbex-options-grid" role="radiogroup">
          {options.map((option) => {
            const isSelected = option.id === selectedId;
            // Logos are only shown for saved cards (wallet)
            // Payment method logos are intentionally NOT rendered because the API returns them as white images :/...
            const hasLogo =
              option.type === "card" && option.logo && !failedLogos[option.id];

            return (
              <button
                type="button"
                key={option.id}
                role="radio"
                aria-checked={isSelected}
                className={`mobbex-option ${hasLogo ? "has-logo" : "no-logo"}${
                  isSelected ? " is-selected" : ""
                }${option.isNew ? " is-new" : ""}`}
                onClick={() => setSelectedId(option.id)}
              >
                {hasLogo && (
                  <img
                    className="mobbex-option-logo"
                    src={option.logo}
                    alt={option.label}
                    onError={() =>
                      setFailedLogos((prev) => ({ ...prev, [option.id]: true }))
                    }
                  />
                )}
                {option.isNew && <span className="mobbex-option-plus">+</span>}
                <span className="mobbex-option-text">{option.label}</span>
              </button>
            );
          })}
        </div>
      )}

      {/* Wallet fields for the selected saved card */}
      {selectedCard && (
        <div className="mobbex-wallet-fields">
          <p className="mobbex-form-row">
            <label htmlFor={`mobbex-wallet-${selectedCard.key}-installments`}>
              {__("Cuotas", "mobbex-for-woocommerce")}
            </label>
            <select
              id={`mobbex-wallet-${selectedCard.key}-installments`}
              required
              value={walletInstallments[selectedCard.key] || ""}
              onChange={(e) =>
                setWalletInstallments((prev) => ({
                  ...prev,
                  [selectedCard.key]: e.target.value,
                }))
              }
            >
              <option value="">
                {__("Seleccionar cuotas", "mobbex-for-woocommerce")}
              </option>
              {selectedCard.installments.map((installment) => (
                <option
                  key={installment.reference}
                  value={installment.reference}
                >
                  {installment.name} ({installment?.totals?.installment?.count}{" "}
                  cuota/s de ${installment?.totals?.installment?.amount})
                </option>
              ))}
            </select>
          </p>
          <p className="mobbex-form-row">
            <label htmlFor={`mobbex-wallet-${selectedCard.key}-code`}>
              {selectedCard.codeName}
            </label>
            <input
              type="text"
              inputMode="numeric"
              id={`mobbex-wallet-${selectedCard.key}-code`}
              maxLength={selectedCard.codeLength}
              placeholder={selectedCard.codeName}
              required
              value={walletCvv[selectedCard.key] || ""}
              onChange={(e) =>
                setWalletCvv((prev) => ({
                  ...prev,
                  [selectedCard.key]: e.target.value.replace(/\D/g, ""),
                }))
              }
            />
          </p>
        </div>
      )}
    </div>
  );
};

/**
 * Redirect to Mobbex checkout page, optionally pre-selecting a payment method.
 *
 * @param {string} redirect    Mobbex checkout URL.
 * @param {string|null} group  Selected payment method group (group:subgroup).
 */
function redirectToCheckout(redirect, group) {
  window.top.location = redirect + (group ? "?paymentMethod=" + group : "");
  return true;
}

/**
 * Open the Mobbex checkout modal.
 *
 * @param {object} response     Mobbex checkout response (paymentDetails).
 * @param {string|null} group   Selected payment method group (group:subgroup).
 */
function openCheckoutModal(response, group) {
  let mbbxPaymentData = false;

  let options = {
    id: response.checkout_id,
    type: "checkout",
    paymentMethod: group || null,

    onResult: (data) => {
      location.href =
        response.return_url +
        "&fromCallback=onResult&status=" +
        data.status.code;
    },

    onPayment: (data) => {
      mbbxPaymentData = data.data;
    },

    onClose: (cancelled) => {
      location.href =
        response.return_url +
        "&fromCallback=onClose&status=" +
        (mbbxPaymentData ? mbbxPaymentData.status.code : "500");
    },

    onError: (error) => {
      location.href =
        response.return_url +
        "&fromCallback=onError&status=" +
        (mbbxPaymentData ? mbbxPaymentData.status.code : "500");
    },
  };

  let mobbexEmbed = window.MobbexEmbed.init(options);
  mobbexEmbed.open();

  return true;
}

/**
 * Execute a wallet (saved card) payment client-side via the Mobbex SDK.
 *
 * @param {object} response  Mobbex checkout response (paymentDetails).
 * @param {object} selected  Selected wallet card with installment and CVV.
 */
function executeWallet(response, selected) {
  // The Store API casts payment_details to strings, so prefer the string-safe
  // wallet token map; fall back to the raw checkout data when available.
  let wallet = [];

  if (response.wallet_tokens) {
    try {
      wallet = JSON.parse(response.wallet_tokens);
    } catch (e) {
      console.error("[Mobbex] Could not parse wallet tokens:", e);
    }
  } else if (response.data && Array.isArray(response.data.wallet)) {
    wallet = response.data.wallet.map((card) => ({
      card_number: card.card.card_number,
      it: card.it,
    }));
  }

  const updatedCard = wallet.find(
    (card) => card.card_number == selected.cardNumber,
  );

  if (!updatedCard || !updatedCard.it) {
    console.error("[Mobbex] Wallet card not found in checkout response");
    return true;
  }

  const options = {
    intentToken: updatedCard.it,
    installment: selected.installment,
    securityCode: selected.securityCode,
  };

  window.MobbexJS.operation
    .process(options)
    .then((data) => {
      if (data.result === true) {
        location.href =
          response.return_url + "&status=" + data.data.status.code;
      } else {
        console.error("[Mobbex] Wallet payment error:", data);
        window.location.reload();
      }
    })
    .catch((error) => {
      console.error("[Mobbex] Wallet payment exception:", error);
      window.location.reload();
    });

  return true;
}

/** Register the Mobbex method */
registerPaymentMethod({
  name: "mobbex",
  paymentMethodId: "mobbex",
  label: <Label />,
  content: <Content />,
  edit: <Content />,
  canMakePayment: () => true,
  ariaLabel: "Mobbex",
  supports: {
    features: settings.supports || ["products", "refunds"],
  },
});
