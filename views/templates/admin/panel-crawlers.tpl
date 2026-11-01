{**
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{if !$gl_at_root}
  <div class="gl-note gl-note-warn"><i class="material-icons" aria-hidden="true">warning_amber</i><p>{l s='Your shop is in a subfolder, and crawlers only read robots.txt at the root of a domain. The lines below will not reach them from here. Blocks are still enforced by your server.' d='Modules.Geolens.Admin'}</p></div>
{/if}
{if $gl_multistore}
  <div class="gl-note gl-note-plain"><i class="material-icons" aria-hidden="true">info</i><p>{l s='robots.txt is one file for your whole installation, so these choices apply to every shop.' d='Modules.Geolens.Admin'}</p></div>
{/if}
{if $gl_bypass}
  <div class="gl-note gl-note-warn"><i class="material-icons" aria-hidden="true">warning_amber</i><p>{l s='robots.txt has its own group for %bots%, so they ignore your rules for the cart, search and filter pages. Remove those lines from the file. Allowed bots need no lines at all.' sprintf=['%bots%' => $gl_bypass] d='Modules.Geolens.Admin'}</p></div>
{/if}

<div class="gl-card" data-policy data-presets="{$gl_presets_json|escape:'html':'UTF-8'}" data-confirm-all="{l s='This blocks every answer engine. Your shop will likely disappear from AI answers. Continue?' d='Modules.Geolens.Admin'}">
  <h3>{l s='Who may read your shop' d='Modules.Geolens.Admin'}</h3>
  <p class="gl-card-intro">{l s='AI companies run separate crawlers for answers and for training, so you can allow one and refuse the other.' d='Modules.Geolens.Admin'}</p>

  <div class="gl-presets">
    <label class="gl-preset"><input type="radio" name="preset" value="visibility"{if $gl_preset == 'visibility'} checked{/if}><span><i class="material-icons" aria-hidden="true">visibility</i><strong>{l s='Visibility first' d='Modules.Geolens.Admin'}</strong><small>{l s='Every AI crawler may read your shop. Nothing is written to robots.txt. This is the default.' d='Modules.Geolens.Admin'}</small></span></label>
    <label class="gl-preset"><input type="radio" name="preset" value="notrain"{if $gl_preset == 'notrain'} checked{/if}><span><i class="material-icons" aria-hidden="true">school</i><strong>{l s='Answers yes, training no' d='Modules.Geolens.Admin'}</strong><small>{l s='Crawlers that can cite you stay. Crawlers that collect for model training are refused.' d='Modules.Geolens.Admin'}</small></span></label>
    <label class="gl-preset"><input type="radio" name="preset" value="lockdown"{if $gl_preset == 'lockdown'} checked{/if}><span><i class="material-icons" aria-hidden="true">block</i><strong>{l s='Block all AI' d='Modules.Geolens.Admin'}</strong><small>{l s='Only search engines stay. You will likely disappear from AI answers while competitors stay in.' d='Modules.Geolens.Admin'}</small></span></label>
  </div>

  <div class="gl-policy-more">
<details class="gl-acc" data-acc="bots">
  <summary><span class="gl-acc-title"><strong>{l s='Crawler by crawler' d='Modules.Geolens.Admin'}</strong><small>{l s='Allow or refuse each one, and see its requests and its access test.' d='Modules.Geolens.Admin'}</small></span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
    <div class="gl-actions gl-actions-top">
      <p class="gl-muted" data-access-summary hidden></p>
      <button type="button" class="gl-btn gl-btn-secondary gl-btn-sm" data-run="access"><i class="material-icons" aria-hidden="true">network_check</i>{l s='Test access now' d='Modules.Geolens.Admin'}</button>
    </div>
  <div data-access-notes>
    <div class="gl-note gl-note-warn" data-if="cdn" hidden><i class="material-icons" aria-hidden="true">cloud</i><p>{l s='Your shop is behind %cdn%, which can block crawlers whatever robots.txt says. Check its bot settings too.' d='Modules.Geolens.Admin'}</p></div>
    <div class="gl-note gl-note-warn" data-if="differs" hidden><i class="material-icons" aria-hidden="true">compare_arrows</i><p>{l s='The robots.txt crawlers receive is not the file on your server. Something in front of the shop changes it, often a CDN adding its own AI rules.' d='Modules.Geolens.Admin'}</p></div>
  </div>

  <div class="gl-scroll"><table class="gl-table">
    <thead><tr>
      <th>{l s='Crawler' d='Modules.Geolens.Admin'}</th>
      <th class="gl-num">{l s='Requests' d='Modules.Geolens.Admin'}</th>
      <th>{l s='Is it real?' d='Modules.Geolens.Admin'}</th>
      <th>{l s='Access test' d='Modules.Geolens.Admin'}</th>
      <th style="text-align:right">{l s='Allow' d='Modules.Geolens.Admin'}</th>
    </tr></thead>
    <tbody>
    {foreach ['search', 'user', 'train', 'core'] as $purpose}
      <tr class="gl-group"><td colspan="5">
        {if $purpose == 'search'}<strong>{l s='Answers and search' d='Modules.Geolens.Admin'}</strong><small>{l s='These can cite and link your shop. Blocking one can remove you from that assistant\'s answers.' d='Modules.Geolens.Admin'}</small>
        {elseif $purpose == 'user'}<strong>{l s='Read for a person' d='Modules.Geolens.Admin'}</strong><small>{l s='An assistant opens a page because someone asked. Many ignore robots.txt by design, so a block here is also enforced by your server.' d='Modules.Geolens.Admin'}</small>
        {elseif $purpose == 'train'}<strong>{l s='Training' d='Modules.Geolens.Admin'}</strong><small>{l s='These collect content that may train models. No operator states a search penalty for refusing them.' d='Modules.Geolens.Admin'}</small>
        {else}<strong>{l s='Search engines' d='Modules.Geolens.Admin'}</strong><small>{l s='Behind AI Overviews, AI Mode and Copilot. Blocking them removes you from search, so the module never does.' d='Modules.Geolens.Admin'}</small>{/if}
      </td></tr>
      {foreach $gl_bots[$purpose] as $b}
        <tr>
          <td>
            <strong>{$b.token|escape:'html':'UTF-8'}</strong>
            <small style="display:block">{$b.op|escape:'html':'UTF-8'}{if !$b.official} · {l s='no official documentation' d='Modules.Geolens.Admin'}{/if}{if $b.control} · {l s='a setting, it never visits' d='Modules.Geolens.Admin'}{/if}</small>
            {if $b.token == 'Google-Extended'}<small style="display:block">{l s='Also controls answers in Gemini Apps, so blocking it can remove you from them. Google Search, AI Overviews and AI Mode are not affected.' d='Modules.Geolens.Admin'}</small>{/if}
          </td>
          <td class="gl-num">{$b.hits|intval}{if $b.last}<small style="display:block">{$b.last|escape:'html':'UTF-8'}</small>{/if}</td>
          <td>
            {if $b.hits == 0}<span class="gl-muted">-</span>
            {elseif !$b.can_verify}<span class="gl-muted" title="{l s='This operator publishes no address list, so this cannot be checked.' d='Modules.Geolens.Admin'}">{l s='Cannot be checked' d='Modules.Geolens.Admin'}</span>
            {elseif $b.spoofed > 0}<span class="gl-pill gl-pill-warn" title="{l s='Requests using this name from an address the operator does not publish.' d='Modules.Geolens.Admin'}"><i class="material-icons" aria-hidden="true">gpp_maybe</i>{l s='%count% fake' sprintf=['%count%' => $b.spoofed] d='Modules.Geolens.Admin'}</span>
            {else}<span class="gl-pill gl-pill-ok"><i class="material-icons" aria-hidden="true">verified</i>{l s='Verified' d='Modules.Geolens.Admin'}</span>{/if}
          </td>
          <td data-access="{$b.token|escape:'html':'UTF-8'}">{if isset($gl_access[$b.token])}{include file='./access-result.tpl' gl_r=$gl_access[$b.token]}{else}<span class="gl-muted">-</span>{/if}</td>
          <td style="text-align:right">
            {if $purpose == 'core'}
              <span class="gl-pill gl-pill-off"><i class="material-icons" aria-hidden="true">lock</i>{l s='Always' d='Modules.Geolens.Admin'}</span>
            {else}
              <label class="gl-switch gl-switch-inline"><input type="checkbox" name="allow[]" value="{$b.token|escape:'html':'UTF-8'}" data-purpose="{$purpose|escape:'html':'UTF-8'}"{if !$b.blocked} checked{/if}><span class="gl-track"></span><span class="gl-sr">{l s='Allow %bot%' sprintf=['%bot%' => $b.token] d='Modules.Geolens.Admin'}</span></label>
            {/if}
          </td>
        </tr>
      {/foreach}
    {/foreach}
    </tbody>
  </table></div>
    <p class="gl-floor"><i class="material-icons" aria-hidden="true">info</i><span>{l s='The test asks for a product page in each crawler\'s name from your own server. A firewall may treat a real crawler\'s address differently, so a pass here is strong evidence, not proof.' d='Modules.Geolens.Admin'}</span></p>


  </div>
</details>
<details class="gl-acc" data-acc="signals">
  <summary><span class="gl-acc-title"><strong>{l s='Say no to training everywhere' d='Modules.Geolens.Admin'}</strong><small>{l s='Two optional statements in robots.txt.' d='Modules.Geolens.Admin'}</small></span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
  <label class="gl-switch"><input type="checkbox" name="training" value="1"{if $gl_training} checked{/if}><span class="gl-track"></span><span class="gl-switch-text"><strong>{l s='Publish the shared opt-out (__usage_ai-training)' d='Modules.Geolens.Admin'}</strong><small>{l s='A new signal, proposed on 30 October 2026. Google and Microsoft plan to honour it from 2027, and OpenAI and Anthropic have announced support too. Google reads it as a no for Google-Extended unless that is named, so the module names it to keep you in Gemini Apps, which also leaves Gemini training allowed. Block Google-Extended above to refuse both.' d='Modules.Geolens.Admin'}</small></span></label>
  <label class="gl-switch"><input type="checkbox" name="signals" value="1"{if $gl_signals} checked{/if}><span class="gl-track"></span><span class="gl-switch-text"><strong>{l s='Publish Content Signals' d='Modules.Geolens.Admin'}</strong><small>{l s='A Cloudflare proposal that states your terms in robots.txt. No AI company is known to honour it yet. Most useful as a rights reservation in the EU.' d='Modules.Geolens.Admin'}</small></span></label>


  </div>
</details>
<details class="gl-acc" data-acc="preview">
  <summary><span class="gl-acc-title"><strong>{l s='What will be in robots.txt' d='Modules.Geolens.Admin'}</strong><small>{l s='Exactly what is written. Your own lines stay as they are.' d='Modules.Geolens.Admin'}</small></span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
  <pre class="gl-pre" data-preview{if !$gl_block} hidden{/if}>{$gl_block|escape:'html':'UTF-8'}</pre>
  <p class="gl-muted" style="margin-top:6px" data-preview-empty{if $gl_block} hidden{/if}>{l s='Nothing. Allowed crawlers need no lines at all, because a crawler with its own group stops reading your rules for the cart, search and filter pages.' d='Modules.Geolens.Admin'}</p>
  {if !$gl_writable}
    <div class="gl-note gl-note-warn"><i class="material-icons" aria-hidden="true">warning_amber</i><p>{l s='robots.txt cannot be written on this server. Your choices will still be enforced by the shop, but copy these lines into the file by hand.' d='Modules.Geolens.Admin'}</p></div>
  {/if}

  </div>
</details>
  </div>
  <div class="gl-savebar">
    <button type="button" class="gl-btn gl-btn-primary" data-save-policy>{l s='Apply' d='Modules.Geolens.Admin'}</button>
    {if $gl_can_undo}<button type="button" class="gl-btn gl-btn-ghost" data-post="undo_policy" data-confirm="{l s='Put robots.txt and your choices back to how they were before the last change?' d='Modules.Geolens.Admin'}"><i class="material-icons" aria-hidden="true">undo</i>{l s='Undo last change' d='Modules.Geolens.Admin'}</button>{/if}
  </div>
</div>

<h3 class="gl-section">{l s='Crawler activity' d='Modules.Geolens.Admin'}</h3>
<div class="gl-accs">
<details class="gl-acc" data-acc="read">
  <summary><span class="gl-acc-title"><strong>{l s='What assistants read for people' d='Modules.Geolens.Admin'}</strong></span><span class="gl-count">{count($gl_read_for_people)|intval}</span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
    <p class="gl-muted">{l s='Each line is a page an assistant opened because someone asked it something. This is the closest you can get to seeing the question.' d='Modules.Geolens.Admin'}</p>
    {if $gl_read_for_people}
      <div class="gl-scroll"><table class="gl-table"><thead><tr><th>{l s='Page' d='Modules.Geolens.Admin'}</th><th>{l s='Crawler' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Reads' d='Modules.Geolens.Admin'}</th></tr></thead><tbody>
        {foreach $gl_read_for_people as $r}<tr><td class="gl-path">{$r.path|escape:'html':'UTF-8'}</td><td><small>{$r.bots|escape:'html':'UTF-8'}</small></td><td class="gl-num">{$r.hits|intval}</td></tr>{/foreach}
      </tbody></table></div>
    {else}<p class="gl-muted gl-mt">{l s='Nothing yet.' d='Modules.Geolens.Admin'}</p>{/if}
  </div>
</details>
<details class="gl-acc" data-acc="missing">
  <summary><span class="gl-acc-title"><strong>{l s='Pages they asked for and did not find' d='Modules.Geolens.Admin'}</strong></span><span class="gl-count">{count($gl_missing)|intval}</span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
    <p class="gl-muted">{l s='AI systems often ask for addresses that no longer exist. A redirect wins those visits back.' d='Modules.Geolens.Admin'}</p>
    {if $gl_missing}
      <div class="gl-scroll"><table class="gl-table"><thead><tr><th>{l s='Page' d='Modules.Geolens.Admin'}</th><th>{l s='Crawler' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Requests' d='Modules.Geolens.Admin'}</th></tr></thead><tbody>
        {foreach $gl_missing as $r}<tr><td class="gl-path">{$r.path|escape:'html':'UTF-8'}</td><td><small>{$r.bots|escape:'html':'UTF-8'}</small></td><td class="gl-num">{$r.hits|intval}</td></tr>{/foreach}
      </tbody></table></div>
    {else}<p class="gl-muted gl-mt">{l s='Nothing yet.' d='Modules.Geolens.Admin'}</p>{/if}
  </div>
</details>
<details class="gl-acc" data-acc="demand">
  <summary><span class="gl-acc-title"><strong>{l s='What agents ask for' d='Modules.Geolens.Admin'}</strong></span><span class="gl-count">{count($gl_demand)|intval}</span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
    <p class="gl-muted">{l s='Counts requests for files and formats you may not offer. If a real crawler starts asking for one, that is the moment to add it, not before.' d='Modules.Geolens.Admin'}</p>
    {if $gl_demand}
      <div class="gl-scroll"><table class="gl-table"><thead><tr><th>{l s='Asked for' d='Modules.Geolens.Admin'}</th><th>{l s='By' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Requests' d='Modules.Geolens.Admin'}</th></tr></thead><tbody>
        {foreach $gl_demand as $what => $r}<tr><td><code>{if $what == 'llms_txt'}/llms.txt{elseif $what == 'llms_full'}/llms-full.txt{elseif $what == 'agents_md'}/agents.md{elseif $what == 'ucp'}/.well-known/ucp{elseif $what == 'mcp'}/.well-known/mcp{elseif $what == 'a2a'}/.well-known/agent-card{elseif $what == 'md_url'}*.md{elseif $what == 'accept_markdown'}Accept: text/markdown{elseif $what == 'signature_agent'}Signature-Agent{else}{$what|escape:'html':'UTF-8'}{/if}</code></td><td><small>{$r.bots|escape:'html':'UTF-8'}</small></td><td class="gl-num">{$r.hits|intval}</td></tr>{/foreach}
      </tbody></table></div>
    {else}<p class="gl-muted gl-mt">{l s='Nothing yet.' d='Modules.Geolens.Admin'}</p>{/if}
  </div>
</details>
</div>
