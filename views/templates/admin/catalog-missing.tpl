{**
 * Products missing one fact, each with a link to fix it.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{if $gl_rows}
  <div class="gl-scroll" style="margin-bottom:0"><table class="gl-table">
    <thead><tr><th>{l s='Product' d='Modules.Geolens.Admin'}</th><th>{l s='Reference' d='Modules.Geolens.Admin'}</th><th><span class="gl-sr">{l s='Actions' d='Modules.Geolens.Admin'}</span></th></tr></thead>
    <tbody>
      {foreach $gl_rows as $r}
        <tr><td>{$r.name|escape:'html':'UTF-8'}</td><td class="gl-muted">{$r.reference|escape:'html':'UTF-8'}</td><td class="gl-num"><a class="gl-link" href="{$r.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Edit' d='Modules.Geolens.Admin'}</a></td></tr>
      {/foreach}
    </tbody>
  </table></div>
  {if $gl_next}
    <div class="gl-actions" style="margin-top:32px"><button type="button" class="gl-btn gl-btn-secondary gl-btn-sm" data-more="{$gl_fact|escape:'html':'UTF-8'}" data-after="{$gl_next|intval}">{l s='Show more' d='Modules.Geolens.Admin'}</button></div>
  {/if}
{else}
  <p class="gl-muted gl-mt">{l s='Nothing to fix here.' d='Modules.Geolens.Admin'}</p>
{/if}
