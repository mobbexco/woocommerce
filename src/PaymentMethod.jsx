import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { settings } from "./payment-method/config";
import { Label } from "./payment-method/components/Label";
import { PaymentContent } from "./payment-method/components/PaymentContent";

/** Register the Mobbex (redirect) payment method for the checkout block. */
registerPaymentMethod({
  name: "mobbex",
  paymentMethodId: "mobbex",
  label: <Label />,
  content: <PaymentContent />,
  edit: <PaymentContent />,
  canMakePayment: () => true,
  ariaLabel: "Mobbex",
  supports: {
    features: settings.supports || ["products", "refunds"],
  },
});
