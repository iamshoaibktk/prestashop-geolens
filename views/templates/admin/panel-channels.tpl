{**
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<div class="gl-card gl-card-first">
  <h3>{l s='Where AI gets product data, and what is open to you' d='Modules.Geolens.Admin'}</h3>
  <p class="gl-card-intro">{l s='Your data can be ready, but applying is up to you and accepting is up to the platform. No module can get you into any of these. Checked on %date%, and this changes quickly.' sprintf=['%date%' => $gl_checked] d='Modules.Geolens.Admin'}</p>
  <div class="gl-scroll"><table class="gl-table">
    <thead><tr><th>{l s='Channel' d='Modules.Geolens.Admin'}</th><th>{l s='Open to this shop?' d='Modules.Geolens.Admin'}</th><th>{l s='What it does' d='Modules.Geolens.Admin'}</th></tr></thead>
    <tbody>
      {foreach $gl_channels as $c}
        <tr>
          <td style="white-space:nowrap"><a class="gl-link" href="{$c.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$c.name|escape:'html':'UTF-8'}</a></td>
          <td>{if $c.open}<span class="gl-pill gl-pill-ok">{if $c.markets == 'all'}{l s='Yes' d='Modules.Geolens.Admin'}{else}{l s='Possibly' d='Modules.Geolens.Admin'}{/if}</span>{else}<span class="gl-pill gl-pill-off">{l s='US buyers only' d='Modules.Geolens.Admin'}</span>{/if}</td>
          <td>
            {if $c.key == 'gmc'}{l s='The most valuable step. Google says feeds can help products appear in AI responses. Build a file in the Product feed tab, or connect through the official Marketing with Google module, but do not send the same products both ways.' d='Modules.Geolens.Admin'} <a class="gl-link" href="#feed" data-goto="feed">{l s='Open' d='Modules.Geolens.Admin'}</a>
            {elseif $c.key == 'msmc'}{l s='Microsoft says its feeds inform organic Copilot results. It can import your Google feed.' d='Modules.Geolens.Admin'}
            {elseif $c.key == 'bing'}{l s='Its AI Performance report shows when Copilot cites you. Those are citations, not clicks.' d='Modules.Geolens.Admin'}
            {elseif $c.key == 'gsc'}{l s='Its generative AI report shows impressions in AI Overviews and AI Mode, with no clicks or queries. It can also exclude you from them.' d='Modules.Geolens.Admin'}
            {elseif $c.key == 'chatgpt'}{l s='Discovery only: OpenAI has moved away from buying inside ChatGPT. Waitlist for shops outside Shopify and Etsy.' d='Modules.Geolens.Admin'}
            {elseif $c.key == 'ucp'}{l s='Checkout inside Google AI. Selected merchants, by application and review.' d='Modules.Geolens.Admin'}
            {elseif $c.key == 'copilot'}{l s='Checkout inside Copilot, through Shopify, Stripe or PayPal partners. PrestaShop is not among PayPal\'s listed partners.' d='Modules.Geolens.Admin'}{/if}
          </td>
        </tr>
      {/foreach}
    </tbody>
  </table></div>
</div>

<div class="gl-accs">
<details class="gl-acc" data-acc="ask">
  <summary><span class="gl-acc-title"><strong>{l s='Ask them yourself' d='Modules.Geolens.Admin'}</strong></span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">

  <p class="gl-muted">{l s='Paste these into ChatGPT, Perplexity or Gemini and read the answers, in a private window. Ask each one several times: the same question rarely gets the same list twice, so one answer proves little, and no tool can give you a real rank.' d='Modules.Geolens.Admin'}</p>
  <ul class="gl-prompts">
    {foreach $gl_prompts as $prompt}
      <li><span data-copy-text>{$prompt|escape:'html':'UTF-8'}</span><button type="button" class="gl-btn gl-btn-secondary gl-btn-sm" data-copy>{l s='Copy' d='Modules.Geolens.Admin'}</button></li>
    {/foreach}
  </ul>

  </div>
</details>
<details class="gl-acc" data-acc="wont">
  <summary><span class="gl-acc-title"><strong>{l s='What this module will not do, and why' d='Modules.Geolens.Admin'}</strong></span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">

  <p class="gl-muted">{l s='Plenty of tools sell these. The research found no support for them.' d='Modules.Geolens.Admin'}</p>
  <ul class="gl-todo">
    <li><span class="gl-todo-icon gl-todo-info"><i class="material-icons" aria-hidden="true">block</i></span><span class="gl-todo-text">{l s='Rewrite your product text with AI. In shopping tests, rewriting product copy mostly lowered citations.' d='Modules.Geolens.Admin'}</span></li>
    <li><span class="gl-todo-icon gl-todo-info"><i class="material-icons" aria-hidden="true">block</i></span><span class="gl-todo-text">{l s='Give you a visibility score or an AI rank. No stable rank exists, and such scores barely predict citations.' d='Modules.Geolens.Admin'}</span></li>
    <li><span class="gl-todo-icon gl-todo-info"><i class="material-icons" aria-hidden="true">block</i></span><span class="gl-todo-text">{l s='Generate FAQ blocks. Google stopped showing FAQ results in May 2026, and tests show almost no effect on AI answers.' d='Modules.Geolens.Admin'}</span></li>
    <li><span class="gl-todo-icon gl-todo-info"><i class="material-icons" aria-hidden="true">block</i></span><span class="gl-todo-text">{l s='Add special AI tags or refresh dates to look new. Engines cannot read tags schema.org does not define, and Google warns against date changes with no real change.' d='Modules.Geolens.Admin'}</span></li>
    <li><span class="gl-todo-icon gl-todo-info"><i class="material-icons" aria-hidden="true">block</i></span><span class="gl-todo-text">{l s='Promise you citations. Nothing supports that, whoever says it.' d='Modules.Geolens.Admin'}</span></li>
  </ul>
  <div class="gl-actions"><a class="gl-btn gl-btn-ghost gl-btn-sm" href="https://github.com/iamshoaibktk/prestashop-geolens/blob/main/EVIDENCE.md" target="_blank" rel="noopener"><i class="material-icons" aria-hidden="true">open_in_new</i>{l s='Read the evidence' d='Modules.Geolens.Admin'}</a></div>

  </div>
</details>
</div>
