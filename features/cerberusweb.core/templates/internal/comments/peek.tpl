{$div_id = "peek{uniqid()}"}
{$peek_context = 'cerberusweb.contexts.comment'}
{$is_writeable = Context_Comment::isWriteableByActor($dict, $active_worker)}

<div id="{$div_id}">
	<div style="float:left;">
		<div style="margin-top:5px;">
			{if $is_writeable}
			<button type="button" class="cerb-peek-edit" data-context="{$peek_context}" data-context-id="{$dict->id}" data-edit="true"><span class="glyphicons glyphicons-cogwheel"></span> {'common.edit'|devblocks_translate|capitalize}</button>
			{/if}
			
			{if $dict->id}<button type="button" class="cerb-peek-profile"><span class="glyphicons glyphicons-nameplate"></span> {'common.profile'|devblocks_translate|capitalize}</button>{/if}
		</div>
	</div>
</div>

<div style="clear:both;padding-top:10px;"></div>

<fieldset class="peek">
	<legend>{'common.properties'|devblocks_translate|capitalize}</legend>
	
	<div class="cerb-properties-grid" data-column-width="100">
	
		{$labels = $dict->_labels}
		{$types = $dict->_types}
		{foreach from=$properties item=k name=props}
			{if $dict->$k}
			<div>
			{if $k == ''}
			{else}
				{include file="devblocks:cerberusweb.core::internal/peek/peek_property_grid_cell.tpl" dict=$dict k=$k labels=$labels types=$types}
			{/if}
			</div>
			{/if}
		{/foreach}
	</div>
	
	<div style="clear:both;"></div>
</fieldset>

{include file="devblocks:cerberusweb.core::internal/profiles/profile_record_links.tpl" properties_links=$links peek=true page_context=$peek_context page_context_id=$dict->id}

{include file="devblocks:cerberusweb.core::internal/peek/card_timeline_pager.tpl"}

<script type="text/javascript">
$(function() {
	var $div = $('#{$div_id}');
	var $popup = genericAjaxPopupFind($div);
	var $layer = $popup.attr('data-layer');
	
	var $timeline = {$timeline_json|default:'{}' nofilter};
	
	$popup.one('popup_open',function(event,ui) {
		$popup.dialog('option','title', "{'comment'|devblocks_translate|capitalize|escape:'javascript' nofilter}");
		$popup.css('overflow', 'inherit');
		
		// Properties grid
		$popup.find('div.cerb-properties-grid').cerbPropertyGrid();
		
		// Edit button
		{if $is_writeable}
		$popup.find('button.cerb-peek-edit')
			.cerbPeekTrigger({ 'view_id': '{$view_id}' })
			.on('cerb-peek-saved', function(e) {
				genericAjaxPopup($layer,'c=internal&a=showPeekPopup&context={$peek_context}&context_id={$dict->id}&view_id={$view_id}','reuse',false,'50%');
			})
			.on('cerb-peek-deleted', function(e) {
				genericAjaxPopupClose($layer);
			})
			;
		{/if}
		
		// Peeks
		$popup.find('.cerb-peek-trigger')
			.cerbPeekTrigger()
			;
		
		// Searches
		$popup.find('.cerb-search-trigger')
			.cerbSearchTrigger()
			;
		
		// Menus
		$popup.find('ul.cerb-menu').menu();
		
		// View profile
		$popup.find('.cerb-peek-profile').click(function(e) {
			if(e.metaKey) {
				window.open('{devblocks_url}c=profiles&type=comment&id={$dict->id}-{$dict->_label|devblocks_permalink}{/devblocks_url}', '_blank');
				
			} else {
				document.location='{devblocks_url}c=profiles&type=comment&id={$dict->id}-{$dict->_label|devblocks_permalink}{/devblocks_url}';
			}
		});
		
		// Timeline
		{include file="devblocks:cerberusweb.core::internal/peek/card_timeline_script.tpl"}
	});
});
</script>
