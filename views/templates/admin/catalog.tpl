{**
 * Catalogue facts, one by one. No blended score on purpose.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{if !$gl_a.total}
  <div class="gl-empty"><i class="material-icons" aria-hidden="true">inventory_2</i><h4>{l s='No active products to check yet.' d='Modules.Geolens.Admin'}</h4></div>
{else}
  <div class="gl-facts">
    {foreach $gl_fact_rows as $r}
      <div class="gl-fact">
        <div>
          {if $r.fact == 'price'}{l s='Has a price' d='Modules.Geolens.Admin'}
          {elseif $r.fact == 'brand'}{l s='Names a brand' d='Modules.Geolens.Admin'}
          {elseif $r.fact == 'gtin'}{l s='Has a GTIN (EAN, UPC, ISBN)' d='Modules.Geolens.Admin'}
          {elseif $r.fact == 'gtin_valid'}{l s='GTIN passes its check digit' d='Modules.Geolens.Admin'}
          {elseif $r.fact == 'reference'}{l s='Has a reference or MPN' d='Modules.Geolens.Admin'}
          {elseif $r.fact == 'short'}{l s='Has a short description' d='Modules.Geolens.Admin'}
          {elseif $r.fact == 'long'}{l s='Has a full description' d='Modules.Geolens.Admin'}
          {elseif $r.fact == 'features'}{l s='Has features (specifications)' d='Modules.Geolens.Admin'}
          {elseif $r.fact == 'image'}{l s='Has a cover image' d='Modules.Geolens.Admin'}
          {elseif $r.fact == 'alt'}{l s='Cover image has a caption' d='Modules.Geolens.Admin'}
          {elseif $r.fact == 'category'}{l s='Is in a real category' d='Modules.Geolens.Admin'}{/if}
          <small>{l s='%count% of %total%' sprintf=['%count%' => $r.has, '%total%' => $r.of] d='Modules.Geolens.Admin'}</small>
        </div>
        <div class="gl-meter" role="img" aria-label="{$r.percent|intval}%"><span style="width:{$r.percent|intval}%"></span></div>
        <div class="gl-fact-n">{$r.percent|intval}%</div>
        <div style="text-align:right">{if $r.gaps}<button type="button" class="gl-btn gl-btn-secondary gl-btn-sm" data-missing="{$r.fact|escape:'html':'UTF-8'}">{l s='See products' d='Modules.Geolens.Admin'}</button>{/if}</div>
      </div>
    {/foreach}
  </div>
  <p class="gl-floor">
    <i class="material-icons" aria-hidden="true">info</i>
    <span>
      {l s='%count% of %total% products in stock (simple products).' sprintf=['%count%' => $gl_in_stock, '%total%' => $gl_of] d='Modules.Geolens.Admin'}
      {if $gl_reviews !== null}{l s='%count% have at least one approved review.' sprintf=['%count%' => $gl_reviews] d='Modules.Geolens.Admin'}{/if}
      {if $gl_a.image_sample.checked}{l s='In a random sample of %checked% cover images, %small% are under 500 x 500 pixels, the minimum Merchant Center requires from 31 January 2027.' sprintf=['%checked%' => $gl_a.image_sample.checked, '%small%' => $gl_a.image_sample.small] d='Modules.Geolens.Admin'}{/if}
    </span>
  </p>
  <div class="gl-actions">
    <small>{if $gl_age > 0}{l s='Checked %minutes% minutes ago.' sprintf=['%minutes%' => $gl_age] d='Modules.Geolens.Admin'}{else}{l s='Checked just now.' d='Modules.Geolens.Admin'}{/if}</small>
    <button type="button" class="gl-btn gl-btn-ghost gl-btn-sm" data-refresh="1"><i class="material-icons" aria-hidden="true">refresh</i>{l s='Check again' d='Modules.Geolens.Admin'}</button>
  </div>
{/if}
