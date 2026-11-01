{**
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<link rel="stylesheet" href="{$gl_css|escape:'html':'UTF-8'}">
<div id="gl-app"
  data-url="{$gl_url|escape:'html':'UTF-8'}"
  data-t-error="{l s='Something went wrong. Please try again.' d='Modules.Geolens.Admin'}"
  data-t-retry="{l s='Try again' d='Modules.Geolens.Admin'}"
  data-t-copied="{l s='Copied' d='Modules.Geolens.Admin'}"
  data-t-checking="{l s='Checking...' d='Modules.Geolens.Admin'}"
  data-t-testing="{l s='Reading your robots.txt...' d='Modules.Geolens.Admin'}"
  data-t-testing-n="{l s='Tested %done% of %total%...' d='Modules.Geolens.Admin'}"
  data-t-test-ok="{l s='Done. Every crawler got the answer your settings call for.' d='Modules.Geolens.Admin'}"
  data-t-test-problems="{l s='Done. %count% crawlers did not get the answer your settings call for.' d='Modules.Geolens.Admin'}">

  <header class="gl-head">
    <img src="{$gl_logo|escape:'html':'UTF-8'}" alt="" width="36" height="36">
    <div class="gl-head-text">
      <h2>{l s='GEO Lens' d='Modules.Geolens.Admin'}</h2>
      <p>{l s='See and control how AI assistants use your shop, and what it earns you.' d='Modules.Geolens.Admin'}</p>
    </div>
    <a class="gl-btn gl-btn-ghost gl-btn-sm" href="{$gl_docs|escape:'html':'UTF-8'}" target="_blank" rel="noopener"><i class="material-icons" aria-hidden="true">menu_book</i>{l s='User guide (PDF)' d='Modules.Geolens.Admin'}</a>
  </header>

  <div class="gl-nav">
    <div class="gl-tabs" role="tablist" aria-label="{l s='GEO Lens sections' d='Modules.Geolens.Admin'}">
      <button type="button" role="tab" class="gl-tab" data-tab="overview" aria-controls="gl-panel" aria-selected="true"><i class="material-icons" aria-hidden="true">insights</i>{l s='Overview' d='Modules.Geolens.Admin'}</button>
      <button type="button" role="tab" class="gl-tab" data-tab="crawlers" aria-controls="gl-panel" aria-selected="false" tabindex="-1"><i class="material-icons" aria-hidden="true">smart_toy</i>{l s='AI crawlers' d='Modules.Geolens.Admin'}</button>
      <button type="button" role="tab" class="gl-tab" data-tab="data" aria-controls="gl-panel" aria-selected="false" tabindex="-1"><i class="material-icons" aria-hidden="true">inventory_2</i>{l s='Product data' d='Modules.Geolens.Admin'}</button>
      <button type="button" role="tab" class="gl-tab" data-tab="feed" aria-controls="gl-panel" aria-selected="false" tabindex="-1"><i class="material-icons" aria-hidden="true">rss_feed</i>{l s='Product feed' d='Modules.Geolens.Admin'}</button>
      <button type="button" role="tab" class="gl-tab" data-tab="settings" aria-controls="gl-panel" aria-selected="false" tabindex="-1"><i class="material-icons" aria-hidden="true">tune</i>{l s='Settings' d='Modules.Geolens.Admin'}</button>
      <button type="button" role="tab" class="gl-tab" data-tab="channels" aria-controls="gl-panel" aria-selected="false" tabindex="-1"><i class="material-icons" aria-hidden="true">hub</i>{l s='Channels' d='Modules.Geolens.Admin'}</button>
    </div>
    <div class="gl-period" role="group" aria-label="{l s='Period' d='Modules.Geolens.Admin'}">
      <button type="button" data-days="7" aria-pressed="false">{l s='7 days' d='Modules.Geolens.Admin'}</button>
      <button type="button" data-days="30" aria-pressed="true">{l s='30 days' d='Modules.Geolens.Admin'}</button>
      <button type="button" data-days="90" aria-pressed="false">{l s='90 days' d='Modules.Geolens.Admin'}</button>
    </div>
  </div>

  <div id="gl-flash" aria-live="polite"></div>
  <div id="gl-panel" role="tabpanel" tabindex="0" aria-live="polite" aria-busy="true"></div>
</div>
<script src="{$gl_js|escape:'html':'UTF-8'}"></script>
