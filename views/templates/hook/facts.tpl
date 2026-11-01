{**
 * Delivery and returns on the product page. Read by people and by crawlers that run no JavaScript.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<ul class="geolens-facts">
  {if $geolens_facts.shipping}
    <li>
      {if $geolens_facts.shipping.free}
        <strong>{l s='Free delivery' d='Modules.Geolens.Shop'}</strong>
      {else}
        <strong>{l s='Delivery: %amount%' sprintf=['%amount%' => $geolens_facts.shipping.rate_label] d='Modules.Geolens.Shop'}</strong>
        {if $geolens_facts.shipping.free_over_label}
          ({l s='free over %amount%' sprintf=['%amount%' => $geolens_facts.shipping.free_over_label] d='Modules.Geolens.Shop'})
        {/if}
      {/if}
      · {l s='arrives in %min%-%max% days' sprintf=['%min%' => $geolens_facts.shipping.min|intval, '%max%' => $geolens_facts.shipping.max|intval] d='Modules.Geolens.Shop'}
    </li>
  {/if}
  {if $geolens_facts.returns}
    <li>
      {if $geolens_facts.returns.mode == 'none'}
        {l s='Returns are not accepted on this product' d='Modules.Geolens.Shop'}
      {else}
        <strong>
          {if $geolens_facts.returns.mode == 'unlimited'}
            {l s='Returns at any time' d='Modules.Geolens.Shop'}
          {else}
            {l s='%days%-day returns' sprintf=['%days%' => $geolens_facts.returns.days|intval] d='Modules.Geolens.Shop'}
          {/if}
        </strong>
        {if $geolens_facts.returns.fees == 'free'}
          · {l s='free of charge' d='Modules.Geolens.Shop'}
        {elseif $geolens_facts.returns.fees == 'fixed'}
          · {l s='return fee %amount%' sprintf=['%amount%' => $geolens_facts.returns.fee_label] d='Modules.Geolens.Shop'}
        {else}
          · {l s='you pay the return postage' d='Modules.Geolens.Shop'}
        {/if}
      {/if}
    </li>
  {/if}
</ul>
