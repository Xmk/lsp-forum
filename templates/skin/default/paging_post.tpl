{if $aPaging and $aPaging.iCountPage>1}
<ul class="pagination_mini">
	{if $aPaging.iCurrentPage>1}
		<li><a href="{$aPaging.sBaseUrl}">{$aLang.paging_first}</a></li>
	{/if}

	{foreach from=$aPaging.aPagesLeft item=iPage}
		<li><a href="{$aPaging.sBaseUrl}/page{$iPage}" title="{$aLang.plugin.forum.on_page}: {$iPage}">{$iPage}</a></li>
	{/foreach}

	<li><a href="{$aPaging.sBaseUrl}/page{$aPaging.iCurrentPage}" title="{$aLang.plugin.forum.on_page}: {$aPaging.iCurrentPage}">{$aPaging.iCurrentPage}</a></li>

	{foreach from=$aPaging.aPagesRight item=iPage}
		<li><a href="{$aPaging.sBaseUrl}/page{$iPage}" title="{$aLang.plugin.forum.on_page}: {$iPage}">{$iPage}</a></li>
	{/foreach}

	{if $aPaging.iCountPage>5}
		<li><a href="{$aPaging.sBaseUrl}/page{$aPaging.iCountPage}" title="{$aLang.plugin.forum.on_page}: {$aPaging.iCountPage}">{$aLang.paging_last}</a></li>
	{/if}
</ul>
{/if}