<fieldset class="peek">
<legend>{'reports.ui.worker.worker_history'|devblocks_translate}</legend>

<form action="{devblocks_url}c=pages&page={$page->id}-{$page->name|devblocks_permalink}&report=report.workers.worker_history{/devblocks_url}" method="POST" id="frmRange" name="frmRange">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">
<b>{'reports.ui.date_from'|devblocks_translate}</b> <input type="text" name="start" id="start" size="24" value="{$start}"><button type="button" onclick="devblocksAjaxDateChooser('#start','#divCal');"><span class="glyphicons glyphicons-calendar"></span></button>
<b>{'reports.ui.date_to'|devblocks_translate}</b> <input type="text" name="end" id="end" size="24" value="{$end}"><button type="button" onclick="devblocksAjaxDateChooser('#end','#divCal');"><span class="glyphicons glyphicons-calendar"></span></button>
<b>Grouping:</b> <select name="report_date_grouping">
	<option value="">-auto-</option>
	<option value="year" {if 'year'==$report_date_grouping}selected="selected"{/if}>Years</option>
	<option value="month" {if 'month'==$report_date_grouping}selected="selected"{/if}>Months</option>
	<option value="week" {if 'week'==$report_date_grouping}selected="selected"{/if}>Weeks</option>
	<option value="day" {if 'day'==$report_date_grouping}selected="selected"{/if}>Days</option>
	<option value="hour" {if 'hour'==$report_date_grouping}selected="selected"{/if}>Hours</option>
</select>
<div id="divCal"></div>

<b>{'reports.ui.date_past'|devblocks_translate}</b> <a href="javascript:;" onclick="$('#start').val('-1 year');$('#end').val('now');">{'reports.ui.filters.1_year'|devblocks_translate|lower}</a>
| <a href="javascript:;" onclick="$('#start').val('-6 months');$('#end').val('now');">{'reports.ui.filters.n_months'|devblocks_translate:6}</a>
| <a href="javascript:;" onclick="$('#start').val('-3 months');$('#end').val('now');">{'reports.ui.filters.n_months'|devblocks_translate:3}</a>
| <a href="javascript:;" onclick="$('#start').val('-1 month');$('#end').val('now');">{'reports.ui.filters.1_month'|devblocks_translate|lower}</a>
| <a href="javascript:;" onclick="$('#start').val('-1 week');$('#end').val('now');">{'reports.ui.filters.1_week'|devblocks_translate|lower}</a>
| <a href="javascript:;" onclick="$('#start').val('-1 day');$('#end').val('now');">{'reports.ui.filters.1_day'|devblocks_translate|lower}</a>
| <a href="javascript:;" onclick="$('#start').val('today');$('#end').val('now');">{'common.today'|devblocks_translate|lower}</a>
<br>
{if !empty($years)}
	{foreach from=$years item=year name=years}
		{if !$smarty.foreach.years.first} | {/if}<a href="javascript:;" onclick="$('#start').val('Jan 1 {$year}');$('#end').val('Dec 31 {$year} 23:59:59');">{$year}</a>
	{/foreach}
	<br>
{/if}

<div>
	<b>{'reports.ui.filters.worker'|devblocks_translate}</b>
	<button type="button" class="chooser-abstract" data-field-name="worker_id[]" data-context="{CerberusContexts::CONTEXT_WORKER}" data-query="" data-autocomplete=""><span class="glyphicons glyphicons-search"></span></button>
	<ul class="bubbles chooser-container">
		{foreach from=$filter_worker_ids item=filter_worker_id}
		{$filter_worker = $workers.{$filter_worker_id}}
		{if !empty($filter_worker)}
		<li><img class="cerb-avatar" src="{devblocks_url}c=avatars&context=worker&context_id={$filter_worker->id}{/devblocks_url}?v={$filter_worker->updated}"><input type="hidden" name="worker_id[]" value="{$filter_worker->id}"><a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{CerberusContexts::CONTEXT_WORKER}" data-context-id="{$filter_worker->id}">{$filter_worker->getName()}</a></li>
		{/if}
		{/foreach}
	</ul>
</div>

<div>
	<b>{'reports.ui.filters.group'|devblocks_translate}</b> 
	<button type="button" class="chooser-abstract" data-field-name="group_id[]" data-context="{CerberusContexts::CONTEXT_GROUP}" data-query="" data-autocomplete=""><span class="glyphicons glyphicons-search"></span></button>
	<ul class="bubbles chooser-container">
		{foreach from=$filter_group_ids item=filter_group_id}
		{$filter_group = $groups.{$filter_group_id}}
		{if !empty($filter_group)}
		<li><img class="cerb-avatar" src="{devblocks_url}c=avatars&context=group&context_id={$filter_group->id}{/devblocks_url}?v={$filter_group->updated}"><input type="hidden" name="group_id[]" value="{$filter_group->id}"><a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{CerberusContexts::CONTEXT_GROUP}" data-context-id="{$filter_group->id}">{$filter_group->name}</a></li>
		{/if}
		{/foreach}
	</ul>
