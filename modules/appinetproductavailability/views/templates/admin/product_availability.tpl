<div class="panel">
    <h3>{l s='Product Availability' mod='appinetproductavailability'}</h3>
        {if isset($combinations) && count($combinations) > 0 }
            <h4>{l s='Combinations' mod='appinetproductavailability'} - {count($combinations)}</h4>
            <div class="alert alert-info col-12 col-sm-8 col-md-5">
                Jeśli wybór widoczności kombinacji jest ustawiony na "-- Brak wyboru --" to oznacza ,że kombinacja jest widoczna.
            </div>
            <div class="col-12 col-sm-8 col-md-6 col-xl-4">
                {foreach from=$combinations item=combination}

                        <div class="row mb-2">

                        <div class="col-12 col-sm-8 col-md-12 d-md-flex align-items-center justify-content-center">
                            <div class="mr-2">

                                <label for="availability-select_{$id_product}_{$combination.id_product_attribute}" class="label">{$combination.attribute_name}</label>
                                <select id="availability-select_{$id_product}_{$combination.id_product_attribute}" class="availability-select form-control" data-id-product="{$id_product}" data-id-attribute="{$combination.id_product_attribute}">
                                    <option {if isset($availability[$combination.id_product_attribute])} selected{/if} value="">-- Brak wyboru--</option>
                                    <option value="1" {if isset($availability[$combination.id_product_attribute]) && $availability[$combination.id_product_attribute].display }selected{/if}>{l s='Yes' mod='appinetproductavailability'}</option>
                                    <option value="0" {if isset($availability[$combination.id_product_attribute]) && !$availability[$combination.id_product_attribute].display }selected{/if}>{l s='No' mod='appinetproductavailability'}</option>
                                </select>
                            </div>
                            <div>

                                <label for="estimated_delivery_time_{$id_product}_{$combination.id_product_attribute}">Przewidywany czas dostawy:</label>
                                <input
                                        value="{$availability[$combination.id_product_attribute].estimated_delivery_time}"
                                        data-id-product="{$id_product}"
                                        data-id-attribute="{$combination.id_product_attribute}"
                                        type="datetime-local"
                                        name="estimated_delivery_time"
                                        id="estimated_delivery_time_{$id_product}_{$combination.id_product_attribute}"
                                        class="form-control estimated_delivery_time"
                                />
                            </div>
                        </div>
                    </div>

                {/foreach}
            </div>
            {else}
            <h2>Brak kombinacji</h2>
        {/if}
    <h4 class="mt-3">{l s='Widoczność całego produktu:' mod='appinetproductavailability'}</h4>
    
    <div class="col-12 col-sm-8 col-md-6 col-xl-4 d-md-flex align-items-center justify-content-between">
        <div>
            <label for="product_allow_buying">Produkt niedostępny do zakupu:</label>
            <select name="product_allow_buying" id="product_allow_buying" class="form-control" data-id-product="{$id_product}">
                <option value="1" {if (int)$productsAllowBuing.allow_buying} selected{/if}>Zezwalaj na zakup</option>
                <option value="0" {if !(int)$productsAllowBuing.allow_buying} selected{/if}>Nie zezwalaj na zakup</option>
            </select>
        </div>
        <div>
            <label for="estimated_delivery_time_all">Przewidywany czas dostawy:</label>
            <input
                    value="{$productsAllowBuing.estimated_delivery_time_all}"
                    data-id-product="{$id_product}"
                    type="datetime-local"
                    name="estimated_delivery_time_all"
                    id="estimated_delivery_time_all"
                    class="form-control estimated_delivery_time_all"
            />
        </div>
    </div>


