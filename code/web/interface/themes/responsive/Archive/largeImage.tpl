{* {strip} *}
	<div class="col-xs-12">
		{* Search Navigation *}
		{include file="Archive/search-results-navigation.tpl"}
		<h1>
			{$title}
			{*{$title|escape} // plb 3/8/2017 not escaping because some titles use &amp; *}
		</h1>

		{if $canView}
			{if $noImage}
				<div class="alert alert-warning">
					Sorry we could not find an image for this object.
				</div>
			{elseif $large_image}
				<div class="large-image-wrapper">
					{* <a id="clip" title="Clip Image" href="#">
						Clip Image
					</a> *}
					<div class="large-image-content">
						<div id="custom-openseadragon" class="openseadragon" oncontextmenu="return false;"></div>
					</div>
				</div>
			{else}
				<div class="main-project-image">
					<img src="{$image}" class="img-responsive">
				</div>
			{/if}
		{else}
			{include file="Archive/noAccess.tpl"}
		{/if}

		<div id="download-options">
			{if $canView}
				{if $hasLargeImage && ($anonymousLcDownload || ($loggedIn && $verifiedLcDownload))}
					<a class="btn btn-default" href="/Archive/{$pid}/DownloadLC">Download Large Image</a>
				{elseif ($hasLargeImage && !$loggedIn && $verifiedLcDownload)}
					<a class="btn btn-default" onclick="return AspenDiscovery.Account.followLinkIfLoggedIn(this)" href="/Archive/{$pid}/DownloadLC">Sign in to Download Large Image</a>
				{/if}
				{if $anonymousOriginalDownload || ($loggedIn && $verifiedOriginalDownload)}
					<a class="btn btn-default" href="/Archive/{$pid}/DownloadOriginal">Download Original Image</a>
				{elseif (!$loggedIn && $verifiedOriginalDownload)}
					<a class="btn btn-default" onclick="return AspenDiscovery.Account.followLinkIfLoggedIn(this)" href="/Archive/{$pid}/DownloadOriginal">Sign in to Download Original Image</a>
				{/if}
			{/if}
			{if $allowRequestsForArchiveMaterials}
				<a class="btn btn-default" href="/Archive/RequestCopy?pid={$pid}">Request Copy</a>
			{/if}
			{if $showClaimAuthorship}
				<a class="btn btn-default" href="/Archive/ClaimAuthorship?pid={$pid}">Claim Authorship</a>
			{/if}
			{if $showFavorites == 1}
				<a onclick="return AspenDiscovery.Account.showSaveToListForm(this, 'Islandora', '{$pid|escape}');" class="btn btn-default ">{translate text='Add to list'}</a>
			{/if}
		</div>

		{include file="Archive/metadata.tpl"}
	</div>
	<script src="/js/openseadragon/openseadragon.js" ></script>
	<script src="/js/openseadragon/djtilesource.js" ></script>
{if $canView}
	<script type="text/javascript">
		$(document).ready(function(){ldelim}
			if (!$('#custom-openseadragon').hasClass('processed')) {ldelim}
				var openSeadragonSettings = {ldelim}
					"pid":"{$pid}",
					"resourceUri":{$large_image|@json_encode nofilter},
					"tileSize":256,
					"tileOverlap":0,
					"id":"custom-openseadragon",
					"settings": {ldelim}
							"id":"custom-openseadragon",
							"prefixUrl":"{$encodedRepositoryUrl}\/sites\/all\/libraries\/openseadragon\/images\/",
							"debugMode":false,
							"djatokaServerBaseURL":"\/AJAX\/DjatokaResolver",
							"tileSize":256,
							"tileOverlap":0,
							"animationTime":1.5,
							"blendTime":0.1,
							"alwaysBlend":false,
							"autoHideControls":1,
							"immediateRender":true,
							"wrapHorizontal":false,
							"wrapVertical":false,
							"wrapOverlays":false,
							"panHorizontal":1,
							"panVertical":1,
							"minZoomImageRatio":0.35,
							"maxZoomPixelRatio":2,
							"visibilityRatio":0.5,
							"springStiffness":5,
							"imageLoaderLimit":5,
							"clickTimeThreshold":300,
							"clickDistThreshold":5,
							"zoomPerClick":2,
							"zoomPerScroll":1.2,
							"zoomPerSecond":2,
							"showNavigator":1,
							"defaultZoomLevel":1
					{rdelim}
				{rdelim};
				openSeadragonSettings.settings.tileSources = new Array();
				var tileSource = new OpenSeadragon.DjatokaTileSource(
						Globals.url + "/AJAX/DjatokaResolver",
						'{$large_image}',
						openSeadragonSettings.settings
				);
				openSeadragonSettings.settings.tileSources.push(tileSource);

				var viewer = new OpenSeadragon(openSeadragonSettings.settings);
				$('#custom-openseadragon').addClass('processed');
			{rdelim}
		{rdelim});
	</script>
{/if}
{* {/strip} *}
<script type="text/javascript">
	$().ready(function(){ldelim}
		AspenDiscovery.Archive.loadExploreMore('{$pid|urlencode}');
	{rdelim});
</script>