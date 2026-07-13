import { PaymentOption } from "./PaymentOption";

/** Grid of selectable payment options (a radiogroup of chips). */
export const PaymentOptions = ({ options, selectedId, onSelect }) => (
  <div className="mobbex-options-grid" role="radiogroup">
    {options.map((option) => (
      <PaymentOption
        key={option.id}
        option={option}
        isSelected={option.id === selectedId}
        onSelect={() => onSelect(option.id)}
      />
    ))}
  </div>
);
