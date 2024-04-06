{$cashfree_confirmation}

<img src="{$base_url|escape:'htmlall':'UTF-8'}" alt="" style="display: none;"/>

<div class="cashfree-header">
    <h2 class="page-title"><img src="{$module_dir}logo.png" alt="cashfree" class="cashfree-logo"/>
        Cashfree
    </h2>
</div>

<form action="{$cashfree_form|escape:'htmlall':'UTF-8'}" id="module_form" class="defaultForm form-horizontal"
      method="post">
    <div class="panel" id="fieldset_0">
        <div class="panel-heading">
            <i class="icon-cogs"></i>Settings
        </div>

        <div class="form-wrapper">


            <div class="form-group">
                <label class="control-label col-lg-3" for="cashfree_app_id">{l s='App  ID:' mod='cashfree'}</label>
                <div class="col-lg-3">
                    <div class="input-group">
                        <span class="input-group-addon"><i class="icon icon-tag"></i></span>
                        <input type="text" class="text" name="cashfree_app_id" id="cashfree_app_id"
                               value="{$cashfree_app_id|escape:'htmlall':'UTF-8'}"/>
                    </div>
                </div>
            </div>


            <div class="form-group">

                <label class="control-label col-lg-3"
                       for="cashfree_secret_key">{l s='Secret Key:' mod='cashfree'}</label>
                <div class="col-lg-3">
                    <div class="input-group">

                        <span class="input-group-addon"><i class="icon icon-tag"></i></span>
                        <input type="text" class="text" name="cashfree_secret_key" id="cashfree_secret_key"
                               value="{$cashfree_secret_key|escape:'htmlall':'UTF-8'}"/>

                    </div>
                </div>
            </div>


            <div class="form-group">
                <label class="control-label col-lg-3" for="cashfree_imode">{l s='Test Mode:' mod='cashfree'}</label>
                <div class="col-lg-3">
                    <label for="input-mode"></label><select name="cashfree_mode" id="input-mode" class="form-control">
                        {if $cashfree_mode == 'Y'}
                            <option value="Y" selected="selected">{l s='Yes' mod='cashfree'}</option>
                            <option value="N">{l s='No' mod='cashfree'}</option>
                        {else}
                            <option value="Y">{l s='Yes' mod='cashfree'}</option>
                            <option value="N" selected="selected">{l s='No' mod='cashfree'}</option>
                        {/if}
                    </select>

                </div>
            </div>


            <div class="form-group">
                <label class="control-label col-lg-3"
                       for="cashfree_order_status">{l s='Success Order Status:' mod='cashfree'}</label>
                <div class="col-lg-3">
                    <label for="input-transaction-method"></label><select name="cashfree_order_status"
                                                                          id="input-transaction-method"
                                                                          class="form-control">
                        {foreach from=$orderstates key='ordid' item='ordname'}
                            <option
                                value="{$ordid}" {if $ordid == $cashfree_order_status} selected="selected"{/if}>{$ordname}</option>
                        {/foreach}
                    </select>
                </div>
            </div>


        </div>
        <div class="panel-footer">

            <button type="submit" value="1" id="module_form_submit_btn" name="submitcashfree"
                    class="btn btn-default pull-right">
                <i class="process-icon-save"></i> Save
            </button>
        </div>
    </div>
</form>


