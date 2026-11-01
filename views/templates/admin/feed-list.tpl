{**
 * The shop's feeds. Sent again on its own while a build is running.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{if !$gl_feeds}
  <div class="gl-card"><div class="gl-empty">
    <i class="material-icons" aria-hidden="true">rss_feed</i>
    <h4>{l s='No feed yet' d='Modules.Geolens.Admin'}</h4>
    <p>{l s='Add one below. It takes a few clicks, and nothing is sent anywhere until you submit its address yourself.' d='Modules.Geolens.Admin'}</p>
  </div></div>
{/if}
{foreach $gl_feeds as $f}
  <div class="gl-card gl-feed"{if $f.status == 'building'} data-feed-building{/if}>
    <div class="gl-feed-top">
      <h3>{$f.country_name|escape:'html':'UTF-8'} · {$f.language|escape:'html':'UTF-8'} · {$f.currency|escape:'html':'UTF-8'}</h3>
      <span class="gl-pill gl-pill-off">{if $f.mode == 'primary'}{l s='Primary' d='Modules.Geolens.Admin'}{else}{l s='Supplemental' d='Modules.Geolens.Admin'}{/if}</span>
      <span class="gl-pill gl-pill-off">{if $f.target == 'google'}Google{else}Microsoft{/if}</span>
      {if $f.status == 'building'}<span class="gl-pill gl-pill-warn"><i class="material-icons gl-spin" aria-hidden="true">autorenew</i>{l s='Building' d='Modules.Geolens.Admin'}</span>
      {elseif $f.status == 'failed'}<span class="gl-pill gl-pill-err">{l s='Build failed' d='Modules.Geolens.Admin'}</span>
      {elseif $f.status == 'ready'}<span class="gl-pill gl-pill-ok">{l s='File ready' d='Modules.Geolens.Admin'}</span>
      {else}<span class="gl-pill gl-pill-off">{l s='Not built yet' d='Modules.Geolens.Admin'}</span>{/if}
    </div>

    {if $f.status == 'building'}
      <div class="gl-meter gl-mt" role="progressbar" aria-valuenow="{$f.percent|intval}" aria-valuemin="0" aria-valuemax="100" aria-label="{l s='Building' d='Modules.Geolens.Admin'}"><span style="width:{$f.percent|intval}%"></span></div>
      <p class="gl-help">{l s='%done% of %total% products read. The current file stays online until the new one is complete.' sprintf=['%done%' => $f.done, '%total%' => $f.total] d='Modules.Geolens.Admin'}</p>
      {if $f.stuck}
        <div class="gl-note gl-note-warn"><i class="material-icons" aria-hidden="true">warning_amber</i><p>{l s='This build has not moved for a while. Your host may refuse requests from the shop to itself. Set up the scheduled job in the Settings tab, or keep this page open.' d='Modules.Geolens.Admin'} <a class="gl-link" href="#settings" data-goto="settings">{l s='Open' d='Modules.Geolens.Admin'}</a></p></div>
      {/if}
    {elseif $f.status == 'failed'}
      <div class="gl-note gl-note-err"><i class="material-icons" aria-hidden="true">error_outline</i><p>
        {if $f.error == 'no_items'}{l s='No product could be listed, so the last good file stays online. An empty file would take every product down in Merchant Center. It will try again in an hour.' d='Modules.Geolens.Admin'}
        {else}{l s='The build stopped, and any earlier file stays online. It will try again in an hour. Reason: %reason%' sprintf=['%reason%' => $f.error] d='Modules.Geolens.Admin'}{/if}
      </p></div>
    {/if}

    <div data-copy-from class="gl-feed-url">
      <span class="gl-label">{l s='Address of the file. Keep it private: anyone who has it can read your catalogue data.' d='Modules.Geolens.Admin'}</span>
      <pre class="gl-pre gl-pre-light" data-copy-text>{$f.url|escape:'html':'UTF-8'}</pre>
      <div class="gl-actions">
        <button type="button" class="gl-btn gl-btn-secondary gl-btn-sm" data-copy>{l s='Copy' d='Modules.Geolens.Admin'}</button>
        <button type="button" class="gl-btn gl-btn-secondary gl-btn-sm" data-post="feed_test" data-key="{$f.key|escape:'html':'UTF-8'}">{l s='Test the address' d='Modules.Geolens.Admin'}</button>
        <span class="gl-muted">{l s='Feed label: %label%. Language: %language%.' sprintf=['%label%' => $f.country, '%language%' => $f.iso] d='Modules.Geolens.Admin'}</span>
      </div>
    </div>

    {if $f.items !== null}
      <div class="gl-stats">
        <span>{l s='Items in the file:' d='Modules.Geolens.Admin'} <b>{$f.items|escape:'html':'UTF-8'}</b></span>
        <span>{l s='Size:' d='Modules.Geolens.Admin'} <b>{$f.size|escape:'html':'UTF-8'}</b></span>
        <span>{l s='Built:' d='Modules.Geolens.Admin'} <b>{$f.built|escape:'html':'UTF-8'}</b></span>
        <span>{l s='In %seconds% s of work.' sprintf=['%seconds%' => $f.seconds] d='Modules.Geolens.Admin'}</span>
        <span>{l s='Last fetched:' d='Modules.Geolens.Admin'} <b>{if $f.fetched}{$f.fetched|escape:'html':'UTF-8'}{if $f.fetched_by == 'other'} ({l s='by another program' d='Modules.Geolens.Admin'}){else} ({$f.fetched_by|escape:'html':'UTF-8'}){/if}{else}{l s='never' d='Modules.Geolens.Admin'}{/if}</b></span>
      </div>
      {if !$f.fetched}<p class="gl-help">{l s='No platform has read this file yet. It does nothing until you submit its address.' d='Modules.Geolens.Admin'}</p>{/if}
      {if $f.dropped}
        <div class="gl-note gl-note-warn"><i class="material-icons" aria-hidden="true">warning_amber</i><p>{l s='This file has less than half the items of the one before. If that is not what you expected, Merchant Center will remove the missing products at its next fetch.' d='Modules.Geolens.Admin'}</p></div>
      {/if}
      {if $f.large_for_microsoft && $f.target == 'microsoft'}
        <div class="gl-note gl-note-warn"><i class="material-icons" aria-hidden="true">warning_amber</i><p>{l s='Over 1 GB, the limit Microsoft states for a file downloaded from an address.' d='Modules.Geolens.Admin'}</p></div>
      {/if}
      {if $f.left_out || $f.notes || $f.problems}
        <details class="gl-numbers">
          <summary>{l s='What was left out, and what to fix' d='Modules.Geolens.Admin'}</summary>
          {if $f.left_out}
            <div class="gl-numbers-wrap"><table class="gl-table">
              <thead><tr><th>{l s='Not in the file because' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Products' d='Modules.Geolens.Admin'}</th><th>{l s='For example' d='Modules.Geolens.Admin'}</th></tr></thead>
              <tbody>{foreach $f.left_out as $r}<tr><td>{$r.text|escape:'html':'UTF-8'}</td><td class="gl-num">{$r.count|escape:'html':'UTF-8'}</td><td>{foreach $r.examples as $e}<a class="gl-link" href="{$e.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">#{$e.id|intval}</a> {/foreach}</td></tr>{/foreach}</tbody>
            </table></div>
          {/if}
          {if $f.notes}
            <div class="gl-numbers-wrap"><table class="gl-table">
              <thead><tr><th>{l s='In the file, but worth fixing' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Items' d='Modules.Geolens.Admin'}</th></tr></thead>
              <tbody>{foreach $f.notes as $r}<tr><td>{$r.text|escape:'html':'UTF-8'}</td><td class="gl-num">{$r.count|escape:'html':'UTF-8'}</td></tr>{/foreach}</tbody>
            </table></div>
          {/if}
          {if $f.problems}
            <pre class="gl-pre gl-pre-light">{foreach $f.problems as $p}{$p|escape:'html':'UTF-8'}
{/foreach}</pre>
          {/if}
        </details>
      {/if}
    {/if}

    <ul class="gl-feed-advice">
      {foreach $f.advice as $a}
        <li class="gl-feed-advice-{$a.level|escape:'html':'UTF-8'}"><i class="material-icons" aria-hidden="true">{if $a.level == 'warning'}warning_amber{else}info{/if}</i><span>{$a.text|escape:'html':'UTF-8'}{if isset($a.tab)} <a class="gl-link" href="#{$a.tab|escape:'html':'UTF-8'}" data-goto="{$a.tab|escape:'html':'UTF-8'}">{l s='Open' d='Modules.Geolens.Admin'}</a>{/if}</span></li>
      {/foreach}
    </ul>

    <div class="gl-actions">
      <button type="button" class="gl-btn gl-btn-secondary gl-btn-sm" data-post="feed_rebuild" data-key="{$f.key|escape:'html':'UTF-8'}"{if $f.status == 'building'} disabled{/if}><i class="material-icons" aria-hidden="true">refresh</i>{l s='Rebuild now' d='Modules.Geolens.Admin'}</button>
      <button type="button" class="gl-btn gl-btn-ghost gl-btn-sm" data-post="feed_rotate" data-key="{$f.key|escape:'html':'UTF-8'}" data-confirm="{l s='Create a new address? The old one stops working at once, and Merchant Center will need the new one.' d='Modules.Geolens.Admin'}">{l s='New address' d='Modules.Geolens.Admin'}</button>
      <button type="button" class="gl-btn gl-btn-ghost gl-btn-sm" data-post="feed_remove" data-key="{$f.key|escape:'html':'UTF-8'}" data-confirm="{l s='Remove this feed and its file? Merchant Center\'s next fetch will fail, and if it stays that way your products will expire there.' d='Modules.Geolens.Admin'}">{l s='Remove' d='Modules.Geolens.Admin'}</button>
    </div>
  </div>
{/foreach}
