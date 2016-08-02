<form action="{devblocks_url}{/devblocks_url}" method="POST" id="formBatchUpdate" name="formBatchUpdate" onsubmit="return false;">
<input type="hidden" name="c" value="profiles">
<input type="hidden" name="a" value="handleSectionAction">
<input type="hidden" name="section" value="opportunity">
<input type="hidden" name="action" value="startBulkUpdateJson">
<input type="hidden" name="view_id" value="{$view_id}">
<input type="hidden" name="opp_ids" value="{$opp_ids}">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<fieldset class="peek">
	<legend>{'common.bulk_update.with'|devblocks_translate|capitalize}</legend>
	<label><input type="radio" name="filter" value="" {if empty($opp_ids)}checked{/if}> {'common.bulk_update.filter.all'|devblocks_translate}</label> 
	{if !empty($opp_ids)}
		<label><input type="radio" name="filter" value="checks" {if !empty($opp_ids)}checked{/if}> {'common.bulk_update.filter.checked'|devblocks_translate}</label>
	{else}
		<label><input type="radio" name="filter" value="sample"> {'common.bulk_update.filter.random'|devblocks_translate} </label><input type="text" name="filter_sample_size" size="5" maxlength="4" value="100" class="input_number">
	{/if}
</fieldset>

<fieldset class="peek">
	<legend>Set Fields</legend>
	<table cellspacing="0" cellpadding="2" width="100%">
		<tr>
			<td width="0%" nowrap="nowrap" valign="top" align="right">{'common.status'|devblocks_translate|capitalize}:</td>
			<td width="100%"><select name="status">
				<option value=""></option>
				<option value="open">{'crm.opp.status.open'|devblocks_translate}</option>
				<option value="won">{'crm.opp.status.closed.won'|devblocks_translate}</option>
				<option value="lost">{'crm.opp.status.closed.lost'|devblocks_translate}</option>
				{if $active_worker->hasPriv('crm.opp.actions.delete')}
				<option value="deleted">{'status.deleted'|devblocks_translate|capitalize}</option>
				{/if}
			</select>
			<br>
			<button type="button" onclick="this.form.status.selectedIndex = 1;">{'crm.opp.status.open'|devblocks_translate|lower}</button>
			<button type="button" onclick="this.form.status.selectedIndex = 2;">{'crm.opp.status.closed.won'|devblocks_translate|lower}</button>
			<button type="button" onclick="this.form.status.selectedIndex = 3;">{'crm.opp.status.closed.lost'|devblocks_translate|lower}</button>
			{if $active_worker->hasPriv('crm.opp.actions.delete')}
			<button type="button" onclick="this.form.status.selectedIndex = 4;">{'status.deleted'|devblocks_translate|lower}</button>
			{/if}
			</td>
		</tr>
		
		{if $active_worker->hasPriv('core.watchers.assign')}
		<tr>
			<td width="0%" nowrap="nowrap" align="right" valign="top">Add watchers:</td>
			<td width="100%">
				<div>
					<button type="button" class="chooser-abstract" data-field-name="do_watcher_add_ids[]" data-context="{CerberusContexts::CONTEXT_WORKER}" data-query="isDisabled:n" data-autocomplete="true"><span class="glyphicons glyphicons-search"></span></button>
					<ul class="bubbles chooser-container" style="display:block;"></ul>
				</div>
			</td>
		</tr>
		{/if}
		
		{if $active_worker->hasPriv('core.watchers.unassign')}
		<tr>
			<td width="0%" nowrap="nowrap" align="right" valign="top">Remove watchers:</td>
			<td width="100%">
				<div>
					<button type="button" class="chooser-abstract" data-field-name="do_watcher_remove_ids[]" data-context="{CerberusContexts::CONTEXT_WORKER}" data-query="isDisabled:n" data-autocomplete="true"><span class="glyphicons glyphicons-search"></span></button>
					<ul class="bubbles chooser-container" style="display:block;"></ul>
				</div>
			</td>
		</tr>
		{/if}

		<tr>
			<td width="0%" nowrap="nowrap" align="right">{'crm.opportunity.closed_date'|devblocks_translate|capitalize}:</td>
			<td width="100%">
				<input type="text" name="closed_date" size=35 value=""><button type="button" onclick="devblocksAjaxDateChooser(this.form.closed_date,'#dateOppBulkClosed');"><span class="glyphicons glyphicons-calendar"></span></button>
				<div id="dateOppBulkClosed"></div>
			</td>
		</tr>
	</table>
