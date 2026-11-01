{**
 * One delivery rule: where it applies, what it costs and how long it takes.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<div class="gl-rule">
  <button type="button" class="gl-rule-remove" aria-label="{l s='Remove this rule' d='Modules.Geolens.Admin'}" title="{l s='Remove this rule' d='Modules.Geolens.Admin'}"><i class="material-icons" aria-hidden="true">close</i></button>
  <div class="gl-field">
    <span class="gl-label">{l s='Delivers to' d='Modules.Geolens.Admin'}</span>
    <div class="gl-checks">{foreach $gl_countries as $c}<label><input type="checkbox" name="rule[{$gl_i|escape:'html':'UTF-8'}][countries][]" value="{$c.iso|escape:'html':'UTF-8'}"{if in_array($c.iso, $gl_rule.countries)} checked{/if}>{$c.name|escape:'html':'UTF-8'}</label>{/foreach}</div>
  </div>
  <div class="gl-grid-2">
    <div class="gl-field">
      <label for="gl-rate-{$gl_i|escape:'html':'UTF-8'}">{l s='Delivery price' d='Modules.Geolens.Admin'}</label>
      <div class="gl-money"><input id="gl-rate-{$gl_i|escape:'html':'UTF-8'}" type="number" min="0" step="0.01" name="rule[{$gl_i|escape:'html':'UTF-8'}][rate]" value="{$gl_rule.rate|floatval}"><span>{$gl_currency|escape:'html':'UTF-8'}</span></div>
    </div>
    <div class="gl-field">
      <label for="gl-free-{$gl_i|escape:'html':'UTF-8'}">{l s='Free above (optional)' d='Modules.Geolens.Admin'}</label>
      <div class="gl-money"><input id="gl-free-{$gl_i|escape:'html':'UTF-8'}" type="number" min="0" step="0.01" name="rule[{$gl_i|escape:'html':'UTF-8'}][free_over]" value="{if $gl_rule.free_over !== null}{$gl_rule.free_over|floatval}{/if}"><span>{$gl_currency|escape:'html':'UTF-8'}</span></div>
    </div>
  </div>
  <div class="gl-grid-4">
    <div class="gl-field"><label for="gl-hmin-{$gl_i|escape:'html':'UTF-8'}">{l s='Preparing, days from' d='Modules.Geolens.Admin'}</label><input id="gl-hmin-{$gl_i|escape:'html':'UTF-8'}" type="number" min="0" name="rule[{$gl_i|escape:'html':'UTF-8'}][handling_min]" value="{$gl_rule.handling_min|intval}"></div>
    <div class="gl-field"><label for="gl-hmax-{$gl_i|escape:'html':'UTF-8'}">{l s='Preparing, days to' d='Modules.Geolens.Admin'}</label><input id="gl-hmax-{$gl_i|escape:'html':'UTF-8'}" type="number" min="0" name="rule[{$gl_i|escape:'html':'UTF-8'}][handling_max]" value="{$gl_rule.handling_max|intval}"></div>
    <div class="gl-field"><label for="gl-tmin-{$gl_i|escape:'html':'UTF-8'}">{l s='In transit, days from' d='Modules.Geolens.Admin'}</label><input id="gl-tmin-{$gl_i|escape:'html':'UTF-8'}" type="number" min="0" name="rule[{$gl_i|escape:'html':'UTF-8'}][transit_min]" value="{$gl_rule.transit_min|intval}"></div>
    <div class="gl-field"><label for="gl-tmax-{$gl_i|escape:'html':'UTF-8'}">{l s='In transit, days to' d='Modules.Geolens.Admin'}</label><input id="gl-tmax-{$gl_i|escape:'html':'UTF-8'}" type="number" min="0" name="rule[{$gl_i|escape:'html':'UTF-8'}][transit_max]" value="{$gl_rule.transit_max|intval}"></div>
  </div>
</div>
