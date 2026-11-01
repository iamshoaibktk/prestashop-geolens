{**
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<div class="gl-accs gl-accs-first">
<details class="gl-acc" data-acc="attribution">
  <summary><span class="gl-acc-title"><strong>{l s='How an order is linked to an AI visit' d='Modules.Geolens.Admin'}</strong><small>{l s='The rule that decides which orders count.' d='Modules.Geolens.Admin'}</small></span>{if $gl_log_visits}<span class="gl-pill gl-pill-ok">{l s='On' d='Modules.Geolens.Admin'}</span>{else}<span class="gl-pill gl-pill-off">{l s='Off' d='Modules.Geolens.Admin'}</span>{/if}<i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
    <form data-action="save_attribution">
  <div class="gl-field">
    <label for="gl-window">{l s='Count an order when it is placed' d='Modules.Geolens.Admin'}</label>
    <select id="gl-window" name="window" style="max-width:420px">
      <option value="7200"{if $gl_window == 7200} selected{/if}>{l s='During the same visit, within 2 hours' d='Modules.Geolens.Admin'}</option>
      <option value="2592000"{if $gl_window == 2592000} selected{/if}>{l s='Within 30 days of the visit' d='Modules.Geolens.Admin'}</option>
    </select>
    <p class="gl-help">{l s='A 30-day link remembers a visitor for longer. Check whether it needs consent where you sell.' d='Modules.Geolens.Admin'}</p>
  </div>
  <label class="gl-switch"><input type="checkbox" name="log_visits" value="1"{if $gl_log_visits} checked{/if}><span class="gl-track"></span><span class="gl-switch-text"><strong>{l s='Count visits from AI assistants' d='Modules.Geolens.Admin'}</strong><small>{l s='Only the assistant\'s name is kept, inside PrestaShop\'s own cookie. No IP address, no third party, no new cookie.' d='Modules.Geolens.Admin'}</small></span></label>
  <div class="gl-actions"><button type="submit" class="gl-btn gl-btn-primary">{l s='Save' d='Modules.Geolens.Admin'}</button></div>
</form>
  </div>
</details>
<details class="gl-acc" data-acc="log">
  <summary><span class="gl-acc-title"><strong>{l s='Crawler log' d='Modules.Geolens.Admin'}</strong><small>{l s='What is recorded for each crawler request, and for how long.' d='Modules.Geolens.Admin'}</small></span>{if $gl_log_bots}<span class="gl-pill gl-pill-ok">{l s='On' d='Modules.Geolens.Admin'}</span>{else}<span class="gl-pill gl-pill-off">{l s='Off' d='Modules.Geolens.Admin'}</span>{/if}<i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
    <form data-action="save_measure">
      <label class="gl-switch"><input type="checkbox" name="log_bots" value="1"{if $gl_log_bots} checked{/if}><span class="gl-track"></span><span class="gl-switch-text"><strong>{l s='Count crawler requests' d='Modules.Geolens.Admin'}</strong><small>{l s='Name, page and status only. Addresses are checked against the operator\'s list and then forgotten.' d='Modules.Geolens.Admin'}</small></span></label>
      <div class="gl-field"><label for="gl-retention">{l s='Keep the log for' d='Modules.Geolens.Admin'}</label>
        <select id="gl-retention" name="retention" style="max-width:200px">
          {foreach [30, 90, 180, 365] as $d}<option value="{$d|intval}"{if $gl_retention == $d} selected{/if}>{l s='%days% days' sprintf=['%days%' => $d] d='Modules.Geolens.Admin'}</option>{/foreach}
        </select>
      </div>
      <div class="gl-actions"><button type="submit" class="gl-btn gl-btn-secondary">{l s='Save' d='Modules.Geolens.Admin'}</button></div>
    </form>
  </div>
</details>
<details class="gl-acc" data-acc="guard">
  <summary><span class="gl-acc-title"><strong>{l s='Load guard' d='Modules.Geolens.Admin'}</strong><small>{l s='Refuses AI crawlers on pages robots.txt already forbids.' d='Modules.Geolens.Admin'}</small></span>{if $gl_guard}<span class="gl-pill gl-pill-ok">{l s='On' d='Modules.Geolens.Admin'}</span>{else}<span class="gl-pill gl-pill-off">{l s='Off' d='Modules.Geolens.Admin'}</span>{/if}<i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
    <form data-action="save_guard">
      <label class="gl-switch"><input type="checkbox" name="guard" value="1"{if $gl_guard} checked{/if}><span class="gl-track"></span><span class="gl-switch-text"><strong>{l s='Refuse AI crawlers on pages robots.txt already forbids' d='Modules.Geolens.Admin'}</strong><small>{l s='Filter, search and cart URLs are the expensive ones. A crawler that obeys robots.txt never asks for them, so this costs no visibility. Scrapers that hide their name are not stopped by this: only your CDN can do that.' d='Modules.Geolens.Admin'}</small></span></label>
      <div class="gl-actions"><button type="submit" class="gl-btn gl-btn-secondary">{l s='Save' d='Modules.Geolens.Admin'}</button></div>
    </form>
  </div>
</details>
<details class="gl-acc" data-acc="indexnow">
  <summary><span class="gl-acc-title"><strong>IndexNow</strong><small>{l s='Tells Bing and others when a page changes.' d='Modules.Geolens.Admin'}</small></span>{if $gl_indexnow}<span class="gl-pill gl-pill-ok">{l s='On' d='Modules.Geolens.Admin'}</span>{else}<span class="gl-pill gl-pill-off">{l s='Off' d='Modules.Geolens.Admin'}</span>{/if}<i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
    <form data-action="save_indexnow">
  <p class="gl-muted">{l s='IndexNow sends the address of a changed page to Bing, which feeds Copilot, and to Yandex, Seznam, Naver, Yep, Amazon and the Internet Archive. Google does not take part, and no one has measured an effect on AI answers.' d='Modules.Geolens.Admin'}</p>
  <label class="gl-switch"><input type="checkbox" name="indexnow" value="1"{if $gl_indexnow} checked{/if}><span class="gl-track"></span><span class="gl-switch-text"><strong>{l s='Turn on IndexNow' d='Modules.Geolens.Admin'}</strong><small>{l s='Sends only public page addresses, when a product, category or page is saved, a price changes, or stock reaches or leaves zero.' d='Modules.Geolens.Admin'}</small></span></label>
  <div class="gl-actions"><button type="submit" class="gl-btn gl-btn-primary">{l s='Save' d='Modules.Geolens.Admin'}</button></div>

  {if $gl_indexnow}
    <div class="gl-note gl-note-plain"><i class="material-icons" aria-hidden="true">key</i><p>{l s='Your key file proves the shop is yours:' d='Modules.Geolens.Admin'} <a class="gl-link" href="{$gl_key_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$gl_key_url|escape:'html':'UTF-8'}</a></p></div>
    <div class="gl-actions" style="justify-content:space-between">
      <p>
        {l s='Waiting to be sent: %count%.' sprintf=['%count%' => $gl_pending] d='Modules.Geolens.Admin'}
        {if $gl_backfill > 0}{l s='Sending your catalogue, now at product %id%.' sprintf=['%id%' => $gl_backfill] d='Modules.Geolens.Admin'}{/if}
      </p>
      <span>
        <button type="button" class="gl-btn gl-btn-secondary" data-post="indexnow_send"{if !$gl_pending} disabled{/if}>{l s='Send now' d='Modules.Geolens.Admin'}</button>
        <button type="button" class="gl-btn gl-btn-secondary" data-post="indexnow_backfill" data-confirm="{l s='Send every active product once, in every language? Do this once, not regularly: submitted addresses count toward your crawl quota.' d='Modules.Geolens.Admin'}"{if $gl_backfill > 0} disabled{/if}>{l s='Send whole catalogue once' d='Modules.Geolens.Admin'}</button>
      </span>
    </div>
    {if $gl_log}
      <div class="gl-scroll"><table class="gl-table">
        <thead><tr><th>{l s='Sent' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Addresses' d='Modules.Geolens.Admin'}</th><th>{l s='Answer' d='Modules.Geolens.Admin'}</th></tr></thead>
        <tbody>
          {foreach $gl_log as $r}
            <tr><td>{$r.date|escape:'html':'UTF-8'}</td><td class="gl-num">{$r.urls|intval}</td><td>
              {if $r.code == 200}<span class="gl-pill gl-pill-ok">{l s='Accepted' d='Modules.Geolens.Admin'}</span>
              {elseif $r.code == 202}<span class="gl-pill gl-pill-ok">{l s='Received, checking your key' d='Modules.Geolens.Admin'}</span>
              {elseif $r.code == 403}<span class="gl-pill gl-pill-err">{l s='Key not accepted (403). Will retry in an hour.' d='Modules.Geolens.Admin'}</span>
              {elseif $r.code == 429}<span class="gl-pill gl-pill-warn">{l s='Too many requests (429). Will retry in an hour.' d='Modules.Geolens.Admin'}</span>
              {elseif $r.code == 0}<span class="gl-pill gl-pill-warn">{l s='No connection. Will retry in an hour.' d='Modules.Geolens.Admin'}</span>
              {else}<span class="gl-pill gl-pill-err">{l s='Refused (%status%)' sprintf=['%status%' => $r.code] d='Modules.Geolens.Admin'}</span>{/if}
            </td></tr>
          {/foreach}
        </tbody>
      </table></div>
      
    {/if}
  {/if}
</form>
  </div>
</details>
<details class="gl-acc" data-acc="sitemap">
  <summary><span class="gl-acc-title"><strong>{l s='Your sitemap' d='Modules.Geolens.Admin'}</strong><small>{l s='Whether it exists and is up to date.' d='Modules.Geolens.Admin'}</small></span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">

  <p class="gl-muted">{l s='Google and Bing trust a sitemap\'s dates only while they prove accurate. PrestaShop\'s product date misleads both ways: stock and sale prices never change it, while reordering a category changes it for products that did not change.' d='Modules.Geolens.Admin'}</p>
  <ul class="gl-todo">
    <li>
      {if !$gl_sitemap_exists}
        <span class="gl-todo-icon gl-todo-warning"><i class="material-icons" aria-hidden="true">warning_amber</i></span><span class="gl-todo-text">{l s='This shop has no sitemap yet. Generate one with the Google sitemap module.' d='Modules.Geolens.Admin'}</span>
      {elseif $gl_sitemap_age > 14}
        <span class="gl-todo-icon gl-todo-warning"><i class="material-icons" aria-hidden="true">warning_amber</i></span><span class="gl-todo-text">{l s='Your sitemap is %days% days old. Regenerate it, or schedule it with a cron job.' sprintf=['%days%' => $gl_sitemap_age] d='Modules.Geolens.Admin'}</span>
      {else}
        <span class="gl-todo-icon gl-todo-info"><i class="material-icons" aria-hidden="true">check</i></span><span class="gl-todo-text">{l s='Your sitemap exists and is %days% days old.' sprintf=['%days%' => $gl_sitemap_age] d='Modules.Geolens.Admin'} <a class="gl-link" href="{$gl_sitemap_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Open' d='Modules.Geolens.Admin'}</a></span>
      {/if}
    </li>
    <li>
      {if $gl_bulk_share >= 50 && $gl_total >= 20}
        <span class="gl-todo-icon gl-todo-warning"><i class="material-icons" aria-hidden="true">warning_amber</i></span><span class="gl-todo-text">{l s='%percent%% of your products share one "last changed" day. That usually means an import or a reorder, not real edits, and search engines learn to ignore such dates.' sprintf=['%percent%' => $gl_bulk_share] d='Modules.Geolens.Admin'}</span>
      {else}
        <span class="gl-todo-icon gl-todo-info"><i class="material-icons" aria-hidden="true">check</i></span><span class="gl-todo-text">{l s='Your products\' change dates look natural, with no big block sharing one day.' d='Modules.Geolens.Admin'}</span>
      {/if}
    </li>
  </ul>
  <div class="gl-note gl-note-plain"><i class="material-icons" aria-hidden="true">event_available</i><p>{l s='The module keeps its own record of real changes, including stock and scheduled sales, and uses it for the date in your page markup. It never changes a date just to look fresh.' d='Modules.Geolens.Admin'}</p></div>

  </div>
</details>
<details class="gl-acc" data-acc="llms">
  <summary><span class="gl-acc-title"><strong>{l s='llms.txt and agents.md' d='Modules.Geolens.Admin'}</strong><small>{l s='An optional index for AI tools, with no proven effect.' d='Modules.Geolens.Admin'}</small></span>{if $gl_llms}<span class="gl-pill gl-pill-ok">{l s='On' d='Modules.Geolens.Admin'}</span>{else}<span class="gl-pill gl-pill-off">{l s='Off' d='Modules.Geolens.Admin'}</span>{/if}<i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
<div class="gl-note gl-note-warn"><i class="material-icons" aria-hidden="true">science</i><div><p><strong>{l s='What the evidence says about this file' d='Modules.Geolens.Admin'}</strong></p><p>{l s='No study has found that llms.txt earns AI citations. Google says its Search ignores it, and in Ahrefs\' data 97% of these files received no requests at all. Coding agents and audit tools are its main readers. It is here because it costs nothing and some tools check for it, not because it will bring you customers.' d='Modules.Geolens.Admin'}</p></div></div>

    <form data-action="save_llms">
  <p class="gl-muted gl-mt">{l s='A short index of your categories and policy pages, in each language. It holds no prices or products, because those go stale in a file, and no instructions for AI, because those read as manipulation.' d='Modules.Geolens.Admin'}</p>
  <label class="gl-switch"><input type="checkbox" name="llms" value="1"{if $gl_llms} checked{/if}><span class="gl-track"></span><span class="gl-switch-text"><strong>{l s='Publish the file' d='Modules.Geolens.Admin'}</strong>{if $gl_llms}<small><a class="gl-link" href="{$gl_llms_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$gl_llms_url|escape:'html':'UTF-8'}</a> · <a class="gl-link" href="{$gl_agents_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$gl_agents_url|escape:'html':'UTF-8'}</a></small>{/if}</span></label>

  <div class="gl-field">
    <span class="gl-label">{l s='One or two sentences about your shop' d='Modules.Geolens.Admin'}</span>
    <p class="gl-help" style="margin:0 0 8px">{l s='Left empty, the file uses the meta description of your home page.' d='Modules.Geolens.Admin'}</p>
    {foreach $gl_llms_languages as $l}
      <div class="gl-field" style="margin-top:8px">
        <label for="gl-sum-{$l.id|intval}" style="font-weight:400">{$l.name|escape:'html':'UTF-8'}</label>
        <textarea id="gl-sum-{$l.id|intval}" name="summary[{$l.id|intval}]" rows="2" maxlength="500">{$l.summary|escape:'html':'UTF-8'}</textarea>
      </div>
    {/foreach}
  </div>
  <div class="gl-actions"><button type="submit" class="gl-btn gl-btn-primary">{l s='Save' d='Modules.Geolens.Admin'}</button></div>
</form>
    <h4 class="gl-mt">{l s='Preview' d='Modules.Geolens.Admin'}</h4>
    <pre class="gl-pre gl-pre-light">{$gl_preview|escape:'html':'UTF-8'}</pre>
  </div>
</details>
<details class="gl-acc" data-acc="jobs">
  <summary><span class="gl-acc-title"><strong>{l s='Scheduled jobs' d='Modules.Geolens.Admin'}</strong><small>{l s='Needed only if your host blocks the automatic ones.' d='Modules.Geolens.Admin'}</small></span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
    <div data-copy-from>
  <p class="gl-muted">{l s='The module runs its own jobs through a short request the shop sends to itself, so no cron is needed. If your host blocks that, or you prefer a real one, call this address every 5 minutes.' d='Modules.Geolens.Admin'}</p>
  <pre class="gl-pre gl-pre-light" data-copy-text>{$gl_cron|escape:'html':'UTF-8'}</pre>
  <div class="gl-actions"><button type="button" class="gl-btn gl-btn-secondary gl-btn-sm" data-copy>{l s='Copy' d='Modules.Geolens.Admin'}</button></div>
</div>
  </div>
</details>
</div>
