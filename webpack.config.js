const defaultConfig = require("@wordpress/scripts/config/webpack.config.js");
const WooCommerceDependencyExtractionWebpackPlugin = require("@woocommerce/dependency-extraction-webpack-plugin");
const path = require("path");

const wcDepMap = {
  "@woocommerce/blocks-registry": ["wc", "wcBlocksRegistry"],
  "@woocommerce/settings": ["wc", "wcSettings"],
};

const wcHandleMap = {
  "@woocommerce/blocks-registry": "wc-blocks-registry",
  "@woocommerce/settings": "wc-settings",
};

const requestToExternal = (request) => {
  if (wcDepMap[request]) {
    return wcDepMap[request];
  }
};

const requestToHandle = (request) => {
  if (wcHandleMap[request]) {
    return wcHandleMap[request];
  }
};

// Export configuration.
module.exports = {
  ...defaultConfig,
  entry: {
    "frontend/payment-method": "/src/PaymentMethod.jsx",
    "frontend/transparent": "/src/Transparent.jsx",
  },
  output: {
    path: path.resolve(__dirname, "assets/blocks"),
    filename: "[name].js",
  },
  externals: {
    "@woocommerce/blocks-registry": ["wc", "wcBlocksRegistry"],
    "@woocommerce/settings": ["wc", "wcSettings"],
    react: "React",
    "react-dom": "ReactDOM",
  },
  plugins: [
    ...defaultConfig.plugins.filter(
      (plugin) =>
        plugin.constructor.name !== "DependencyExtractionWebpackPlugin",
    ),
    new WooCommerceDependencyExtractionWebpackPlugin({
      requestToExternal,
      requestToHandle,
    }),
  ],
};
