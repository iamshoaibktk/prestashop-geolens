{**
 * One row of the access test.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{if $gl_r.state == 'open'}
  <span class="gl-pill {if $gl_r.expected}gl-pill-ok{else}gl-pill-err{/if}"><i class="material-icons" aria-hidden="true">{if $gl_r.expected}check{else}priority_high{/if}</i>{if $gl_r.status}{l s='Reached (%status%)' sprintf=['%status%' => $gl_r.status] d='Modules.Geolens.Admin'}{else}{l s='Allowed' d='Modules.Geolens.Admin'}{/if}</span>
{elseif $gl_r.state == 'robots'}
  <span class="gl-pill {if $gl_r.expected}gl-pill-off{else}gl-pill-err{/if}"><i class="material-icons" aria-hidden="true">{if $gl_r.expected}block{else}priority_high{/if}</i>{l s='Closed by robots.txt' d='Modules.Geolens.Admin'}</span>
{elseif $gl_r.state == 'challenge'}
  <span class="gl-pill {if $gl_r.expected}gl-pill-off{else}gl-pill-err{/if}"><i class="material-icons" aria-hidden="true">{if $gl_r.expected}block{else}priority_high{/if}</i>{l s='Stopped by a challenge page' d='Modules.Geolens.Admin'}</span>
{else}
  <span class="gl-pill {if $gl_r.expected}gl-pill-off{else}gl-pill-err{/if}"><i class="material-icons" aria-hidden="true">{if $gl_r.expected}block{else}priority_high{/if}</i>{if $gl_r.status}{l s='Refused (%status%)' sprintf=['%status%' => $gl_r.status] d='Modules.Geolens.Admin'}{else}{l s='No answer' d='Modules.Geolens.Admin'}{/if}</span>
{/if}