</fieldset>

{if !empty($custom_fields)}
<fieldset class="peek">
	<legend>Set Custom Fields</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=true}	
</fieldset>
{/if}

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=CerberusContexts::CONTEXT_OPPORTUNITY bulk=true}

{include file="devblocks:cerberusweb.core::internal/macros/behavior/bulk.tpl" macros=$macros}

{if $active_worker->hasPriv('crm.opp.view.actions.broadcast')}
<fieldset class="peek">
	<legend>Send Broadcast</legend>
	
	<label><input type="checkbox" name="do_broadcast" id="chkMassReply" onclick="$('#bulkOppBroadcast').toggle();">{'common.enabled'|devblocks_translate|capitalize}</label>
	<input type="hidden" name="broadcast_format" value="">
	
	<blockquote id="bulkOppBroadcast" style="display:none;margin:10px;">
		<b>From:</b>
		
		<div style="margin:0px 0px 5px 10px;">
			<select name="broadcast_group_id">
				{foreach from=$groups item=group key=group_id}
				{if $active_worker_memberships.$group_id}
				<option value="{$group->id}">{$group->name}</option>
				{/if}
				{/foreach}
			</select>
		</div>
		
		<b>Subject:</b>
		
		<div style="margin:0px 0px 5px 10px;">
			<input type="text" name="broadcast_subject" value="" style="width:100%;">
		</div>
		
		<b>Compose:</b>
		
		<div style="margin:0px 0px 5px 10px;">
			<textarea name="broadcast_message" style="width:100%;height:200px;"></textarea>
			
			<div>
				<button type="button" onclick="ajax.chooserSnippet('snippets',$('#bulkOppBroadcast textarea[name=broadcast_message]'), { '{CerberusContexts::CONTEXT_OPPORTUNITY}':'', '{CerberusContexts::CONTEXT_WORKER}':'{$active_worker->id}' });">{'common.snippets'|devblocks_translate|capitalize}</button>
				<select class="insert-placeholders">
					<option value="">-- insert at cursor --</option>
					{foreach from=$token_labels key=k item=v}
					<option value="{literal}{{{/literal}{$k}{literal}}}{/literal}">{$v}</option>
					{/foreach}
				</select>
			</div>
		</div>
		
		<b>{'common.attachments'|devblocks_translate|capitalize}:</b>
		
		<div style="margin:0px 0px 5px 10px;">
			<button type="button" class="chooser_file"><span class="glyphicons glyphicons-paperclip"></span></button>
			<ul class="bubbles chooser-container">
		</div>
		
		<b>{'common.status'|devblocks_translate|capitalize}:</b>
		
		<div style="margin:0px 0px 5px 10px;">
			<label><input type="radio" name="broadcast_status_id" value="{Model_Ticket::STATUS_OPEN}"> {'status.open'|devblocks_translate|capitalize}</label>
			<label><input type="radio" name="broadcast_status_id" value="{Model_Ticket::STATUS_WAITING}" checked="checked"> {'status.waiting'|devblocks_translate|capitalize}</label>
			<label><input type="radio" name="broadcast_status_id" value="{Model_Ticket::STATUS_CLOSED}"> {'status.closed'|devblocks_translate|capitalize}</label>
		</div>
		
		<b>{'common.options'|devblocks_translate|capitalize}:</b>
		
		<div style="margin:0px 0px 5px 10px;">
			<label><input type="radio" name="broadcast_is_queued" value="0" checked="checked"> Save as drafts</label>
			<label><input type="radio" name="broadcast_is_queued" value="1"> Send now</label>
		</div>
	</blockquote>
</fieldset>
{/if}

<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
</form>

