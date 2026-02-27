/**
 * Mobbex Transparent - React Component for Checkout Blocks
 */

import { useState, useEffect } from "react";
import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { getSetting } from "@woocommerce/settings";
import { __ } from "@wordpress/i18n";

const settings = getSetting("mobbex_transparent_data", {});

/**
 * Transparent payment method label
 */
const Label = () => {
  return (
    <span className="wc-block-components-transparent-label">
      {settings.title ||
        __("Tarjeta de Crédito/Débito", "mobbex-for-woocommerce")}
    </span>
  );
};

/**
 * Form main component
 */
const TransparentContent = ({
  eventRegistration,
  emitResponse,
  activePaymentMethod,
}) => {
  // Form states
  const [cardNumber, setCardNumber] = useState("");
  const [cardName, setCardName] = useState("");
  const [cardDni, setCardDni] = useState("");
  const [cardExpiration, setCardExpiration] = useState("");
  const [securityCode, setSecurityCode] = useState("");
  const [installments, setInstallments] = useState([]);
  const [selectedInstallment, setSelectedInstallment] = useState("");

  // UI states
  const [isDetecting, setIsDetecting] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [errors, setErrors] = useState({});
  const [detectedSource, setDetectedSource] = useState(null);

  // constants
  const restUrl = "/wp-json/mobbex/v1";
  const intentToken = settings.intent_token;

  /**
   * Detect sources when card number is entered
   */
  useEffect(() => {
    const detectSource = async () => {
      const cleanNumber = cardNumber.replace(/\s/g, "");

      if (cleanNumber.length >= 6) {
        setIsDetecting(true);

        try {
          const bin = cleanNumber.substring(0, 6);
          const res = await fetch(restUrl + "/detect", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              bin: bin,
              token: intentToken,
            }),
          });

          if (!res.ok)
            throw new Error(
              `Network response was not ok: ${
                res.statusText
              } - ${await res.text()}`,
            );

          const card = await res.json();

          if (!card || typeof card !== "object")
            throw new Error("Empty response data" + JSON.stringify(card));

          if (card.data?.installments && card.data.installments.length > 0) {
            setDetectedSource(card.data.source);
            setInstallments(card.data.installments);
            setSelectedInstallment((prev) => {
              const hasCurrentOption = card.data.installments.some(
                (installment) => String(installment.reference) === String(prev),
              );

              if (hasCurrentOption) return prev;
              if (card.data.installments.length === 1)
                return String(card.data.installments[0].reference);

              return "";
            });

            clearError("cardNumber");
          } else {
            setErrors((prev) => ({
              ...prev,
              cardNumber: __(
                "No se encontraron cuotas para esta tarjeta",
                "mobbex-for-woocommerce",
              ),
            }));
            setInstallments([]);
            setSelectedInstallment("");
            setDetectedSource(null);
          }
        } catch (error) {
          console.error("[Mobbex Transparent] Error detecting source:", error);
          setErrors((prev) => ({
            ...prev,
            cardNumber: __(
              "Error al detectar medio de pago",
              "mobbex-for-woocommerce",
            ),
          }));
        } finally {
          setIsDetecting(false);
        }
      } else {
        // Clear installments
        setInstallments([]);
        setSelectedInstallment("");
        setDetectedSource(null);
      }
    };

    // Debounce to avoid constants calls to Wordpress server
    const timer = setTimeout(detectSource, 500);
    return () => clearTimeout(timer);
  }, [cardNumber, intentToken, restUrl]);

  /**
   * Register handler for payment process.
   * Will be fired when order is placed.
   */
  useEffect(() => {
    const unsubscribe = eventRegistration.onPaymentSetup(async () => {
      if (activePaymentMethod !== "mobbex_transparent") return;

      if (!validateAllFields()) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: __(
            "Por favor complete todos los campos correctamente",
            "mobbex-for-woocommerce",
          ),
        };
      }

      // return to transparent gateway for transaction process
      return {
        type: emitResponse.responseTypes.SUCCESS,
        meta: {
          paymentMethodData: {
            number: String(cardNumber).replace(/\s/g, ""),
            name: String(cardName),
            identification: String(cardDni),
            expiry: String(cardExpiration).replace(/\s/g, ""),
            cvv: String(securityCode),
            installments: String(selectedInstallment),
          },
        },
      };
    });

    return unsubscribe;
  }, [
    activePaymentMethod,
    cardNumber,
    cardName,
    cardDni,
    cardExpiration,
    securityCode,
    selectedInstallment,
  ]);

  const validateAllFields = () => {
    const newErrors = {};
    let isValid = true;

    const cleanCardNumber = cardNumber.replace(/\s/g, "");
    if (!cleanCardNumber || !/^\d{13,19}$/.test(cleanCardNumber)) {
      newErrors.cardNumber = __(
        "Número de tarjeta inválido",
        "mobbex-for-woocommerce",
      );
      isValid = false;
    }

    if (!cardName || cardName.length < 3) {
      newErrors.cardName = __("Nombre inválido", "mobbex-for-woocommerce");
      isValid = false;
    }

    if (!cardDni || !/^\d{7,15}$/.test(cardDni)) {
      newErrors.cardDni = __("DNI inválido", "mobbex-for-woocommerce");
      isValid = false;
    }

    const cleanExpiration = cardExpiration.replace(/\s/g, "");
    if (!cleanExpiration || !/^(0[1-9]|1[0-2])\/\d{2}$/.test(cleanExpiration)) {
      newErrors.cardExpiration = __(
        "Fecha de vencimiento inválida",
        "mobbex-for-woocommerce",
      );
      isValid = false;
    }

    if (!securityCode || !/^\d{3,4}$/.test(securityCode)) {
      newErrors.securityCode = __("CVV inválido", "mobbex-for-woocommerce");
      isValid = false;
    }

    if (!selectedInstallment) {
      newErrors.installments = __(
        "Debe seleccionar las cuotas",
        "mobbex-for-woocommerce",
      );
      isValid = false;
    }

    setErrors(newErrors);
    return isValid;
  };

  /** Cleaning and format funcs */
  const clearError = (field) => {
    setErrors((prev) => {
      const newErrors = { ...prev };
      delete newErrors[field];
      return newErrors;
    });
  };

  const formatCardNumber = (value) => {
    const cleaned = value.replace(/\s/g, "");
    const formatted = cleaned.match(/.{1,4}/g)?.join(" ") || cleaned;
    return formatted.substring(0, 19);
  };

  const formatExpiration = (value) => {
    const cleaned = value.replace(/\D/g, "");
    if (cleaned.length >= 2) {
      const month = cleaned.substring(0, 2);
      const year =
        cleaned.length > 4
          ? cleaned.substring(cleaned.length - 2)
          : cleaned.substring(2, 4);

      return year ? month + " / " + year : month;
    }
    return cleaned;
  };

  // Form render
  return (
    <div className="mobbex-transparent-form wc-block-components-form">
      {settings.description && (
        <p className="mobbex-description">{settings.description}</p>
      )}

      {/* Sources banner */}
      {settings.show_banner && (
        <div className="mobbex-checkout-banner">
          <img
            src="https://res.mobbex.com/images/sources/png/banner.png"
            alt={__("Medios de pago", "mobbex-for-woocommerce")}
          />
        </div>
      )}

      {/* Card number */}
      <div className="wc-block-components-text-input mobbex-form-row">
        <input
          type="text"
          id="mobbex-card-number"
          className={`mobbex-form-input ${
            errors.cardNumber ? "has-error" : ""
          }`}
          value={cardNumber}
          onChange={(e) => {
            setCardNumber(formatCardNumber(e.target.value));
            clearError("cardNumber");
          }}
          placeholder="1234 5678 9012 3456"
          maxLength="19"
          autoComplete="cc-number"
          inputMode="numeric"
        />
        {errors.cardNumber && (
          <span className="mobbex-field-error">{errors.cardNumber}</span>
        )}
      </div>

      {/* Name */}
      <div className="wc-block-components-text-input mobbex-form-row">
        <input
          type="text"
          id="mobbex-card-name"
          className={`mobbex-form-input ${errors.cardName ? "has-error" : ""}`}
          value={cardName}
          onChange={(e) => {
            setCardName(e.target.value);
            clearError("cardName");
          }}
          placeholder="Nombre Tarjeta"
          autoComplete="cc-name"
        />
        {errors.cardName && (
          <span className="mobbex-field-error">{errors.cardName}</span>
        )}
      </div>

      {/* DNI */}
      <div className="wc-block-components-text-input mobbex-form-row">
        <input
          type="text"
          id="mobbex-card-dni"
          className={`mobbex-form-input ${errors.cardDni ? "has-error" : ""}`}
          value={cardDni}
          onChange={(e) => {
            setCardDni(e.target.value.replace(/\D/g, ""));
            clearError("cardDni");
          }}
          placeholder="12345678"
          maxLength="15"
          inputMode="numeric"
        />
        {errors.cardDni && (
          <span className="mobbex-field-error">{errors.cardDni}</span>
        )}
      </div>

      <div className="mobbex-form-row-group">
        {/* Expiration */}
        <div className="wc-block-components-text-input mobbex-form-row half">
          <input
            type="text"
            id="mobbex-card-expiration"
            className={`mobbex-form-input ${
              errors.cardExpiration ? "has-error" : ""
            }`}
            value={cardExpiration}
            onChange={(e) => {
              setCardExpiration(formatExpiration(e.target.value));
              clearError("cardExpiration");
            }}
            placeholder="MM / AA"
            maxLength="7"
            autoComplete="cc-exp"
            inputMode="numeric"
          />
          {errors.cardExpiration && (
            <span className="mobbex-field-error">{errors.cardExpiration}</span>
          )}
        </div>

        {/* CVV */}
        <div className="wc-block-components-text-input mobbex-form-row half">
          <input
            type="text"
            id="mobbex-security-code"
            className={`mobbex-form-input ${
              errors.securityCode ? "has-error" : ""
            }`}
            value={securityCode}
            onChange={(e) => {
              setSecurityCode(e.target.value.replace(/\D/g, ""));
              clearError("securityCode");
            }}
            placeholder="123"
            maxLength="4"
            autoComplete="cc-csc"
            inputMode="numeric"
          />
          {errors.securityCode && (
            <span className="mobbex-field-error">{errors.securityCode}</span>
          )}
        </div>
      </div>

      {/* Installments */}
      <div className="wc-block-components-text-input mobbex-form-row">
        <select
          id="mobbex-installments"
          className={`mobbex-form-input ${
            errors.installments ? "has-error" : ""
          }`}
          value={selectedInstallment}
          onChange={(e) => {
            setSelectedInstallment(e.target.value);
            clearError("installments");
          }}
          disabled={isDetecting || installments.length === 0}
        >
          <option value="">
            {isDetecting
              ? settings.i18n?.installments_loading ||
                __("Cargando cuotas...", "mobbex-for-woocommerce")
              : installments.length === 0
              ? __("Ingrese el número de tarjeta", "mobbex-for-woocommerce")
              : settings.i18n?.installments_placeholder ||
                __("Seleccionar cuotas", "mobbex-for-woocommerce")}
          </option>
          {installments.map((installment) => (
            <option key={installment.reference} value={installment.reference}>
              {installment.name}
            </option>
          ))}
        </select>
        {errors.installments && (
          <span className="mobbex-field-error">{errors.installments}</span>
        )}
      </div>

      {isProcessing && (
        <div className="mobbex-processing-message">
          {settings.i18n?.processing ||
            __("Procesando pago...", "mobbex-for-woocommerce")}
        </div>
      )}
    </div>
  );
};

registerPaymentMethod({
  name: "mobbex_transparent",
  label: <Label />,
  content: <TransparentContent />,
  edit: <TransparentContent />,
  canMakePayment: () => {
    return true;
  },
  ariaLabel: settings.title || "Mobbex Transparent",
  supports: {
    features: settings.supports || ["products"],
  },
});
