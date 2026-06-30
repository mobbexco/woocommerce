import { useState } from "@wordpress/element";

/**
 * A single selectable payment option chip.
 *
 * Logos are shown only for saved cards (their brand logos have colors); payment
 * method logos come from the API as white images with no usable contrast.
 */
export const PaymentOption = ({ option, isSelected, onSelect }) => {
  const [logoFailed, setLogoFailed] = useState(false);
  const hasLogo = option.type === "card" && option.logo && !logoFailed;

  return (
    <button
      type="button"
      role="radio"
      aria-checked={isSelected}
      className={`mobbex-option ${hasLogo ? "has-logo" : "no-logo"}${
        isSelected ? " is-selected" : ""
      }${option.isNew ? " is-new" : ""}`}
      onClick={onSelect}
    >
      {hasLogo && (
        <img
          className="mobbex-option-logo"
          src={option.logo}
          alt={option.label}
          onError={() => setLogoFailed(true)}
        />
      )}
      {option.isNew && <span className="mobbex-option-plus">+</span>}
      <span className="mobbex-option-text">{option.label}</span>
    </button>
  );
};
