{**
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<div class="gl-card gl-card-first">
  <div class="gl-card-head">
    <div>
      <h3>{l s='What your catalogue states' d='Modules.Geolens.Admin'}</h3>
      <p class="gl-card-intro">{l s='Stated price and specifications are the best supported on-page signal in the research, and Merchant Center asks for the same facts. Fix the gaps in your products, not in your wording.' d='Modules.Geolens.Admin'}</p>
    </div>
    <div style="flex:none;width:170px">
      <label class="gl-sr" for="gl-alang">{l s='Language' d='Modules.Geolens.Admin'}</label>
      <select id="gl-alang" data-catalog-lang>{foreach $gl_languages as $l}<option value="{$l.id_lang|intval}"{if $l.id_lang == $gl_lang} selected{/if}>{$l.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
    </div>
  </div>
  <div data-catalog></div>
  <div data-missing-box hidden></div>
</div>

<div class="gl-card">
  <div class="gl-card-head">
    <div>
      <h3>{l s='What a crawler gets from your pages' d='Modules.Geolens.Admin'}</h3>
      <p class="gl-card-intro">{l s='Fetches real pages the way most AI crawlers do, without running JavaScript. Finds duplicate or broken markup, directives that hide a page, and markup the page itself does not show.' d='Modules.Geolens.Admin'}</p>
    </div>
    <button type="button" class="gl-btn gl-btn-primary" style="flex:none" data-run="pages"><i class="material-icons" aria-hidden="true">travel_explore</i>{l s='Check my pages' d='Modules.Geolens.Admin'}</button>
  </div>
  <div class="gl-actions" style="flex-wrap:nowrap">
    <label class="gl-sr" for="gl-purl">{l s='Page address' d='Modules.Geolens.Admin'}</label>
    <input id="gl-purl" type="url" data-page-url placeholder="{l s='Or paste the address of any page of this shop' d='Modules.Geolens.Admin'}">
    <label class="gl-checks" style="border:0;padding:0;max-height:none;overflow:visible;flex:none"><label><input type="checkbox" data-page-product>{l s='Product page' d='Modules.Geolens.Admin'}</label></label>
    <button type="button" class="gl-btn gl-btn-secondary" style="flex:none" data-run="one-page">{l s='Check' d='Modules.Geolens.Admin'}</button>
  </div>
  <div data-page-results></div>
</div>

<div class="gl-accs">
<details class="gl-acc" data-acc="facts"{if !$gl_facts_saved} open{/if}>
  <summary><span class="gl-acc-title"><strong>{l s='Delivery and returns' d='Modules.Geolens.Admin'}</strong><small>{l s='Shown on product pages and in your product data, so the two agree.' d='Modules.Geolens.Admin'}</small></span>{if $gl_facts_saved}<span class="gl-pill gl-pill-ok">{l s='Saved' d='Modules.Geolens.Admin'}</span>{else}<span class="gl-pill gl-pill-warn">{l s='Not set' d='Modules.Geolens.Admin'}</span>{/if}<i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
    <form data-action="save_facts" data-returns>
  <p class="gl-muted">{l s='Only you know these, so nothing is published until you save it here. It then appears on product pages and in the product data, so the two always agree. State what your carriers really charge: search engines compare it with your checkout.' d='Modules.Geolens.Admin'}</p>

  <h4 class="gl-mt" style="font-weight:700">{l s='Returns' d='Modules.Geolens.Admin'}</h4>
  <div class="gl-grid-2">
    <div class="gl-field">
      <label for="gl-rmode">{l s='Your returns policy' d='Modules.Geolens.Admin'}</label>
      <select id="gl-rmode" name="returns_mode">
        <option value=""{if !$gl_facts.returns.mode} selected{/if}>{l s='Do not publish' d='Modules.Geolens.Admin'}</option>
        <option value="finite"{if $gl_facts.returns.mode == 'finite'} selected{/if}>{l s='Returns within a number of days' d='Modules.Geolens.Admin'}</option>
        <option value="unlimited"{if $gl_facts.returns.mode == 'unlimited'} selected{/if}>{l s='Returns at any time' d='Modules.Geolens.Admin'}</option>
        <option value="none"{if $gl_facts.returns.mode == 'none'} selected{/if}>{l s='No returns' d='Modules.Geolens.Admin'}</option>
      </select>
    </div>
    <div class="gl-field" data-when-mode="finite"{if $gl_facts.returns.mode != 'finite'} hidden{/if}>
      <label for="gl-rdays">{l s='Days to return' d='Modules.Geolens.Admin'}</label>
      <input id="gl-rdays" type="number" min="1" name="returns_days" value="{$gl_facts.returns.days|intval}">
    </div>
    <div class="gl-field" data-when-mode="finite unlimited"{if $gl_facts.returns.mode != 'finite' && $gl_facts.returns.mode != 'unlimited'} hidden{/if}>
      <label for="gl-rfees">{l s='Who pays for the return' d='Modules.Geolens.Admin'}</label>
      <select id="gl-rfees" name="returns_fees">
        <option value="free"{if $gl_facts.returns.fees == 'free'} selected{/if}>{l s='Free for the customer' d='Modules.Geolens.Admin'}</option>
        <option value="customer"{if $gl_facts.returns.fees == 'customer'} selected{/if}>{l s='The customer pays the postage' d='Modules.Geolens.Admin'}</option>
        <option value="fixed"{if $gl_facts.returns.fees == 'fixed'} selected{/if}>{l s='A fixed fee' d='Modules.Geolens.Admin'}</option>
      </select>
    </div>
    <div class="gl-field" data-when-fee{if $gl_facts.returns.fees != 'fixed' || !$gl_facts.returns.mode || $gl_facts.returns.mode == 'none'} hidden{/if}>
      <label for="gl-rfee">{l s='Return fee' d='Modules.Geolens.Admin'}</label>
      <div class="gl-money"><input id="gl-rfee" type="number" min="0" step="0.01" name="returns_fee" value="{$gl_facts.returns.fee|floatval}"><span>{$gl_currency|escape:'html':'UTF-8'}</span></div>
    </div>
  </div>
  <div class="gl-field" data-when-mode="finite unlimited none"{if !$gl_facts.returns.mode} hidden{/if}>
    <span class="gl-label">{l s='Applies to customers in' d='Modules.Geolens.Admin'}</span>
    <div class="gl-checks">{foreach $gl_countries as $c}<label><input type="checkbox" name="returns_countries[]" value="{$c.iso|escape:'html':'UTF-8'}"{if in_array($c.iso, $gl_facts.returns.countries)} checked{/if}>{$c.name|escape:'html':'UTF-8'}</label>{/foreach}</div>
  </div>

  <h4 class="gl-mt" style="font-weight:700">{l s='Delivery' d='Modules.Geolens.Admin'}</h4>
  <div data-rules>
    {foreach $gl_facts.shipping as $i => $rule}
      {include file='./delivery-rule.tpl' gl_i=$i gl_rule=$rule gl_saved=$gl_facts_saved}
    {/foreach}
  </div>
  <template data-rule-template>
    {assign var=gl_empty_rule value=['countries' => [], 'rate' => 0, 'free_over' => null, 'handling_min' => 0, 'handling_max' => 1, 'transit_min' => 1, 'transit_max' => 3]}
    {include file='./delivery-rule.tpl' gl_i='__i__' gl_rule=$gl_empty_rule gl_saved=true}
  </template>
  <div class="gl-actions">
    <button type="button" class="gl-btn gl-btn-secondary" data-add-rule><i class="material-icons" aria-hidden="true">add</i>{l s='Add a delivery rule' d='Modules.Geolens.Admin'}</button>
    <button type="submit" class="gl-btn gl-btn-primary">{l s='Save delivery and returns' d='Modules.Geolens.Admin'}</button>
  </div>
</form>
  </div>
</details>
<details class="gl-acc" data-acc="markup">
  <summary><span class="gl-acc-title"><strong>{l s='Product markup' d='Modules.Geolens.Admin'}</strong><small>{l s='How the module corrects what your theme prints.' d='Modules.Geolens.Admin'}</small></span>{if $gl_schema}<span class="gl-pill gl-pill-ok">{l s='On' d='Modules.Geolens.Admin'}</span>{else}<span class="gl-pill gl-pill-off">{l s='Off' d='Modules.Geolens.Admin'}</span>{/if}<i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
    <form data-action="save_markup">
  <p class="gl-muted">{l s='The module extends the markup your theme already prints, so there is never a second Product. It helps rich results and Merchant Center. Tests have not found that markup alone earns AI citations.' d='Modules.Geolens.Admin'}</p>
  <label class="gl-switch"><input type="checkbox" name="schema" value="1"{if $gl_schema} checked{/if}><span class="gl-track"></span><span class="gl-switch-text"><strong>{l s='Correct and complete the product markup' d='Modules.Geolens.Admin'}</strong><small>{l s='Removes the made-up "valid for 15 days" date and false identifiers, checks GTINs, states condition, delivery and returns.' d='Modules.Geolens.Admin'}</small></span></label>
  <label class="gl-switch"><input type="checkbox" name="variants" value="1"{if $gl_variants} checked{/if}><span class="gl-track"></span><span class="gl-switch-text"><strong>{l s='Describe each combination' d='Modules.Geolens.Admin'}</strong><small>{l s='Each size or color gets its own price, stock and identifier, for products with up to 50 combinations.' d='Modules.Geolens.Admin'}</small></span></label>
  <label class="gl-switch"><input type="checkbox" name="show_facts" value="1"{if $gl_show_facts} checked{/if}><span class="gl-track"></span><span class="gl-switch-text"><strong>{l s='Show delivery and returns on the product page' d='Modules.Geolens.Admin'}</strong><small>{l s='Search engines want markup to match what the visitor can read.' d='Modules.Geolens.Admin'}</small></span></label>
  <label class="gl-switch"><input type="checkbox" name="shop_brand" value="1"{if $gl_shop_brand} checked{/if}><span class="gl-track"></span><span class="gl-switch-text"><strong>{l s='We make our own products' d='Modules.Geolens.Admin'}</strong><small>{l s='Use the shop name as the brand of products with no manufacturer. Leave this off if you resell other brands.' d='Modules.Geolens.Admin'}</small></span></label>
  <label class="gl-switch"><input type="checkbox" name="strike" value="1"{if $gl_strike} checked{/if}><span class="gl-track"></span><span class="gl-switch-text"><strong>{l s='Publish crossed-out prices' d='Modules.Geolens.Admin'}</strong><small>{if $gl_in_eu}{l s='Off by default for your country: in the EU, a reduction must be calculated from the lowest price of the previous 30 days, and the module cannot check that for you.' d='Modules.Geolens.Admin'}{else}{l s='The price before the discount, as shown on the page.' d='Modules.Geolens.Admin'}{/if}</small></span></label>
  <div class="gl-actions"><button type="submit" class="gl-btn gl-btn-primary">{l s='Save' d='Modules.Geolens.Admin'}</button></div>
</form>
  </div>
</details>
</div>
