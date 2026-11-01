{**
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{if $gl_mwg.linked}
  <div class="gl-note gl-note-warn"><i class="material-icons" aria-hidden="true">warning_amber</i><div><p><strong>{l s='Marketing with Google already looks linked to Merchant Center' d='Modules.Geolens.Admin'}</strong></p><p>{l s='It sends your products there through PrestaShop\'s cloud. Do not also add a primary feed from here for the same country and language: Google says a product uploaded through two sources belongs to whichever updated last, and recommends one primary source. Use a supplemental feed here instead.' d='Modules.Geolens.Admin'}</p></div></div>
{elseif $gl_mwg.active}
  <div class="gl-note gl-note-plain"><i class="material-icons" aria-hidden="true">info</i><div><p><strong>{l s='Marketing with Google is installed' d='Modules.Geolens.Admin'}</strong></p><p>{l s='It does not look linked to an account, though this shop cannot see its side of the connection. If you use its product sync, do not also add a primary feed from here for the same country and language: Google says a product uploaded through two sources belongs to whichever updated last, and recommends one primary source. Use a supplemental feed here instead.' d='Modules.Geolens.Admin'}</p></div></div>
{/if}
{foreach $gl_feed_shop_notes as $note}
  <div class="gl-note gl-note-warn"><i class="material-icons" aria-hidden="true">warning_amber</i><p>{$note.text|escape:'html':'UTF-8'}{if isset($note.tab)} <a class="gl-link" href="#{$note.tab|escape:'html':'UTF-8'}" data-goto="{$note.tab|escape:'html':'UTF-8'}">{l s='Open' d='Modules.Geolens.Admin'}</a>{/if}</p></div>
{/foreach}

<h3 class="gl-section gl-section-first">{l s='Your feeds' d='Modules.Geolens.Admin'}</h3>
<div data-feed-list>{include file='./feed-list.tpl'}</div>

<div class="gl-accs">
<details class="gl-acc" data-acc="add-feed"{if !$gl_feeds} open{/if}>
  <summary><span class="gl-acc-title"><strong>{l s='Add a feed' d='Modules.Geolens.Admin'}</strong><small>{l s='Choose the country, language and currency.' d='Modules.Geolens.Admin'}</small></span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
    <form data-action="save_feed"{if $gl_mwg.active} data-confirm-primary="{l s='Marketing with Google is active. A primary feed for the same country and language would compete with its sync. Add it anyway?' d='Modules.Geolens.Admin'}"{/if}>
  <p class="gl-muted">{l s='Merchant Center ties a file to one country and language, so each pair is a feed of its own. Item IDs stay the same in all of them, as Google asks.' d='Modules.Geolens.Admin'}</p>
  <div class="gl-grid-3">
    <div class="gl-field">
      <label for="gl-fcountry">{l s='Country you sell to' d='Modules.Geolens.Admin'}</label>
      <select id="gl-fcountry" name="country">{foreach $gl_feed_countries as $c}<option value="{$c.iso|escape:'html':'UTF-8'}"{if $c.iso == $gl_feed_country} selected{/if}>{$c.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
    </div>
    <div class="gl-field">
      <label for="gl-flang">{l s='Language' d='Modules.Geolens.Admin'}</label>
      <select id="gl-flang" name="id_lang">{foreach $gl_feed_languages as $l}<option value="{$l.id_lang|intval}"{if $l.id_lang == $gl_feed_lang} selected{/if}>{$l.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
    </div>
    <div class="gl-field">
      <label for="gl-fcurrency">{l s='Currency' d='Modules.Geolens.Admin'}</label>
      <select id="gl-fcurrency" name="id_currency">{foreach $gl_feed_currencies as $c}<option value="{$c.id|intval}"{if $c.id == $gl_feed_currency} selected{/if}>{$c.iso|escape:'html':'UTF-8'}</option>{/foreach}</select>
    </div>
  </div>
  <div class="gl-grid-2">
    <div class="gl-field">
      <label for="gl-fmode">{l s='What it is for' d='Modules.Geolens.Admin'}</label>
      <select id="gl-fmode" name="mode">
        <option value="primary"{if !$gl_mwg.active} selected{/if}>{l s='Primary: your whole catalogue, for an account with no other source' d='Modules.Geolens.Admin'}</option>
        <option value="supplemental"{if $gl_mwg.active} selected{/if}>{l s='Supplemental: extra details on top of another source' d='Modules.Geolens.Admin'}</option>
      </select>
      <p class="gl-help">{l s='A supplemental feed works only where its IDs match the other source. The IDs here read product-combination, like 12-0 or 12-34, the form Marketing with Google uses in its own events. Its cloud service sets the IDs in Merchant Center and is not public, so compare a few there first.' d='Modules.Geolens.Admin'}</p>
    </div>
    <div class="gl-field">
      <label for="gl-ftarget">{l s='Where it goes' d='Modules.Geolens.Admin'}</label>
      <select id="gl-ftarget" name="target">
        <option value="google" selected>{l s='Google Merchant Center' d='Modules.Geolens.Admin'}</option>
        <option value="microsoft">{l s='Microsoft Merchant Center, as a file of its own' d='Modules.Geolens.Admin'}</option>
      </select>
      <p class="gl-help">{l s='Most shops need no separate Microsoft file: its import tool copies what Google already has.' d='Modules.Geolens.Admin'}</p>
    </div>
  </div>
  <div class="gl-actions"><button type="submit" class="gl-btn gl-btn-primary"><i class="material-icons" aria-hidden="true">add</i>{l s='Add this feed' d='Modules.Geolens.Admin'}</button></div>
</form>
  </div>
</details>
<details class="gl-acc" data-acc="feed-how">
  <summary><span class="gl-acc-title"><strong>{l s='How a feed works' d='Modules.Geolens.Admin'}</strong></span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">
    <p class="gl-muted">{l s='Google says Merchant Center feeds can help products appear in AI responses. It does not say they will, and no one has measured by how much. Three different parties are involved:' d='Modules.Geolens.Admin'}</p>
  <div class="gl-three">
    <div class="gl-part"><i class="material-icons" aria-hidden="true">description</i><h4>{l s='The module writes the file' d='Modules.Geolens.Admin'}</h4><p>{l s='Once a day, in the background, from your catalogue: one item per combination, real prices and tax for the country you choose, and only GTINs whose check digit passes.' d='Modules.Geolens.Admin'}</p></div>
    <div class="gl-part"><i class="material-icons" aria-hidden="true">upload_file</i><h4>{l s='You submit it' d='Modules.Geolens.Admin'}</h4><p>{l s='You add its address to your own Merchant Center account. The module cannot do this for you, and nothing is sent anywhere until you do.' d='Modules.Geolens.Admin'}</p></div>
    <div class="gl-part"><i class="material-icons" aria-hidden="true">gavel</i><h4>{l s='The platform decides' d='Modules.Geolens.Admin'}</h4><p>{l s='Google and Microsoft review each product and choose whether and where to show it. A file they accept is not a promise of listings, clicks or citations.' d='Modules.Geolens.Admin'}</p></div>
  </div>

  </div>
</details>
<details class="gl-acc" data-acc="feed-submit">
  <summary><span class="gl-acc-title"><strong>{l s='Submitting it' d='Modules.Geolens.Admin'}</strong></span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">

  <p class="gl-muted">{l s='As their help pages described it on %date%. These screens change, and only the platforms decide what they accept.' sprintf=['%date%' => $gl_spec_checked] d='Modules.Geolens.Admin'}</p>
  <div class="gl-two">
    <div>
      <h4 class="gl-mt" style="font-weight:700">Google Merchant Center</h4>
      <ol class="gl-steps">
        <li>{l s='Open Data sources and add a product source from a file.' d='Modules.Geolens.Admin'}</li>
        <li>{l s='Choose to enter a link to your file, and paste the address of the feed.' d='Modules.Geolens.Admin'}</li>
        <li>{l s='Set the feed label and language shown next to the feed.' d='Modules.Geolens.Admin'}</li>
        <li>{l s='A supplemental feed goes on the Supplemental sources tab, which appears once the Advanced data source management add-on is on. Its label and language must match the primary source.' d='Modules.Geolens.Admin'}</li>
      </ol>
    </div>
    <div>
      <h4 class="gl-mt" style="font-weight:700">Microsoft Merchant Center</h4>
      <ol class="gl-steps">
        <li>{l s='The usual way: Tools, Merchant Center, your store, Import, New import. It copies your Google offers, so schedule it, because imported offers expire after 30 days.' d='Modules.Geolens.Admin'}</li>
        <li>{l s='Or add a Microsoft feed here and use automatic download from a URL. The file must be under 1 GB. Microsoft says it takes Google-formatted XML, and its pages do not define that further, so check its first run.' d='Modules.Geolens.Admin'}</li>
      </ol>
    </div>
  </div>

  </div>
</details>
<details class="gl-acc" data-acc="feed-missing">
  <summary><span class="gl-acc-title"><strong>{l s='What the file does not contain' d='Modules.Geolens.Admin'}</strong></span><i class="material-icons gl-acc-chev" aria-hidden="true">expand_more</i></summary>
  <div class="gl-acc-body">

  <p class="gl-muted">{l s='PrestaShop holds no such fact, and the module will not guess. Fields follow Google\'s specification as read on %date%.' sprintf=['%date%' => $gl_spec_checked] d='Modules.Geolens.Admin'} <a class="gl-link" href="{$gl_spec_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Read it' d='Modules.Geolens.Admin'}</a></p>
  <ul class="gl-todo">
    <li><span class="gl-todo-icon gl-todo-info"><i class="material-icons" aria-hidden="true">checkroom</i></span><span class="gl-todo-text">{l s='Gender and age group. Google requires both for clothing sold in Brazil, France, Germany, Japan, the UK and the US. Add them with attribute rules in Merchant Center.' d='Modules.Geolens.Admin'}</span></li>
    <li><span class="gl-todo-icon gl-todo-info"><i class="material-icons" aria-hidden="true">category</i></span><span class="gl-todo-text">{l s='Google product category. It is optional, and your own categories go in as the product type instead.' d='Modules.Geolens.Admin'}</span></li>
    <li><span class="gl-todo-icon gl-todo-info"><i class="material-icons" aria-hidden="true">bolt</i></span><span class="gl-todo-text">{l s='Energy labels and unit prices, where EU law requires them, and the adult flag. Add these in Merchant Center if they apply to you.' d='Modules.Geolens.Admin'}</span></li>
    <li><span class="gl-todo-icon gl-todo-info"><i class="material-icons" aria-hidden="true">assignment_return</i></span><span class="gl-todo-text">{l s='Returns. Google asks you to use this field sparingly, so set your policy in the Merchant Center account.' d='Modules.Geolens.Admin'}</span></li>
  </ul>

  </div>
</details>
</div>
