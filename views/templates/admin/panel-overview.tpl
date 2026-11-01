{**
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<section class="gl-kpis" aria-label="{l s='Overview' d='Modules.Geolens.Admin'}">
  <div class="gl-kpi"><span class="gl-kpi-label">{l s='Visits from AI assistants' d='Modules.Geolens.Admin'}</span><strong>{$gl_totals.visits|escape:'html':'UTF-8'}</strong></div>
  <div class="gl-kpi"><span class="gl-kpi-label">{l s='Orders after an AI visit' d='Modules.Geolens.Admin'}</span><strong>{$gl_totals.orders|escape:'html':'UTF-8'}</strong><small>{l s='%share%% of all orders' sprintf=['%share%' => $gl_order_share] d='Modules.Geolens.Admin'}</small></div>
  <div class="gl-kpi"><span class="gl-kpi-label">{l s='Value of those orders' d='Modules.Geolens.Admin'}</span><strong>{$gl_value|escape:'html':'UTF-8'}</strong></div>
  <div class="gl-kpi"><span class="gl-kpi-label">{l s='AI crawler requests' d='Modules.Geolens.Admin'}</span><strong>{$gl_totals.crawls|escape:'html':'UTF-8'}</strong></div>
</section>
<p class="gl-floor"><i class="material-icons" aria-hidden="true">info</i><span>{l s='These are a floor, not a total. Assistant apps hide where a click came from, Google\'s AI answers look like ordinary Google clicks, and a page cache can hide crawlers from PHP.' d='Modules.Geolens.Admin'}</span></p>

<div class="gl-card">
  <h3>{l s='What to look at next' d='Modules.Geolens.Admin'}</h3>
  {if $gl_checks}
    <ul class="gl-todo">
      {foreach $gl_checks as $c}{if $c@iteration <= 3}
        <li>
          <span class="gl-todo-icon gl-todo-{$c.level|escape:'html':'UTF-8'}"><i class="material-icons" aria-hidden="true">{if $c.level == 'error'}priority_high{elseif $c.level == 'warning'}warning_amber{else}lightbulb{/if}</i><span class="gl-sr">{if $c.level == 'error'}{l s='Problem' d='Modules.Geolens.Admin'}{elseif $c.level == 'warning'}{l s='Warning' d='Modules.Geolens.Admin'}{else}{l s='Suggestion' d='Modules.Geolens.Admin'}{/if}</span></span>
          <span class="gl-todo-text">{$c.text|escape:'html':'UTF-8'}</span>
          <button type="button" class="gl-btn gl-btn-secondary gl-btn-sm" data-goto="{$c.tab|escape:'html':'UTF-8'}">{l s='Open' d='Modules.Geolens.Admin'}</button>
        </li>
      {/if}{/foreach}
    </ul>
    {if count($gl_checks) > 3}
      <details class="gl-numbers"><summary>{l s='Show %count% more' sprintf=['%count%' => count($gl_checks) - 3] d='Modules.Geolens.Admin'}</summary>
        <ul class="gl-todo">
          {foreach $gl_checks as $c}{if $c@iteration > 3}
        <li>
          <span class="gl-todo-icon gl-todo-{$c.level|escape:'html':'UTF-8'}"><i class="material-icons" aria-hidden="true">{if $c.level == 'error'}priority_high{elseif $c.level == 'warning'}warning_amber{else}lightbulb{/if}</i><span class="gl-sr">{if $c.level == 'error'}{l s='Problem' d='Modules.Geolens.Admin'}{elseif $c.level == 'warning'}{l s='Warning' d='Modules.Geolens.Admin'}{else}{l s='Suggestion' d='Modules.Geolens.Admin'}{/if}</span></span>
          <span class="gl-todo-text">{$c.text|escape:'html':'UTF-8'}</span>
          <button type="button" class="gl-btn gl-btn-secondary gl-btn-sm" data-goto="{$c.tab|escape:'html':'UTF-8'}">{l s='Open' d='Modules.Geolens.Admin'}</button>
        </li>
          {/if}{/foreach}
        </ul>
      </details>
    {/if}
  {else}
    <div class="gl-empty"><i class="material-icons" aria-hidden="true">check_circle</i><h4>{l s='Nothing to fix right now.' d='Modules.Geolens.Admin'}</h4></div>
  {/if}
</div>

<div class="gl-card">
  <h3>{l s='From visit to order, by assistant' d='Modules.Geolens.Admin'}</h3>
  <p class="gl-card-intro">{l s='A visit is a click from an assistant. An order counts when it follows such a visit, under the rule you choose below.' d='Modules.Geolens.Admin'}</p>
  {if $gl_engines}
    <div class="gl-scroll"><table class="gl-table">
      <thead><tr><th>{l s='Assistant' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Visits' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Carts' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Orders' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Order value' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Orders per 100 visits' d='Modules.Geolens.Admin'}</th></tr></thead>
      <tbody>
      {foreach $gl_engines as $e}
        <tr><td><strong>{$e.label|escape:'html':'UTF-8'}</strong></td><td class="gl-num">{$e.visits|intval}</td><td class="gl-num">{$e.carts|intval}</td><td class="gl-num">{$e.orders|intval}</td><td class="gl-num">{$e.value_label|escape:'html':'UTF-8'}</td><td class="gl-num">{if $e.conversion !== null}{$e.conversion|escape:'html':'UTF-8'}{else}<span class="gl-muted">-</span>{/if}</td></tr>
      {/foreach}
      </tbody>
    </table></div>
  {else}
    <div class="gl-empty"><i class="material-icons" aria-hidden="true">shopping_bag</i><h4>{l s='No visits from AI assistants in this period.' d='Modules.Geolens.Admin'}</h4><p>{l s='They appear here as soon as someone clicks through from ChatGPT, Perplexity, Gemini, Copilot, Claude or another assistant.' d='Modules.Geolens.Admin'}</p></div>
  {/if}
</div>

<div class="gl-two">
  <div class="gl-card">
    <h3>{l s='Visits from AI assistants' d='Modules.Geolens.Admin'}</h3>
    {$gl_chart_visits nofilter}
    <details class="gl-numbers"><summary>{l s='See the numbers' d='Modules.Geolens.Admin'}</summary>
      <div class="gl-numbers-wrap"><table class="gl-table"><thead><tr><th>{l s='Day' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Visits' d='Modules.Geolens.Admin'}</th></tr></thead><tbody>
        {foreach $gl_table_visits as $day => $n}<tr><td>{$day|escape:'html':'UTF-8'}</td><td class="gl-num">{$n|intval}</td></tr>{/foreach}
      </tbody></table></div>
    </details>
  </div>
  <div class="gl-card">
    <h3>{l s='AI crawler requests' d='Modules.Geolens.Admin'}</h3>
    {$gl_chart_crawls nofilter}
    <div class="gl-legend">{foreach $gl_legend as $s}<span><i style="background:{$s.color|escape:'html':'UTF-8'}"></i>{$s.label|escape:'html':'UTF-8'}</span>{/foreach}</div>
    <details class="gl-numbers"><summary>{l s='See the numbers' d='Modules.Geolens.Admin'}</summary>
      <div class="gl-numbers-wrap"><table class="gl-table"><thead><tr><th>{l s='Day' d='Modules.Geolens.Admin'}</th>{foreach $gl_legend as $s}<th class="gl-num">{$s.label|escape:'html':'UTF-8'}</th>{/foreach}</tr></thead><tbody>
        {foreach $gl_table_crawls as $day => $values}<tr><td>{$day|escape:'html':'UTF-8'}</td>{foreach $values as $n}<td class="gl-num">{$n|intval}</td>{/foreach}</tr>{/foreach}
      </tbody></table></div>
    </details>
  </div>
</div>

<div class="gl-accs">
<details class="gl-acc" data-acc="landing">
  <summary><span class="gl-acc-title"><strong>{l s='Where they land' d='Modules.Geolens.Admin'}</strong></span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
{if $gl_landing}
      <div class="gl-scroll"><table class="gl-table"><thead><tr><th>{l s='Page' d='Modules.Geolens.Admin'}</th><th>{l s='Assistant' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Visits' d='Modules.Geolens.Admin'}</th></tr></thead><tbody>
        {foreach $gl_landing as $r}<tr><td class="gl-path">{$r.path|escape:'html':'UTF-8'}</td><td>{$r.label|escape:'html':'UTF-8'}</td><td class="gl-num">{$r.visits|intval}</td></tr>{/foreach}
      </tbody></table></div>
    {else}
      <p class="gl-muted gl-mt">{l s='Nothing yet.' d='Modules.Geolens.Admin'}</p>
    {/if}

  </div>
</details>
<details class="gl-acc" data-acc="orders">
  <summary><span class="gl-acc-title"><strong>{l s='Latest orders after an AI visit' d='Modules.Geolens.Admin'}</strong></span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
{if $gl_orders}
      <div class="gl-scroll"><table class="gl-table"><thead><tr><th>{l s='Order' d='Modules.Geolens.Admin'}</th><th>{l s='Assistant' d='Modules.Geolens.Admin'}</th><th class="gl-num">{l s='Value' d='Modules.Geolens.Admin'}</th></tr></thead><tbody>
        {foreach $gl_orders as $o}<tr><td><a class="gl-link" href="{$o.url|escape:'html':'UTF-8'}">{$o.reference|escape:'html':'UTF-8'}</a><small style="display:block">{$o.date|escape:'html':'UTF-8'}</small></td><td>{$o.label|escape:'html':'UTF-8'}</td><td class="gl-num">{$o.value_label|escape:'html':'UTF-8'}</td></tr>{/foreach}
      </tbody></table></div>
    {else}
      <p class="gl-muted gl-mt">{l s='Nothing yet.' d='Modules.Geolens.Admin'}</p>
    {/if}

  </div>
</details>
</div>
