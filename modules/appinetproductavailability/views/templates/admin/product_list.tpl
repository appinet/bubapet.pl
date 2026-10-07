<div class="appinet-availability-admin">
    <div class="appinet-hero">
        <div>
            <p class="appinet-eyebrow">Panel kontroli dostępności</p>
            <h2>appiNET Product Availability</h2>
            <p class="appinet-hero-copy">
                Jeden widok do kontroli kombinacji ukrytych przez moduł, blokad zakupu oraz spójności z natywnym `out_of_stock` w PrestaShop.
            </p>
        </div>
        <div class="appinet-hero-actions">
            <a href="{$ajax_url}" class="btn btn-default">Odśwież panel</a>
            <a href="{$ajax_url}&view_mode=mismatch" class="btn btn-primary">Pokaż problemy synchronizacji</a>
        </div>
    </div>

    <div class="appinet-stats-grid">
        <div class="appinet-stat-card">
            <span class="appinet-stat-label">Produkty z kombinacjami</span>
            <strong class="appinet-stat-value">{$stats.total_products|default:0}</strong>
        </div>
        <div class="appinet-stat-card">
            <span class="appinet-stat-label">Łącznie kombinacji</span>
            <strong class="appinet-stat-value">{$stats.total_combinations|default:0}</strong>
        </div>
        <div class="appinet-stat-card appinet-stat-card-warning">
            <span class="appinet-stat-label">Kombinacje oznaczone jako niedostępne</span>
            <strong class="appinet-stat-value">{$stats.unavailable_combinations|default:0}</strong>
            <span class="appinet-stat-note">Produkty dotknięte: {$stats.products_with_unavailable|default:0}</span>
        </div>
        <div class="appinet-stat-card appinet-stat-card-danger">
            <span class="appinet-stat-label">Błędy `out_of_stock`</span>
            <strong class="appinet-stat-value">{$stats.out_of_stock_mismatches|default:0}</strong>
        </div>
        <div class="appinet-stat-card appinet-stat-card-danger">
            <span class="appinet-stat-label">Braki w `stock_available`</span>
            <strong class="appinet-stat-value">{$stats.missing_stock_rows|default:0}</strong>
        </div>
        <div class="appinet-stat-card">
            <span class="appinet-stat-label">Produkty z blokadą zakupu</span>
            <strong class="appinet-stat-value">{$stats.blocked_products|default:0}</strong>
        </div>
        <div class="appinet-stat-card">
            <span class="appinet-stat-label">Kombinacje z ETA</span>
            <strong class="appinet-stat-value">{$stats.combinations_with_eta|default:0}</strong>
        </div>
    </div>

    <div class="appinet-toolbar">
        <form method="get" action="{$ajax_url}" id="search-form" class="appinet-search-form">
            <input type="hidden" name="controller" value="AdminAppinetProductAvailability">
            <div class="appinet-search-inputs">
                <input class="form-control" type="text" name="search_query" value="{$searchQuery}" placeholder="{l s='Szukaj po nazwie produktu lub ID' mod='appinetproductavailability'}">
                <select name="view_mode" class="form-control">
                    <option value="all" {if $viewMode == 'all'}selected{/if}>Wszystkie produkty</option>
                    <option value="unavailable" {if $viewMode == 'unavailable'}selected{/if}>Z niedostępnymi kombinacjami</option>
                    <option value="mismatch" {if $viewMode == 'mismatch'}selected{/if}>Z błędami `out_of_stock`</option>
                    <option value="missing_stock" {if $viewMode == 'missing_stock'}selected{/if}>Bez rekordu `stock_available`</option>
                    <option value="blocked" {if $viewMode == 'blocked'}selected{/if}>Z zablokowanym zakupem</option>
                    <option value="eta" {if $viewMode == 'eta'}selected{/if}>Z ustawioną datą dostawy</option>
                </select>
            </div>
            <div class="appinet-search-actions">
                <button type="submit" class="btn btn-primary">{l s='Filtruj' mod='appinetproductavailability'}</button>
                {if $hasActiveFilters}
                    <a href="{$ajax_url}" class="btn btn-default">Wyczyść</a>
                {/if}
            </div>
        </form>

        <div class="appinet-filter-pills">
            {foreach from=$filterLinks item=filterLink}
                <a href="{$filterLink.url}" class="appinet-pill {if $filterLink.is_active}is-active{/if}">{$filterLink.label}</a>
            {/foreach}
        </div>
    </div>

    <div class="appinet-integrity-panel">
        <div class="appinet-section-heading">
            <div>
                <h3>Kontrola integralności</h3>
                <p>Najważniejsze rekordy wymagające sprawdzenia synchronizacji modułu z magazynem PrestaShop.</p>
            </div>
            <a href="{$ajax_url}&view_mode=mismatch" class="btn btn-default">Zobacz tylko konflikty</a>
        </div>

        {if $integrityIssues|@count > 0}
            <div class="table-responsive">
                <table class="table appinet-table appinet-issues-table">
                    <thead>
                    <tr>
                        <th>Produkt</th>
                        <th>Kombinacja</th>
                        <th>Moduł</th>
                        <th>`out_of_stock`</th>
                        <th>Oczekiwane</th>
                        <th>ETA</th>
                        <th>Akcja</th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach from=$integrityIssues item=issue}
                        <tr class="{if $issue.is_missing_stock_row}is-danger{else}is-warning{/if}">
                            <td>
                                <strong>#{$issue.id_product}</strong><br>
                                {$issue.product_name}
                            </td>
                            <td>{$issue.combination_name|default:'-'}</td>
                            <td>{if $issue.available|intval}Widoczna{else}Niedostępna{/if}</td>
                            <td>
                                {if $issue.is_missing_stock_row}
                                    <span class="appinet-badge appinet-badge-danger">brak rekordu</span>
                                {else}
                                    {$issue.out_of_stock}
                                {/if}
                            </td>
                            <td>{$issue.expected_out_of_stock}</td>
                            <td>{if $issue.estimated_delivery_time}{$issue.estimated_delivery_time}{else}-{/if}</td>
                            <td><a href="{$issue.edit_link}" class="btn btn-default" target="_blank" rel="noopener">Edytuj produkt</a></td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        {else}
            <div class="appinet-empty-state appinet-empty-state-success">
                Nie wykryto problemów synchronizacji `available` z `stock_available.out_of_stock`.
            </div>
        {/if}
    </div>

    <div class="appinet-section-heading">
        <div>
            <h3>Produkty w zakresie filtrów</h3>
            <p>{$totalPages} stron wyników. Aktualny widok pokazuje produkty z podsumowaniem statusów kombinacji i magazynu.</p>
        </div>
    </div>

    {if $products|@count > 0}
        <div class="appinet-product-grid">
            {foreach from=$products item=product}
                <section class="appinet-product-card tone-{$product.status_tone}">
                    <div class="appinet-product-header">
                        <div>
                            <p class="appinet-product-id">Produkt #{$product.id_product}</p>
                            <h4>{$product.name}</h4>
                        </div>
                        <div class="appinet-product-actions">
                            <a href="{$product.edit_link}" class="btn btn-default" target="_blank" rel="noopener">Otwórz produkt</a>
                        </div>
                    </div>

                    <div class="appinet-product-meta">
                        <span class="appinet-badge">Kombinacje: {$product.combinations_count}</span>
                        <span class="appinet-badge {if $product.unavailable_count|intval > 0}appinet-badge-warning{/if}">Niedostępne: {$product.unavailable_count}</span>
                        <span class="appinet-badge {if $product.mismatch_count|intval > 0}appinet-badge-danger{/if}">Błędy `out_of_stock`: {$product.mismatch_count}</span>
                        <span class="appinet-badge {if $product.missing_stock_rows|intval > 0}appinet-badge-danger{/if}">Braki magazynu: {$product.missing_stock_rows}</span>
                        <span class="appinet-badge {if $product.product_blocked|intval}appinet-badge-warning{/if}">Zakup: {if $product.product_blocked|intval}zablokowany{else}aktywny{/if}</span>
                        <span class="appinet-badge">ETA: {$product.eta_count}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table appinet-table">
                            <thead>
                            <tr>
                                <th>Kombinacja</th>
                                <th>Status modułu</th>
                                <th>Stan magazynu</th>
                                <th>`out_of_stock`</th>
                                <th>ETA</th>
                            </tr>
                            </thead>
                            <tbody>
                            {foreach from=$product.combinations item=combination}
                                <tr class="{if $combination.has_out_of_stock_mismatch|intval || $combination.is_missing_stock_row|intval}is-danger{elseif !$combination.available|intval}is-warning{/if}">
                                    <td>
                                        <strong>{$combination.name|default:'Kombinacja bez nazwy'}</strong><br>
                                        <small>ID atrybutu: {$combination.id_product_attribute}</small>
                                    </td>
                                    <td>
                                        <select class="availability-select form-control" data-id-product="{$product.id_product}" data-id-attribute="{$combination.id_product_attribute}">
                                            <option value="1" {if $combination.available|intval}selected{/if}>Dostępna</option>
                                            <option value="0" {if !$combination.available|intval}selected{/if}>Niedostępna</option>
                                        </select>
                                    </td>
                                    <td>
                                        {if $combination.is_missing_stock_row|intval}
                                            <span class="appinet-badge appinet-badge-danger">Brak `stock_available`</span>
                                        {else}
                                            Ilość: {$combination.quantity|intval}
                                        {/if}
                                    </td>
                                    <td>
                                        {if $combination.is_missing_stock_row|intval}
                                            <span class="appinet-badge appinet-badge-danger">brak</span>
                                        {else}
                                            <span class="appinet-inline-metric">
                                                <strong>{$combination.out_of_stock}</strong>
                                                <small>oczekiwane {$combination.expected_out_of_stock}</small>
                                            </span>
                                        {/if}
                                        {if $combination.has_out_of_stock_mismatch|intval}
                                            <span class="appinet-badge appinet-badge-danger">niespójne</span>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $combination.estimated_delivery_time}
                                            {$combination.estimated_delivery_time}
                                        {else}
                                            -
                                        {/if}
                                    </td>
                                </tr>
                            {/foreach}
                            </tbody>
                        </table>
                    </div>
                </section>
            {/foreach}
        </div>

        {if $pagination|@count > 1}
            <div class="appinet-pagination">
                {foreach from=$pagination item=pageData}
                    {if $pageData.is_current}
                        <span class="appinet-page is-current">{$pageData.page}</span>
                    {else}
                        <a href="{$pageData.url}" class="appinet-page">{$pageData.page}</a>
                    {/if}
                {/foreach}
            </div>
        {/if}
    {else}
        <div class="appinet-empty-state">
            Brak produktów pasujących do wybranych filtrów.
        </div>
    {/if}
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.availability-select').forEach(select => {
            select.addEventListener('change', function() {
                const idProduct = this.getAttribute('data-id-product');
                const idAttribute = this.getAttribute('data-id-attribute');
                const available = this.value;
                const token = '{$token}';

                fetch('{$ajax_url}&ajax=1&action=UpdateAvailability', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'id_product=' + idProduct + '&id_product_attribute=' + idAttribute + '&available=' + available + '&token=' + token
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('success', '{l s="Dostępność zaktualizowana. Odświeżam widok..." mod="appinetproductavailability"}');
                            window.setTimeout(function() {
                                window.location.reload();
                            }, 450);
                        } else {
                            showNotification('danger', '{l s="Błąd podczas aktualizowania dostępności." mod="appinetproductavailability"}');
                        }
                    })
                    .catch(function() {
                        showNotification('danger', '{l s="Wystąpił nieoczekiwany błąd." mod="appinetproductavailability"}');
                    });
            });
        });

        document.getElementById("search-form").addEventListener("submit", function(e) {
            e.preventDefault();
            const searchQuery = document.querySelector("[name='search_query']").value;
            const viewMode = document.querySelector("[name='view_mode']").value;
            window.location.href = "{$ajax_url}&search_query=" + encodeURIComponent(searchQuery) + "&view_mode=" + encodeURIComponent(viewMode);
        });
    });

    function showNotification(type, message) {
        $.growl({
            title: "",
            message: message
        }, {
            type: type,
            allow_dismiss: true,
            placement: {
                from: "top",
                align: "right"
            },
            delay: 4000,
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
