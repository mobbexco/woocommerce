import { decodeEntities } from "@wordpress/html-entities";
import { settings, hasSelector, accentColor } from "../config";
import { paymentOptions } from "../options";
import { usePaymentSelection } from "../hooks/usePaymentSelection";
import { useMobbexCheckout } from "../hooks/useMobbexCheckout";
import { PaymentOptions } from "./PaymentOptions";
import { WalletFields } from "./WalletFields";

/** Content rendered when the Mobbex method is selected in the checkout block. */
export const PaymentContent = ({ eventRegistration, emitResponse }) => {
  const {
    selectedId,
    setSelectedId,
    selectedCard,
    installments,
    securityCodes,
    setInstallment,
    setSecurityCode,
    selectionRef,
  } = usePaymentSelection(paymentOptions);

  useMobbexCheckout({ eventRegistration, emitResponse, selectionRef });

  return (
    <div
      className="mobbex-block-content"
      style={{ "--mbbx-color": accentColor }}
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
        <PaymentOptions
          options={paymentOptions}
          selectedId={selectedId}
          onSelect={setSelectedId}
        />
      )}

      {selectedCard && (
        <WalletFields
          card={selectedCard}
          installment={installments[selectedCard.key]}
          securityCode={securityCodes[selectedCard.key]}
          onInstallmentChange={(value) =>
            setInstallment(selectedCard.key, value)
          }
          onSecurityCodeChange={(value) =>
            setSecurityCode(selectedCard.key, value)
          }
        />
      )}
    </div>
  );
};