</div>

<button type="submit" id="btnSubmit">{'reports.common.run_report'|devblocks_translate|capitalize}</button>
</form>
</fieldset>

<!-- Chart -->

{if !empty($data)}

<!--[if IE]><script language="javascript" type="text/javascript" src="{devblocks_url}c=resource&plugin=cerberusweb.reports&f=js/jqplot/excanvas.min.js{/devblocks_url}?v={$smarty.const.APP_BUILD}"></script><![endif]-->
<script language="javascript" type="text/javascript" src="{devblocks_url}c=resource&plugin=cerberusweb.reports&f=js/jqplot/jquery.jqplot.min.js{/devblocks_url}?v={$smarty.const.APP_BUILD}"></script>
<script language="javascript" type="text/javascript" src="{devblocks_url}c=resource&plugin=cerberusweb.reports&f=js/jqplot/plugins/jqplot.highlighter.min.js{/devblocks_url}?v={$smarty.const.APP_BUILD}"></script>
<script language="javascript" type="text/javascript" src="{devblocks_url}c=resource&plugin=cerberusweb.reports&f=js/jqplot/plugins/jqplot.barRenderer.min.js{/devblocks_url}?v={$smarty.const.APP_BUILD}"></script>
<script language="javascript" type="text/javascript" src="{devblocks_url}c=resource&plugin=cerberusweb.reports&f=js/jqplot/plugins/jqplot.canvasTextRenderer.min.js{/devblocks_url}?v={$smarty.const.APP_BUILD}"></script>
<script language="javascript" type="text/javascript" src="{devblocks_url}c=resource&plugin=cerberusweb.reports&f=js/jqplot/plugins/jqplot.canvasAxisTickRenderer.min.js{/devblocks_url}?v={$smarty.const.APP_BUILD}"></script>
<script language="javascript" type="text/javascript" src="{devblocks_url}c=resource&plugin=cerberusweb.reports&f=js/jqplot/plugins/jqplot.canvasAxisLabelRenderer.min.js{/devblocks_url}?v={$smarty.const.APP_BUILD}"></script>
<script language="javascript" type="text/javascript" src="{devblocks_url}c=resource&plugin=cerberusweb.reports&f=js/jqplot/plugins/jqplot.categoryAxisRenderer.min.js{/devblocks_url}?v={$smarty.const.APP_BUILD}"></script>
<link rel="stylesheet" type="text/css" href="{devblocks_url}c=resource&plugin=cerberusweb.reports&f=css/jqplot/jquery.jqplot.min.css{/devblocks_url}?v={$smarty.const.APP_BUILD}" />

<style type="text/css">
.jqplot-highlighter-tooltip,.jqplot-canvasOverlay-tooltip {
	border: 5px solid rgb(249,233,142);
	border-radius: 5px;
	color: rgb(164,125,50);
	font-size: 1.2em;
	white-space: nowrap;
	background: rgb(251,247,170);
	padding: 5px;
	margin-bottom:3px;
}
</style>

<div id="reportLegend" style="margin:5px;"></div>
<div id="reportChart" style="width:98%;height:350px;"></div>

<script type="text/javascript">
{foreach from=$data item=plots key=worker_id}
line{$worker_id} = [{foreach from=$plots key=plot item=freq name=plots}
{$freq}{if !$smarty.foreach.plots.last},{/if}
{/foreach}
];
{/foreach}

chartData = [
{foreach from=$data item=null key=worker_id name=workers}line{$worker_id}{if !$smarty.foreach.workers.last},{/if}{/foreach}
];

var cerbChartStyle = {
	seriesColors: [
		'rgba(115,168,0,0.8)',
		'rgba(207,218,30,0.8)',
		'rgba(249,190,49,0.8)',
		'rgba(244,89,9,0.8)',
		'rgba(238,24,49,0.8)',
		'rgba(189,19,79,0.8)',
		'rgba(50,37,238,0.8)',
		'rgba(87,109,243,0.8)',
		'rgba(116,87,229,0.8)',
		'rgba(143,46,137,0.8)',
		'rgba(241,124,242,0.8)',
		'rgba(180,117,198,0.8)',
		'rgba(196,191,210,0.8)',
		'rgba(18,134,49,0.8)',
		'rgba(44,187,105,0.8)',
		'rgba(184,197,146,0.8)',
		'rgba(46,124,180,0.8)',
		'rgba(84,189,199,0.8)',
		'rgba(24,200,252,0.8)',
		'rgba(254,194,153,0.8)',
		'rgba(213,153,160,0.8)',
		'rgba(244,237,86,0.8)',
		'rgba(204,137,59,0.8)',
		'rgba(157,88,44,0.8)',
		'rgba(108,46,45,0.8)'
	]
};

