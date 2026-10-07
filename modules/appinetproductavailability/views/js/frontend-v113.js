(function () {
  'use strict';

  var state = window.appinetProductAvailability;
  if (!state || !Number(state.idProduct)) return;

  var productId = Number(state.idProduct);
  var selector = '[data-button-action="add-to-cart"], .elementor-atc a, .elementor-atc button, .elementor-atc input[type="submit"]';

  function asNumber(value) {
    var result = parseInt(value, 10);
    return isNaN(result) ? 0 : result;
  }

  function scopeFor(control) {
    return control.closest('form') || control.closest('[data-id-product], [data-product-id]') || document;
  }

  function belongsToCurrentProduct(control) {
    var container = control.closest('[data-id-product], [data-product-id]');
    if (container) {
      return asNumber(container.getAttribute('data-id-product') || container.getAttribute('data-product-id')) === productId;
    }

    var form = control.closest('form');
    var productInput = form && form.querySelector('input[name="id_product"]');
    return productInput ? asNumber(productInput.value) === productId : Boolean(control.closest('#content'));
  }

  function selectedAttribute(scope) {
    var input = scope.querySelector('input[name="id_product_attribute"]');
    return input ? asNumber(input.value) : 0;
  }

  function isBlocked(control) {
    if (!state.allowBuying) return true;
    var attributeId = selectedAttribute(scopeFor(control));
    var combination = attributeId && state.combinations && state.combinations[attributeId];
    return Boolean(combination && !combination.available);
  }

  function apply(control) {
    if (!belongsToCurrentProduct(control)) return;
    var blocked = isBlocked(control);
    if (blocked) {
      control.classList.add('appinet-product-availability-disabled');
      control.setAttribute('aria-disabled', 'true');
      if (/^(BUTTON|INPUT)$/.test(control.tagName)) {
        control.disabled = true;
      } else {
        control.setAttribute('tabindex', '-1');
      }
      return;
    }

    if (!control.classList.contains('appinet-product-availability-disabled')) return;
    control.classList.remove('appinet-product-availability-disabled');
    control.removeAttribute('aria-disabled');
    if (/^(BUTTON|INPUT)$/.test(control.tagName)) {
      control.disabled = false;
    } else {
      control.removeAttribute('tabindex');
    }
  }

  function refresh(root) {
    var target = root && root.querySelectorAll ? root : document;
    if (target.matches && target.matches(selector)) apply(target);
    Array.prototype.forEach.call(target.querySelectorAll(selector), apply);
  }

  document.addEventListener('DOMContentLoaded', function () { refresh(document); });
  document.addEventListener('change', function () { refresh(document); });
  if (window.prestashop && typeof window.prestashop.on === 'function') {
    window.prestashop.on('updatedProduct', function () { refresh(document); });
  }
  document.addEventListener('click', function (event) {
    var control = event.target.closest('.appinet-product-availability-disabled');
    if (!control) return;
    event.preventDefault();
    event.stopImmediatePropagation();
  }, true);
  if (window.MutationObserver) {
    new MutationObserver(function (changes) {
      changes.forEach(function (change) {
        Array.prototype.forEach.call(change.addedNodes, function (node) {
          if (node.nodeType === 1) refresh(node);
        });
      });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }
}());
