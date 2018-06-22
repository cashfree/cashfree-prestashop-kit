{if isset($nbProducts) && $nbProducts <= 0}
<p class="warning">{l s='Your shopping cart is empty.'}</p>

{else}
	<form name="checkout_confirmation" action="{$action}" method="post" />
		{foreach from=$paytm_post key=k item=v}
			<input type="hidden" name="{$k}" value="{$v}" />
		{/foreach}
 	</form>
	<p>
		<center>
			<h1>Please do not refresh this page...</h1>
			{l s='You will be redirected to the Cashfree website to complete your payment.' mod='paytm'}
		</center>
	</p>
	<script type="text/javascript">
		document.checkout_confirmation.submit();
	</script>
{/if}