chartOptions = {
	stackSeries: true,
	legend:{ 
		show:false
	},
	title:{
		show: false 
	},
	grid:{
		shadow: false,
		background:'rgb(255,255,255)',
		borderWidth:0
	},
	seriesColors: cerbChartStyle.seriesColors,	
	seriesDefaults:{
		renderer:$.jqplot.BarRenderer,
		rendererOptions:{ 
			highlightMouseOver: true,
			barPadding:0,
			barMargin:0
		},
		shadow: false,
		fill:true,
		fillAndStroke:false,
		showLine:true,
		showMarker:false,
		markerOptions: {
			size:8,
			style:'filledCircle',
			shadow:false
		}
	},
	series:[
		{foreach from=$data key=worker_id item=worker name=workers}{ label:'{$workers.$worker_id->getName()}' }{if !$smarty.foreach.workers.last},{/if}{/foreach}
	],
	axes:{
		xaxis:{
		  renderer:$.jqplot.CategoryAxisRenderer,
		  tickRenderer: $.jqplot.CanvasAxisTickRenderer,
		  tickOptions: {
		  	{if count($xaxis_ticks) > 94}show:false,{/if}
		  	showGridline:false,
			{if count($xaxis_ticks) < 94 && count($xaxis_ticks) > 13}
			angle: 90,
			{/if}
			fontSize: '8pt'
		  },
		  ticks:['{implode("','",$xaxis_ticks) nofilter}']
		}, 
		yaxis:{
		  labelRenderer: $.jqplot.CanvasAxisLabelRenderer,
		  label:'(# replies)',
		  min:0,
		  autoscale:true,
		  tickRenderer: $.jqplot.CanvasAxisTickRenderer,
		  tickOptions:{
		  	formatString:'%d',
			fontSize: '8pt'
		  }
		}
	},
	highlighter:{
		show: true,
		showMarket: true,
		tooltipLocation: 'n',
		tooltipContentEditor: function(str, seriesIndex, pointIndex, plot) {
			return plot.series[seriesIndex]["label"] + ": " + plot.data[seriesIndex][pointIndex] + "<br>" + plot.axes.xaxis.ticks[pointIndex];
		}
	}
};

$('#reportChart').bind('jqplotPostDraw',function(event, plot) {
	$legend = $('#reportLegend');
	$legend.html('');
	len = plot.series.length;
	for(series in plot.series) {
		$cell = $('<span style="margin-right:5px;display:inline-block;"/>')
			.html(plot.series[series].label)
			.prepend($('<span style="display:inline-block;padding:0px;margin:2px;width:16px;height:16px;"/>')
				.css('background-color', plot.series[series].color)
				.append('&nbsp;')
			);
		$legend.append($cell);
	}
});

var plot1 = $.jqplot('reportChart', chartData, chartOptions);
</script>

{include file="devblocks:cerberusweb.reports::reports/_shared/chart_selector.tpl"}
{/if}

<br>

<!-- Table -->

{if $invalidDate}
	<div><font color="red"><b>{'reports.ui.invalid_date'|devblocks_translate}</b></font></div>
{elseif !empty($data) || !empty($view)}
	{include file="devblocks:cerberusweb.core::internal/views/search_and_view.tpl" view=$view}

	{$sums = array()}
	<div>
		<table cellpadding="5" cellspacing="0" border="0">
		<tr>
			<td></td>
			{foreach from=$data key=worker_id item=plots}
				<td style="font-weight:bold;" nowrap="nowrap">{$workers.{$worker_id}->getName()}</td>
			{/foreach}
		</tr>
		{foreach from=$xaxis_ticks item=tick}
		<tr>
			<td style="border-bottom:1px solid rgb(200,200,200);"><b>{$tick}</b></td>
			{foreach from=$data item=plots key=worker_id}
				<td align="center" style="border-bottom:1px solid rgb(200,200,200);">
				{if isset($plots.$tick)}
					{$plots.$tick}
					{$sums[$worker_id] = intval($sums.$worker_id) + $plots.$tick}
				{/if}
				</td>
			{/foreach}
		</tr>
		{/foreach}
		{if count($xaxis_ticks) > 10}
		<tr>
			<td></td>
			{foreach from=$data key=worker_id item=plots}
				<td style="font-weight:bold;" nowrap="nowrap">{$workers.{$worker_id}->getName()}</td>
			{/foreach}
		</tr>
		{/if}
		<tr>
			<td align="right">Sum</td>
			{foreach from=$sums item=sum}
				<td align="center">
					<b>{$sum}</b>
				</td>
			{/foreach}
		</tr>
		<tr>
			<td align="right">Mean</td>
			{foreach from=$sums item=sum}
				<td align="center">
					<b>{($sum/count($xaxis_ticks))|string_format:"%0.2f"}</b>
				</td>
			{/foreach}
		</tr>
		</table>
	</div>
{else}
	<div><b>No data.</b></div>
{/if}

<script type="text/javascript">
$(function() {
	var $frm = $('#frmRange');
	$frm.find('button.chooser-abstract').cerbChooserTrigger();
});
</script>
<br>