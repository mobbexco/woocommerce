import { useState, useEffect, useRef } from "@wordpress/element";

/**
 * Manages the selected option and the per-card wallet inputs (installments +
 * security code), exposing a ref with the live selection for the checkout
 * callbacks (which subscribe once).
 */
export function usePaymentSelection(options) {
  const [selectedId, setSelectedId] = useState(
    options[0] ? options[0].id : "default",
  );
  const [installments, setInstallments] = useState({});
  const [securityCodes, setSecurityCodes] = useState({});

  const selected =
    options.find((o) => o.id === selectedId) || options[0] || {};
  const selectedCard = selected.type === "card" ? selected : null;

  const selectionRef = useRef({});
  useEffect(() => {
    selectionRef.current = {
      ...selected,
      installment:
        selected.key !== undefined ? installments[selected.key] : null,
      securityCode:
        selected.key !== undefined ? securityCodes[selected.key] : null,
    };
  }, [selected, installments, securityCodes]);

  const setInstallment = (key, value) =>
    setInstallments((prev) => ({ ...prev, [key]: value }));
  const setSecurityCode = (key, value) =>
    setSecurityCodes((prev) => ({ ...prev, [key]: value }));

  return {
    selectedId,
    setSelectedId,
    selectedCard,
    installments,
    securityCodes,
    setInstallment,
    setSecurityCode,
    selectionRef,
  };
}
