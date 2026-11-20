(function () {
  function sameId(left, right) {
    return Number(left) === Number(right);
  }

  function findById(items, id) {
    return (
      items.find(function (item) {
        return sameId(item.id, id);
      }) || null
    );
  }

  function mount(root) {
    var index = Number(root.getAttribute("data-index"));
    var data = window.mopicoOilFunnels && window.mopicoOilFunnels[index];
    if (!data) {
      var message = root.querySelector(".mopico-oil-message");
      if (message) {
        message.textContent = "داده‌های فانل بار نشد.";
      }
      return;
    }
    var state = {
      step: 0,
      groupId: 0,
      brandId: 0,
      modelId: 0,
      variantId: 0,
    };
    var steps = root.querySelector(".mopico-oil-steps");
    var stage = root.querySelector(".mopico-oil-stage");
    var nav = root.querySelector(".mopico-oil-nav");
    var message = root.querySelector(".mopico-oil-message");

    function selectionForStep(step) {
      if (step === 0) {
        return state.groupId;
      }
      if (step === 1) {
        return state.brandId;
      }
      if (step === 2) {
        return state.modelId;
      }
      if (step === 3) {
        return state.variantId;
      }
      return 0;
    }

    function cardsForStep(step) {
      if (step === 0) {
        return data.groups;
      }
      if (step === 1) {
        return data.brands.filter(function (brand) {
          return sameId(brand.groupId, state.groupId);
        });
      }
      if (step === 2) {
        return data.models.filter(function (model) {
          return sameId(model.brandId, state.brandId);
        });
      }
      if (step === 3) {
        return data.variants.filter(function (variant) {
          return sameId(variant.modelId, state.modelId);
        });
      }
      return [];
    }

    function choose(id) {
      if (state.step === 0) {
        if (!sameId(state.groupId, id)) {
          state.brandId = 0;
          state.modelId = 0;
          state.variantId = 0;
        }
        state.groupId = id;
      } else if (state.step === 1) {
        if (!sameId(state.brandId, id)) {
          state.modelId = 0;
          state.variantId = 0;
        }
        state.brandId = id;
      } else if (state.step === 2) {
        if (!sameId(state.modelId, id)) {
          state.variantId = 0;
        }
        state.modelId = id;
      } else if (state.step === 3) {
        state.variantId = id;
      }
      message.textContent = "";
      paint();
    }

    function go(nextStep) {
      state.step = nextStep;
      message.textContent = "";
      paint();
    }

    function paintSteps() {
      steps.replaceChildren();
      data.ui.steps.forEach(function (label, index) {
        var button = document.createElement("button");
        button.type = "button";
        button.className = "mopico-oil-step";
        button.textContent = label;
        if (index === state.step) {
          button.classList.add("is-active");
        }
        if (index < state.step) {
          button.classList.add("is-done");
        }
        button.disabled = index > state.step;
        button.addEventListener("click", function () {
          if (index <= state.step) {
            go(index);
          }
        });
        steps.appendChild(button);
      });
    }

    function cardButton(item) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = "mopico-oil-card";
      if (sameId(item.id, selectionForStep(state.step))) {
        button.classList.add("is-selected");
      }
      if (item.image) {
        var image = document.createElement("img");
        image.src = item.image;
        image.alt = "";
        button.appendChild(image);
      }
      var title = document.createElement("span");
      title.textContent = item.title;
      button.appendChild(title);
      button.addEventListener("click", function () {
        choose(item.id);
      });
      return button;
    }

    function paintCards() {
      var items = cardsForStep(state.step);
      stage.replaceChildren();
      if (items.length === 0) {
        var empty = document.createElement("p");
        empty.className = "mopico-oil-empty";
        empty.textContent = data.ui.emptyStep;
        stage.appendChild(empty);
        return;
      }
      var grid = document.createElement("div");
      grid.className = "mopico-oil-grid";
      items.forEach(function (item) {
        grid.appendChild(cardButton(item));
      });
      stage.appendChild(grid);
    }

    function productLink(product) {
      var link = document.createElement("a");
      link.className = "mopico-oil-product";
      link.href = product.url;
      if (product.image) {
        var image = document.createElement("img");
        image.src = product.image;
        image.alt = "";
        link.appendChild(image);
      }
      var title = document.createElement("span");
      title.className = "mopico-oil-product-title";
      title.textContent = product.title;
      link.appendChild(title);
      if (product.subtitle) {
        var subtitle = document.createElement("span");
        subtitle.className = "mopico-oil-product-line";
        subtitle.textContent = product.subtitle;
        link.appendChild(subtitle);
      }
      return link;
    }

    function paintResults() {
      var variant = findById(data.variants, state.variantId);
      var model = findById(data.models, state.modelId);
      var brand = findById(data.brands, state.brandId);
      var group = findById(data.groups, state.groupId);
      stage.replaceChildren();
      if (!variant) {
        var missing = document.createElement("p");
        missing.className = "mopico-oil-empty";
        missing.textContent = data.ui.missingVariant;
        stage.appendChild(missing);
        return;
      }

      var summary = document.createElement("article");
      summary.className = "mopico-oil-summary";
      var imageUrl = variant.image || (model && model.image) || "";
      if (imageUrl) {
        var image = document.createElement("img");
        image.src = imageUrl;
        image.alt = "";
        summary.appendChild(image);
      }
      var copy = document.createElement("div");
      var kicker = document.createElement("p");
      kicker.className = "mopico-oil-kicker";
      kicker.textContent = [group, brand, model]
        .filter(Boolean)
        .map(function (item) {
          return item.title;
        })
        .join(" / ");
      var heading = document.createElement("h2");
      heading.textContent = variant.title;
      copy.appendChild(kicker);
      copy.appendChild(heading);
      if (variant.specs.length) {
        var list = document.createElement("ul");
        variant.specs.forEach(function (spec) {
          var item = document.createElement("li");
          item.textContent = spec.label + ": " + spec.value;
          list.appendChild(item);
        });
        copy.appendChild(list);
      }
      summary.appendChild(copy);
      stage.appendChild(summary);

      if (!variant.fluids.length) {
        var empty = document.createElement("p");
        empty.className = "mopico-oil-empty";
        empty.textContent = data.ui.emptyProducts;
        stage.appendChild(empty);
        return;
      }

      variant.fluids.forEach(function (fluid) {
        var section = document.createElement("section");
        section.className = "mopico-oil-fluid-result";
        var head = document.createElement("div");
        head.className = "mopico-oil-fluid-head";
        var titleWrap = document.createElement("div");
        var title = document.createElement("h3");
        title.textContent = fluid.label;
        titleWrap.appendChild(title);
        head.appendChild(titleWrap);
        if (fluid.volume) {
          var volume = document.createElement("div");
          volume.className = "mopico-oil-volume";
          var volumeLabel = document.createElement("span");
          volumeLabel.textContent = data.ui.volume;
          var volumeValue = document.createElement("strong");
          volumeValue.textContent = fluid.volume;
          volume.appendChild(volumeLabel);
          volume.appendChild(volumeValue);
          head.appendChild(volume);
        }
        var row = document.createElement("div");
        row.className = "mopico-oil-products";
        fluid.products.forEach(function (product) {
          row.appendChild(productLink(product));
        });
        section.appendChild(head);
        section.appendChild(row);
        stage.appendChild(section);
      });
    }

    function paintNav() {
      nav.replaceChildren();
      var back = document.createElement("button");
      back.type = "button";
      back.className = "is-back";
      back.textContent = data.ui.back;
      back.disabled = state.step === 0;
      back.addEventListener("click", function () {
        if (state.step > 0) {
          go(state.step - 1);
        }
      });

      var next = document.createElement("button");
      next.type = "button";
      next.textContent = data.ui.next;
      next.hidden = state.step === data.ui.steps.length - 1;
      next.disabled = !selectionForStep(state.step);
      next.addEventListener("click", function () {
        if (!selectionForStep(state.step)) {
          message.textContent = data.ui.pick;
          return;
        }
        go(state.step + 1);
      });

      nav.appendChild(next);
      nav.appendChild(back);
    }

    function paint() {
      paintSteps();
      if (state.step === data.ui.steps.length - 1) {
        paintResults();
      } else {
        paintCards();
      }
      paintNav();
    }

    paint();
  }

  function boot() {
    document.querySelectorAll("[data-mopico-oil]").forEach(mount);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