<script type="text/javascript">
$(function() {
	var $popup = genericAjaxPopupFetch('peek');
	
	$popup.one('popup_open', function(event,ui) {
		var $this = $(this);
		
		$popup.dialog('option','title',"{'common.bulk_update'|devblocks_translate|capitalize|escape:'javascript' nofilter}");
	
		$popup.find('button.chooser-abstract').cerbChooserTrigger();
		
		var $content = $this.find('textarea[name=broadcast_message]');
		
		$popup.find('button.submit').click(function() {
			genericAjaxPost('formBatchUpdate', '', null, function(json) {
				if(json.cursor) {
					// Pull the cursor
					var $tips = $('#{$view_id}_tips').html('');
					var $spinner = $('<span class="cerb-ajax-spinner"/>').appendTo($tips);
					genericAjaxGet($tips, 'c=internal&a=viewBulkUpdateWithCursor&view_id={$view_id}&cursor=' + json.cursor);
				}
				
				genericAjaxPopupClose($popup);
			});
		});
		
		$this.find('select.insert-placeholders').change(function(e) {
			var $select = $(this);
			var $val = $select.val();
			
			if($val.length == 0)
				return;
			
			$content.insertAtCursor($val).focus();
			
			$select.val('');
		});
		
		$this.find('button.chooser_file').each(function() {
			ajax.chooserFile(this,'broadcast_file_ids');
		});
		
		// Text editor
		
		var markitupPlaintextSettings = $.extend(true, { }, markitupPlaintextDefaults);
		var markitupParsedownSettings = $.extend(true, { }, markitupParsedownDefaults);
		
		var markitupBroadcastFunctions = {
			switchToMarkdown: function(markItUp) { 
				$content.markItUpRemove().markItUp(markitupParsedownSettings);
				$content.closest('form').find('input:hidden[name=broadcast_format]').val('parsedown');
				
				// Template chooser
				
				var $ul = $content.closest('.markItUpContainer').find('.markItUpHeader UL');
				var $li = $('<li style="margin-left:10px;"></li>');
				
				var $select = $('<select name="broadcast_html_template_id"></select>');
				$select.append($('<option value="0"/>').text(' - {'common.default'|devblocks_translate|lower|escape:'javascript'} -'));
				
				{foreach from=$html_templates item=html_template}
				var $option = $('<option/>').attr('value','{$html_template->id}').text('{$html_template->name|escape:'javascript'}');
				$select.append($option);
				{/foreach}
				
				$li.append($select);
				$ul.append($li);
			},
			
			switchToPlaintext: function(markItUp) {
				$content.markItUpRemove().markItUp(markitupPlaintextSettings);
				$content.closest('form').find('input:hidden[name=broadcast_format]').val('');
			}
		};
		
		markitupPlaintextSettings.markupSet.unshift(
			{ name:'Switch to Markdown', openWith: markitupBroadcastFunctions.switchToMarkdown, className:'parsedown' }
		);
		
		markitupPlaintextSettings.markupSet.push(
			{ separator:' ' },
			{ name:'Preview', key: 'P', call:'preview', className:"preview" }
		);
		
		var previewParser = function(content) {
			genericAjaxPost(
				'formBatchUpdate',
				'',
				'c=crm&a=doOppBulkUpdateBroadcastTest',
				function(o) {
					content = o;
				},
				{
					async: false
				}
			);
			
			return content;
		};
		
		markitupPlaintextSettings.previewParser = previewParser;
		markitupPlaintextSettings.previewAutoRefresh = false;
		
		markitupParsedownSettings.previewParser = previewParser;
		markitupParsedownSettings.previewAutoRefresh = false;
		delete markitupParsedownSettings.previewInWindow;
		
		markitupParsedownSettings.markupSet.unshift(
			{ name:'Switch to Plaintext', openWith: markitupBroadcastFunctions.switchToPlaintext, className:'plaintext' },
			{ separator:' ' }
		);
		
		markitupParsedownSettings.markupSet.splice(
			6,
			0,
			{ name:'Upload an Image', openWith: 
				function(markItUp) {
					$chooser=genericAjaxPopup('chooser','c=internal&a=chooserOpenFile&single=1',null,true,'750');
					
					$chooser.one('chooser_save', function(event) {
						if(!event.response || 0 == event.response)
							return;
						
						$content.insertAtCursor("![inline-image](" + event.response[0].url + ")");
					});
				},
				key: 'U',
				className:'image-inline'
			}
		);
		
		try {
			$content.markItUp(markitupPlaintextSettings);
			
		} catch(e) {
			if(window.console)
				console.log(e);
		}
		
	});
});
</script>