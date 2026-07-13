import { __ } from "@wordpress/i18n";

/** Installments + security code fields for the selected saved card. */
export const WalletFields = ({
  card,
  installment,
  securityCode,
  onInstallmentChange,
  onSecurityCodeChange,
}) => (
  <div className="mobbex-wallet-fields">
    <p className="mobbex-form-row">
      <label htmlFor={`mobbex-wallet-${card.key}-installments`}>
        {__("Cuotas", "mobbex-for-woocommerce")}
      </label>
      <select
        id={`mobbex-wallet-${card.key}-installments`}
        required
        value={installment || ""}
        onChange={(e) => onInstallmentChange(e.target.value)}
      >
        <option value="">
          {__("Seleccionar cuotas", "mobbex-for-woocommerce")}
        </option>
        {card.installments.map((inst) => (
          <option key={inst.reference} value={inst.reference}>
            {inst.name} ({inst?.totals?.installment?.count} cuota/s de $
            {inst?.totals?.installment?.amount})
          </option>
        ))}
      </select>
    </p>
    <p className="mobbex-form-row">
      <label htmlFor={`mobbex-wallet-${card.key}-code`}>{card.codeName}</label>
      <input
        type="text"
        inputMode="numeric"
        id={`mobbex-wallet-${card.key}-code`}
        maxLength={card.codeLength}
        placeholder={card.codeName}
        required
        value={securityCode || ""}
        onChange={(e) => onSecurityCodeChange(e.target.value.replace(/\D/g, ""))}
      />
    </p>
  </div>
);
