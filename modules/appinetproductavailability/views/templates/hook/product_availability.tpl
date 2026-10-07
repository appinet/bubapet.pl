{if isset($product_combinations) && $product_combinations|@count > 0}
    <div id="product-availability">
        {if isset($product_combinations[0]) && $product_combinations[0].available|intval == 0}
            <span>Oczekujemy na dostawę:</span>
        {/if}
        <ul class="pl-2">
    {foreach $product_combinations as $pc}
        {if $pc.available|intval == 0}
            <li class="text-warning mb-0">{$pc.estimated_delivery_time} / {$pc.attribute_name}</li>
        {/if}
    {/foreach}
        </ul>
    </div>
{/if}
<script>

    document.addEventListener('DOMContentLoaded', function () {
        let showSellBtn = {$showSellBtn|intval};
        let allowBuying = {$allowBuying|intval};

        if (showSellBtn === 0 || allowBuying === 0) {
            let addToCartBtn = document.querySelector('.add-to-cart'); // lub inny selektor, zależnie od motywu
            if (addToCartBtn) {
                addToCartBtn.disabled = true;
                addToCartBtn.textContent = 'Niedostępny';
                addToCartBtn.style.opacity = '0.5';
                addToCartBtn.style.cursor = 'not-allowed';
            }
            let product = document.querySelector('.product-add-to-cart')
            if (product) {
                product.style.display = 'none';
            }
        }
    });
</script>
<style>
    #product-availability { margin-top: 15px; font-size: 16px; }
    .text-success { color: green; font-weight: bold; }
    .text-warning { color: red; font-weight: 300;}
</style>
