{extends file=$layout}
{block name='content'}
	<div id="paytm_module">
	    <div class="errors">
			<div class='alert alert-danger error'>
				{$message}
				{if is_guest == true}
					<br/><br/>If you would like to view your order history please <a href="index.php?controller=history">Click Here</a>
				{/if}
			</div>
		</div>
	</div>
{/block}
