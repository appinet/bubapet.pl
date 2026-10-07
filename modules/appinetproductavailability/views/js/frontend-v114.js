(function () {
  'use strict';

  var state = window.appinetProductAvailability;
  if (!state || !Number(state.idProduct) || state.allowBuying) return;

  var productId = Number(state.idProduct);
  var selector = [
    '[data-button-action="add-to-cart"]',
    '.elementor-atc a',
    '.elementor-atc button',
    '.elementor-atc input[type="submit"]',
    '.elementor-widget-product-add-to-cart a[href="#ce-action=addToCart"]'
  ].join(', ');

  function number(value) {
    var result = parseInt(value, 10);
    return isNaN(result) ? 0 : result;
  }

  function belongsToProductPage(control) {
    var container = control.closest('[data-id-product], [data-product-id]');
    if (container) {
      return number(container.getAttribute('data-id-product') || container.getAttribute('data-product-id')) === productId;
    }

    var form = control.closest('form');
    var productInput = form && form.querySelector('input[name="id_product"]');
    return productInput ? number(productInput.value) === productId : Boolean(control.closest('#content'));
  }

  function disable(control) {
    if (!belongsToProductPage(control)) return;
    control.classList.add('appinet-product-availability-disabled');
    control.setAttribute('aria-disabled', 'true');
    if (/^(BUTTON|INPUT)$/.test(control.tagName)) {
      control.disabled = true;
    } else {
      control.setAttribute('tabindex', '-1');
    }
  }

  function refresh(root) {
    var target = root && root.querySelectorAll ? root : document;
    if (target.matches && target.matches(selector)) disable(target);
    Array.prototype.forEach.call(target.querySelectorAll(selector), disable);
  }

  document.addEventListener('DOMContentLoaded', function () { refresh(document); });
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
