(function () {
  'use strict';

  var state = window.appinetProductAvailability;
  if (!state || !Number(state.idProduct)) return;

  var productId = Number(state.idProduct);
  var combinations = state.combinations || {};
  var controlSelector = '[data-button-action="add-to-cart"], .elementor-atc a, .elementor-atc button, .elementor-atc input[type="submit"], .elementor-widget-product-add-to-cart a[href="#ce-action=addToCart"]';

  function number(value) {
    var parsed = parseInt(value, 10);
    return isNaN(parsed) ? 0 : parsed;
  }

  function currentAttributes(scope) {
    var attributes = [];
    Array.prototype.forEach.call(scope.querySelectorAll('select[name^="group["] , input[name^="group["]:checked'), function (input) {
      var value = number(input.value);
      if (value) attributes.push(value);
    });
    return attributes.sort(function (a, b) { return a - b; });
  }

  function sameAttributes(left, right) {
    if (left.length !== right.length) return false;
    for (var index = 0; index < left.length; index += 1) {
      if (left[index] !== right[index]) return false;
    }
    return true;
  }

  function selectedCombination(scope) {
    var attributeInput = scope.querySelector('input[name="id_product_attribute"]');
    if (attributeInput && number(attributeInput.value)) return number(attributeInput.value);

    var selected = currentAttributes(scope);
    Object.keys(combinations).some(function (id) {
      if (sameAttributes(selected, combinations[id].attributes || [])) {
        selected = number(id);
        return true;
      }
      return false;
    });
    return Array.isArray(selected) ? 0 : selected;
  }

  function getScope(control) {
    return control.closest('form') || control.closest('[data-id-product], [data-product-id]') || document;
  }

  function belongsToCurrentProduct(control) {
    var container = control.closest('[data-id-product], [data-product-id]');
    if (container) {
      return number(container.getAttribute('data-id-product') || container.getAttribute('data-product-id')) === productId;
    }
    var form = control.closest('form');
    var productInput = form && form.querySelector('input[name="id_product"]');
    if (productInput) return number(productInput.value) === productId;

    // CE product widgets rendered in page context do not always emit id_product.
    return Boolean(control.closest('#content'));
  }

  function shouldDisable(control) {
    if (!state.allowBuying) return true;
    var combinationId = selectedCombination(getScope(control));
    return combinationId > 0 && combinations[combinationId] && !combinations[combinationId].available;
  }

  function setDisabled(control, disabled) {
    if (disabled) {
      control.classList.add('appinet-product-availability-disabled');
      control.setAttribute('aria-disabled', 'true');
      if (control.tagName === 'BUTTON' || control.tagName === 'INPUT') control.disabled = true;
      else control.setAttribute('tabindex', '-1');
      return;
    }
    if (!control.classList.contains('appinet-product-availability-disabled')) return;
    control.classList.remove('appinet-product-availability-disabled');
    control.removeAttribute('aria-disabled');
    if (control.tagName === 'BUTTON' || control.tagName === 'INPUT') control.disabled = false;
    else control.removeAttribute('tabindex');
  }

  function update(root) {
    var scope = root && root.querySelectorAll ? root : document;
    Array.prototype.forEach.call(scope.querySelectorAll(controlSelector), function (control) {
      if (belongsToCurrentProduct(control)) setDisabled(control, shouldDisable(control));
    });
  }

  document.addEventListener('DOMContentLoaded', function () { update(document); });
  document.addEventListener('change', function () { update(document); });
  document.addEventListener('appinet:product-updated', function () { update(document); });
  if (window.prestashop && typeof window.prestashop.on === 'function') {
    window.prestashop.on('updatedProduct', function () { update(document); });
  }
  document.addEventListener('click', function (event) {
    var control = event.target.closest('.appinet-product-availability-disabled');
    if (!control) return;
    event.preventDefault();
    event.stopImmediatePropagation();
  }, true);
  if (window.MutationObserver) {
    new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        Array.prototype.forEach.call(mutation.addedNodes, function (node) {
          if (node.nodeType === 1) update(node);
        });
      });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }
}());
