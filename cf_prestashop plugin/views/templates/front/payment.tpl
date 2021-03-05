<p class="payment_module cashfree">

<a href="{$acturl}" title="{l s='Pay by LemonPay' mod='cashfree'}">
<img src="{$this_path}/logo.png" alt="{l s='Pay by LemonPay)' mod='cashfree'}" width="86" height="49"/>
    
    {l s='Pay by Cashfree' mod='cashfree'}    
 {if isset($smarty.get.cashfreeError)}<p style="color: red;">{$smarty.get.cashfreeError|escape:'htmlall':'UTF-8'}</p>{/if}</a>
</p>