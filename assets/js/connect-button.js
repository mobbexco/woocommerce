(function (window) {
  /**
   * Bind connect button action in gateway Mobbex settings.
   * Validate API key and then execute a backend request (/start_connect).
   */
  function handleConnectButton() {
    var apiKeyField = document.getElementById("woocommerce_mobbex_api-key");
    var connectButton = document.getElementById("woocommerce_mobbex_connect");

    if (!connectButton || !apiKeyField) return;

    // Enable/Disable other mobbex fields depending on connection state
    function toggleSettingsByConnection(isConnected) {
      var settingsFields = document.querySelectorAll(
        "input[id^='woocommerce_mobbex_'], select[id^='woocommerce_mobbex_'], textarea[id^='woocommerce_mobbex_'], button[id^='woocommerce_mobbex_']",
      );

      settingsFields.forEach(function (field) {
        if (field === connectButton || field === apiKeyField) return;

        if (isConnected) field.removeAttribute("disabled");
        else field.setAttribute("disabled", "disabled");
      });
    }

    function applyConnectState(isConnected) {
      var apiKeyRow = apiKeyField.closest("tr");
      var connectDescriptionEl = connectButton
        .closest("td")
        ?.querySelector(".description");

      toggleSettingsByConnection(isConnected);

      if (isConnected) {
        if (apiKeyRow) apiKeyRow.classList.add("hidden");

        apiKeyField.setAttribute("disabled", "disabled");
        connectButton.textContent =
          (window.mobbexPluginConfig &&
            window.mobbexPluginConfig.connectedLabel) ||
          "Already connected with Mobbex";
        connectButton.setAttribute("disabled", "disabled");
        if (window.mobbexPluginConfig?.connectedDescription) {
          connectButton.setAttribute(
            "aria-description",
            window.mobbexPluginConfig.connectedDescription,
          );
          if (connectDescriptionEl) {
            connectDescriptionEl.textContent =
              window.mobbexPluginConfig.connectedDescription;
          }
        }
        return;
      }

      if (apiKeyRow) apiKeyRow.classList.remove("hidden");

      apiKeyField.removeAttribute("disabled");
      connectButton.textContent =
        (window.mobbexPluginConfig && window.mobbexPluginConfig.connectLabel) ||
        "Connect with Mobbex.";
      connectButton.removeAttribute("disabled");
      if (window.mobbexPluginConfig?.connectDescription) {
        connectButton.setAttribute(
          "aria-description",
          window.mobbexPluginConfig.connectDescription,
        );
        if (connectDescriptionEl) {
          connectDescriptionEl.textContent =
            window.mobbexPluginConfig.connectDescription;
        }
      }
    }

    applyConnectState(
      Boolean(
        window.mobbexPluginConfig && window.mobbexPluginConfig.isConnected,
      ),
    );

    connectButton.addEventListener("click", async function (event) {
      event.preventDefault();

      const baseUrl = window.location.origin;
      var connectStartUrl =
        window.mobbexPluginConfig && window.mobbexPluginConfig.connectStartUrl
          ? window.mobbexPluginConfig.connectStartUrl
          : baseUrl + "/wp-json/mobbex/v1/connect_start";
      var connectRedirectUrl =
        window.mobbexPluginConfig &&
        window.mobbexPluginConfig.connectRedirectUrl
          ? window.mobbexPluginConfig.connectRedirectUrl
          : baseUrl + "/wp-json/mobbex/v1/connect_redirect";
      var apiKey = apiKeyField.value ? apiKeyField.value.trim() : "";

      if (!apiKey) {
        window.alert(
          "[Mobbex] Connect - Debes ingresar API Key antes de conectar.",
        );
        apiKeyField.focus();
        return;
      }

      var requestBody = {
        api_key: apiKey,
        return_url: connectRedirectUrl,
      };

      try {
        connectButton.setAttribute("disabled", "disabled");

        var payload = {};
        try {
          var response = await fetch(connectStartUrl, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify(requestBody),
          });

          if (!response.ok) {
            throw new Error(`HTTP error: ${response.status}`);
          }

          payload = await response.json();
        } catch (e) {
          console.error("Mobbex connect error", e);
        }

        if (!response.ok) {
          if (response.status === 401) {
            window.alert(
              "[Mobbex] Connect - API Key inválida o sin permisos para conectar.",
            );
            return;
          }

          window.alert(
            "[Mobbex] Connect - No se pudo iniciar la conexión. Código: " +
              response.status +
              (payload && payload.message ? " - " + payload.message : ""),
          );
          return;
        }

        var redirectUrl = payload.redirect_url;

        if (!redirectUrl) {
          console.error("Mobbex connect invalid payload", {
            status: response.status,
            payload: payload,
          });
          window.alert(
            "[Mobbex] Connect - Respuesta inválida al iniciar la conexión.",
          );
          return;
        }

        window.location.href = redirectUrl;
      } catch (error) {
        console.error("Mobbex connect request failed", error);
        window.alert("Ocurrió un error al iniciar la conexión.");
      } finally {
        connectButton.removeAttribute("disabled");
      }
    });
  }

  window.addEventListener("load", function () {
    handleConnectButton();
  });
})(window);
