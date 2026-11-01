{**
 * What a crawler got from one page.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<div class="gl-page">
  <div class="gl-page-top">
    <span class="gl-page-url">{$gl_p.url|escape:'html':'UTF-8'}</span>
    <small>{l s='%ms% ms, %kb% KB' sprintf=['%ms%' => $gl_p.ms, '%kb%' => ($gl_p.bytes / 1024)|round] d='Modules.Geolens.Admin'}</small>
    {if !$gl_p.problems}<span class="gl-pill gl-pill-ok"><i class="material-icons" aria-hidden="true">check</i>{l s='No problems' d='Modules.Geolens.Admin'}</span>{/if}
  </div>
  {if $gl_p.problems}
    <ul>
      {foreach $gl_p.problems as $x}
        <li>
          <i class="material-icons {if $x.level == 'error'}gl-ico-err{else}gl-ico-warn{/if}" aria-hidden="true">{if $x.level == 'error'}error_outline{else}warning_amber{/if}</i>
          <span>
            {if $x.code == 'noindex'}{l s='This page says noindex, so search engines and Copilot leave it out.' d='Modules.Geolens.Admin'}
            {elseif $x.code == 'nosnippet'}{l s='This page forbids snippets, which also removes it as a source for AI Overviews and AI Mode.' d='Modules.Geolens.Admin'}
            {elseif $x.code == 'noarchive'}{l s='This page says noarchive. Until 1 March 2027, Bing keeps such pages out of Copilot answers.' d='Modules.Geolens.Admin'}
            {elseif $x.code == 'nocache'}{l s='This page says nocache. Until 1 March 2027, Copilot uses only the title and snippet of such pages.' d='Modules.Geolens.Admin'}
            {elseif $x.code == 'too_big'}{l s='This page is %mb% MB. Googlebot stops reading at 2 MB.' sprintf=['%mb%' => $x.detail] d='Modules.Geolens.Admin'}
            {elseif $x.code == 'no_canonical'}{l s='No canonical address is declared.' d='Modules.Geolens.Admin'}
            {elseif $x.code == 'ld_invalid'}{l s='%count% JSON-LD blocks are broken, so crawlers ignore them.' sprintf=['%count%' => $x.detail] d='Modules.Geolens.Admin'}
            {elseif $x.code == 'ld_duplicate'}{l s='The page describes the product %count% times. A theme or another module is adding its own copy. Turn one off.' sprintf=['%count%' => $x.detail] d='Modules.Geolens.Admin'}
            {elseif $x.code == 'ld_duplicate_org'}{l s='The page describes your shop %count% times. Another module or the theme is adding its own copy.' sprintf=['%count%' => $x.detail] d='Modules.Geolens.Admin'}
            {elseif $x.code == 'ld_no_product'}{l s='This product page has no product markup.' d='Modules.Geolens.Admin'}
            {elseif $x.code == 'microdata_product'}{l s='The theme also describes the product in microdata, next to the JSON-LD. If the two disagree, engines may trust neither.' d='Modules.Geolens.Admin'}
            {elseif $x.code == 'no_offer'}{l s='The markup has no price, usually because prices are hidden on this page.' d='Modules.Geolens.Admin'}
            {elseif $x.code == 'no_availability'}{l s='The markup does not say whether the product is available.' d='Modules.Geolens.Admin'}
            {elseif $x.code == 'name_not_visible'}{l s='The product name in the markup is not in the page text a crawler reads.' d='Modules.Geolens.Admin'}
            {elseif $x.code == 'price_not_visible'}{l s='The price in the markup (%price%) is not in the page text a crawler reads. It may be loaded by JavaScript.' sprintf=['%price%' => $x.detail] d='Modules.Geolens.Admin'}
            {elseif $x.code == 'rolling_date'}{l s='The price is marked valid until about 15 days from today, a date PrestaShop makes up on every page.' d='Modules.Geolens.Admin'}
            {elseif $x.code == 'facts_not_visible'}{l s='The markup states delivery or returns, but the block showing them is not on this page. Your theme or page builder may leave out the displayProductAdditionalInfo hook. Add it, or state the same facts on the page yourself.' d='Modules.Geolens.Admin'}
            {elseif $x.code == 'unreachable'}{l s='The page answered with status %status%, not 200.' sprintf=['%status%' => $x.detail] d='Modules.Geolens.Admin'}
            {else}{$x.code|escape:'html':'UTF-8'}{/if}
          </span>
        </li>
      {/foreach}
    </ul>
  {/if}
  {if $gl_p.types}
    <p class="gl-types">{l s='Markup found:' d='Modules.Geolens.Admin'} {foreach $gl_p.types as $type => $n}{$type|escape:'html':'UTF-8'}{if $n > 1} x{$n|intval}{/if}{if !$n@last}, {/if}{/foreach}</p>
  {/if}
  {if $gl_p.json_ld}
    <details class="gl-numbers"><summary>{l s='See the product markup' d='Modules.Geolens.Admin'}</summary><pre class="gl-pre">{$gl_p.json_ld|escape:'html':'UTF-8'}</pre></details>
  {/if}
</div>
