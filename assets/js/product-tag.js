window.addEventListener("load", () => {  
  function displayProductTag(platform = "woocommerce") {
    let anchorProp;
    switch (platform) {
      case "woocommerce":
        anchorProp = {
          price: ".price",
          img: "img",
          list: ".products",
        };
        break;

      default:
        break;
    }
    const products = document.querySelectorAll(anchorProp.list + " li");
    products.forEach((product) => {
      const tag_plan = product.querySelector('.mobbex-finance-data');
      if (!tag_plan) return;
      console.log("tag_plan data", tag_plan);

      let plan = {
        count: tag_plan.dataset.planCount,
        amount: tag_plan.dataset.planAmount,
        source: tag_plan.dataset.planSource,
        percentage: tag_plan.dataset.planPercentage
      };

      if (window.showFlag)
        addSourceFlag(product, anchorProp.img, plan);

      if (window.showBanner)
        addFinanceBanner(product, anchorProp.price, plan);
    });
  }

  // Handles add flag over product image
  function addSourceFlag(product, eImg, plan) {
    const imgElement = product.querySelector(eImg);
    if (!imgElement) {
      console.error("no se encontró " + eImg + " a la que añadir el elemento");
      return;
    }

    // Wrapper. Flag parent element
    const wrapper = document.createElement("div");
    wrapper.classList.add("mobbex-wrapper");

    // flag container
    const flagContainer = document.createElement("div");
    flagContainer.classList.add("mobbex-flag-container");

    // insert before shop product img (over with style)
    imgElement.parentNode?.insertBefore(wrapper, imgElement);
    wrapper.appendChild(imgElement);
    wrapper.appendChild(flagContainer);

    // Flag
    const flagBody = document.createElement("div");
    flagBody.classList.add("mobbex-flag");

    // add flag parts
    const flagTop = document.createElement("div");
    flagTop.classList.add("mobbex-flag-top");

    flagTop.innerHTML = 
    "<span class='mobbex-flag-top-count' style='font-size:" 
    + (plan.count < 9 ? "2.5" : "1.85") + "rem'>" + plan.count + "</span>"
    + "<span class='mobbex-flag-top-text'>" + financeText(plan.percentage).replace(' ', "<br>") +"</span>";

    const flagBottom = document.createElement("div");
    flagBottom.classList.add("mobbex-flag-bottom");

    flagBottom.innerHTML = 
    "<span class='mobbex-flag-bottom-source'>Con " + plan.source + "</span>"

    // Build elements jerarchy
    flagBody.appendChild(flagTop);
    flagBody.appendChild(flagBottom);
 
    wrapper.appendChild(flagBody);
  }

  // Handles add banner
  function addFinanceBanner(product, ePrice, plan) {
    const priceElement = product.querySelector(ePrice);
    if (!priceElement) {
      console.error("No se encontró " + ePrice + " para añadir el elemento")
      return;
    }

    // create banner and its child elements
    const banner = document.createElement("div");

    banner.classList.add("mobbex-product-banner");
    const bannerTop = document.createElement("div");
    bannerTop.classList.add("mobbex-product-banner-top");

    const bannerBottom = document.createElement("div");
    bannerBottom.classList.add("mobbex-product-banner-bottom");

    if (plan.count > 1)
      bannerTop.innerHTML =
        "<span class='mobbex-installment-span-left'>Hasta</span><span class='mobbex-installment-span-right'>" +
        plan.count +
        " Cuotas</span>";
    else
      bannerTop.innerHTML =
        "<span class='mobbex-installment-span-left'>En</span><span class='mobbex-installment-span-right'>" +
        plan.count +
        " Pago</span>";
    
    bannerBottom.innerHTML = financeText(plan.percentage) + " de $" + plan.amount;
    priceElement.parentNode?.insertBefore(banner, priceElement);

    // build banner elements jerarchy
    banner.appendChild(bannerTop);
    banner.appendChild(bannerBottom);
  }

  function financeText(percentage) {
    if (!percentage)
      console.error(
        "No se encontró el porcentaje de financiación. Ver installment.totals.financial"
      )
    if (percentage == 0)
      return "Sin interés"
    if (percentage < 0)
      return "Con descuento"
    if (percentage > 0)
      return "Con interés"
  }

  displayProductTag();
});