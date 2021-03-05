{if $cashfree_order.valid == 1}
<div class="conf confirmation">
	{l s='Congratulations! Your payment is pending verification, and your order has been saved under' mod='cashfree'}{if isset($cashfree_order.reference)} {l s='the reference' mod='cashfree'} <b>{$cashfree_order.reference|escape:html:'UTF-8'}</b>{else} {l s='the ID' mod='cashfree'} <b>{$cashfree_order.id|escape:html:'UTF-8'}</b>{/if}.
</div>
{else}
<div class="error">
	{l s='Unfortunately, an error occurred during the transaction.' mod='cashfree'}<br /><br />
	{l s='Please double-check your credit card details and try again. If you need further assistance, feel free to contact us anytime.' mod='cashfree'}<br /><br />
{if isset($cashfree_order.reference)}
	({l s='Your Order\'s Reference:' mod='cashfree'} <b>{$cashfree_order.reference|escape:html:'UTF-8'}</b>)
{else}
	({l s='Your Order\'s ID:' mod='cashfree'} <b>{$cashfree_order.id|escape:html:'UTF-8'}</b>)
{/if}
</div>
{/if}