</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.availability-select').forEach(select => {
            select.addEventListener('change', function() {
                if(this.value === '') return;
                const idProduct = this.getAttribute('data-id-product');
                const idAttribute = this.getAttribute('data-id-attribute');
                const available = this.value;
                const token = '{$token}';

                fetch('{$ajax_url}&ajax=1&action=UpdateAvailability', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'id_product='+idProduct+'&id_product_attribute='+idAttribute+'&available='+available+'&token='+token+''
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('success', '{l s="Dostępność zaktualizowana pomyślnie!" mod="appinetproductavailability"}');
                        } else {
                            showNotification('danger', '{l s="Błąd podczas aktualizowania dostępności!" mod="appinetproductavailability"}');
                        }
                    })
                    .catch(error => {
                        showNotification('danger', '{l s="Wystąpił nieoczekiwany błąd!" mod="appinetproductavailability"}');
                    });
            });
        });
        document.querySelectorAll('.estimated_delivery_time').forEach(data=>{
            data.addEventListener('change', function(){
                if (this.value === '') {
                    const idProduct = this.getAttribute('data-id-product');
                    const idAttribute = this.getAttribute('data-id-attribute');
                    const token = '{$token}';

                    fetch('{$ajax_url}&ajax=1&action=DeleteAvailabilityDate', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'id_product=' + idProduct + '&id_product_attribute=' + idAttribute + '&token=' + token
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification('success', '{l s="Data została usunięta!" mod="appinetproductavailability"}');
                            } else {
                                showNotification('danger', '{l s="Błąd podczas usuwania daty!" mod="appinetproductavailability"}');
                            }
                        })
                        .catch(error => {
                            showNotification('danger', '{l s="Wystąpił nieoczekiwany błąd!" mod="appinetproductavailability"}');
                        });

                    return;
                }

                const idProduct = this.getAttribute('data-id-product');
                const idAttribute = this.getAttribute('data-id-attribute');
                const available = this.value;
                const token = '{$token}';

                fetch('{$ajax_url}&ajax=1&action=UpdateAvailabilityDate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'id_product='+idProduct+'&id_product_attribute='+idAttribute+'&date='+available+'&token='+token+''
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('success', '{l s="Data zaktualizowana pomyślnie!" mod="appinetproductavailability"}');
                        } else {
                            showNotification('danger', '{l s="Błąd podczas aktualizowania daty!" mod="appinetproductavailability"}');
                        }
                    })
                    .catch(error => {
                        showNotification('danger', '{l s="Wystąpił nieoczekiwany błąd!" mod="appinetproductavailability"}');
                    });

            })

        })
        let product_allow_buying =  document.querySelector('#product_allow_buying')
            product_allow_buying.addEventListener('change', function(){
            const idProduct = this.getAttribute('data-id-product');
            const allow = this.value;
            const token = '{$token}';

            fetch('{$ajax_url}&ajax=1&action=UpdateAllowBuying', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'id_product='+idProduct+'&allow='+allow+'&token='+token+''
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('success', '{l s="Zaktualizowano pomyślnie!" mod="appinetproductavailability"}');
                    } else {
                        showNotification('danger', '{l s="Błąd podczas aktualizowania!" mod="appinetproductavailability"}');
                    }
                })
                .catch(error => {
                    showNotification('danger', '{l s="Wystąpił nieoczekiwany błąd!" mod="appinetproductavailability"}');
                });
        })

        let estimated_delivery_time_all = document.querySelector('#estimated_delivery_time_all')
            estimated_delivery_time_all.addEventListener('change', function(){
                if (this.value === '') {
                    const idProduct = this.getAttribute('data-id-product');
                    const token = '{$token}';

                    fetch('{$ajax_url}&ajax=1&action=DeleteAvailabilityDateAll', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'id_product=' + idProduct + '&token=' + token
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification('success', '{l s="Data została usunięta!" mod="appinetproductavailability"}');
                            } else {
                                showNotification('danger', '{l s="Błąd podczas usuwania daty!" mod="appinetproductavailability"}');
                            }
                        })
                        .catch(error => {
                            showNotification('danger', '{l s="Wystąpił nieoczekiwany błąd!" mod="appinetproductavailability"}');
                        });

                    return;
                }

                const idProduct = this.getAttribute('data-id-product');
                const available = this.value;
                const token = '{$token}';

                fetch('{$ajax_url}&ajax=1&action=UpdateAvailabilityDateAll', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'id_product='+idProduct+'&date='+available+'&token='+token+''
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('success', '{l s="Data zaktualizowana pomyślnie!" mod="appinetproductavailability"}');
                        } else {
                            showNotification('danger', '{l s="Błąd podczas aktualizowania daty!" mod="appinetproductavailability"}');
                        }
                    })
                    .catch(error => {
                        showNotification('danger', '{l s="Wystąpił nieoczekiwany błąd!" mod="appinetproductavailability"}');
                    });

            })

    });

    function showNotification(type, message) {
        $.growl({
            title: "",
            message: message
        },{
            type: type, // Typ powiadomienia: success, danger, warning, info
            allow_dismiss: true,
            placement: {
                from: "top",
                align: "right"
            },
            delay: 4000, // Czas wyświetlania
            offset: {
                x: 30,
                y: 70
            },
            animate: {
                enter: 'animated fadeInDown',
                exit: 'animated fadeOutUp'
            }
        });
    }
</script>