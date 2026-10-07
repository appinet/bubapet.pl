<div class="appinet-cart-availability-warning" role="alert">
  <strong>Niektóre produkty w koszyku nie są już dostępne.</strong>
  <span>Usuń poniższe pozycje, aby przejść do płatności:</span>
  <ul>
    {foreach from=$unavailable_cart_products item=product}
      <li>{$product.name|escape:'htmlall':'UTF-8'}</li>
    {/foreach}
  </ul>
</div>
