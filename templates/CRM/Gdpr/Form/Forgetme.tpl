{crmScope extensionKey='uk.co.vedaconsulting.gdpr'}
<div class="messages status no-popup">
  <div class="icon inform-icon"></div>&nbsp;
  <span class="font-red bold">{ts}WARNING: 'Forget me' process will make the contact record totally unidentified by removing any related data and entity links.{/ts} {ts}This action cannot be undone.{/ts}</span>
  {if $lowerVersion}
  	<p>{ts} Please Note, this action will not remove Instant Messenger details for the contact. if any Instant Messenger details remains in the contact record, Please delete them manually.. {/ts}</p>
  {/if}
  <p>{ts}Please make sure you are on the right contact record to perform this.{/ts}</p>
  <p>{ts}Click 'Forget me' if you want to continue.{/ts}</p>
</div>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{/crmScope}
