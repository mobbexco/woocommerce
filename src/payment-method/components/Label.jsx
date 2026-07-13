import { settings } from "../config";

/** Label shown next to the Mobbex method radio in the checkout. */
export const Label = ({ components }) => {
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
