<link href="{$module_dir}css/cashfree.css" rel="stylesheet" type="text/css">
<img src="{$cashfree_tracking|escape:'htmlall':'UTF-8'}" alt="" style="display: none;"/>
<div class="cashfree-wrap">
	{$cashfree_confirmation}
	<div class="cashfree-header">
		<img src="{$module_dir}logo.png" alt="Cashfree" class="cashfree-logo" /></a>
	</div>

	<form action="{$cashfree_form|escape:'htmlall':'UTF-8'}" id="cashfree-configuration" method="post">
		<fieldset>
			<legend>{l s='Configuration' mod='cashfree'}</legend>
			<div class="cashfree-half L">
				<label for="cashfree_app_id">{l s='App  ID:' mod='cashfree'}</label>
				<div class="margin-form">					
					<input type="text" class="text" name="cashfree_app_id" id="cashfree_app_id" value="{$cashfree_app_id|escape:'htmlall':'UTF-8'}" />
				</div>
				<label for="cashfree_secret_key">{l s='Secret Key:' mod='cashfree'}</label>
				<div class="margin-form">
					<input type="text" class="text" name="cashfree_secret_key" id="cashfree_secret_key" value="{$cashfree_secret_key|escape:'htmlall':'UTF-8'}" />
				</div>
				<label for="cashfree_maccount" for="cashfree_imode">{l s='Test Mode:' mod='cashfree'}</label>
				<div class="margin-form">
					<select name="cashfree_mode" id="input-mode" class="form-control">
					{if $cashfree_mode == 'Y'}
					<option value="Y" selected="selected">{l s='Yes' mod='cashfree'}</option>
					<option value="N">{l s='No' mod='cashfree'}</option>
					{else}
					<option value="Y">{l s='Yes' mod='cashfree'}</option>
					<option value="N" selected="selected">{l s='No' mod='cashfree'}</option>
					{/if}
				  </select> 
				</div>
				<label for="cashfree_apikey" for="cashfree_order_status">{l s='Success Order Status:' mod='cashfree'}</label> 
				<div class="margin-form">
					 <select name="cashfree_order_status" id="input-transaction-method" class="form-control">
					{foreach from=$orderstates key='ordid' item='ordname'}                  
						<option value="{$ordid}" {if $ordid == $cashfree_order_status} selected="selected"{/if}>{$ordname}</option>
					{/foreach}
	              </select> 
				</div>             
				                                                                                                                                              
						<button type="submit" value="1" id="module_form_submit_btn" name="submitcashfree" class="btn btn-default pull-right">
					<i class="process-icon-save"></i> Save
				</button>


			</div>
		</fieldset>
	</form>
</div>
