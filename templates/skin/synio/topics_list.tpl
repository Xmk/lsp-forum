{foreach from=$aTopics item=oTopic}
	{assign var='oUser' value=$oTopic->getUser()}
	{assign var='oPost' value=$oTopic->getPost()}
	{assign var='oPoster' value=$oPost->getUser()}
	<tr id="topic-{$oTopic->getId()}" class="topic-item{if !$oTopic->getRead()} unread{/if}{if $oTopic->getHot()} hot{/if}{if $oTopic->getPinned()} pinned{/if}{if $oTopic->getState()} close{/if}">
		<td class="cell-icon">
			<a class="topic-icon" href="{$oTopic->getUrlFull()}" title="{if $oTopic->getRead()}{$aLang.plugin.forum.topic_read}{else}{$aLang.plugin.forum.topic_unread}{/if}"></a>
		</td>
		<td class="cell-name">
			<h4>
				<div class="author">
					<a href="{$oUser->getUserWebPath()}"><img src="{$oUser->getProfileAvatarPath(48)}" title="{$aLang.plugin.forum.header_author}: {$oUser->getLogin()}" /></a>
				</div>
				{if $oTopic->getPinned()==1}
					<span class="badge">{$aLang.plugin.forum.topic_pinned}</span>
				{/if}
				<a href="{$oTopic->getUrlFull()}">{$oTopic->getTitle()|escape:'html'}</a>
				{include file="$sTemplatePathForum/paging_post.tpl" aPaging=$oTopic->getPaging()}
			</h4>
			{if $oTopic->getDescription()}
			<p class="lighter">
				<small>{$oTopic->getDescription()|escape:'html'}</small>
			</p>
			{/if}
			<p>
				{date_format date=$oTopic->getDateAdd()}
			</p>
		</td>
		<td class="cell-stats ta-r">
			<ul>
				<li><strong>{$oTopic->getCountPost()|number_format:0:'.':$oConfig->Get('plugin.forum.number_format')}</strong> {$oTopic->getCountPost()|declension:$aLang.plugin.forum.posts_declension:'russian'|lower}</li>
				<li><strong>{$oTopic->getViews()|number_format:0:'.':$oConfig->Get('plugin.forum.number_format')}</strong> {$oTopic->getViews()|declension:$aLang.plugin.forum.views_declension:'russian'|lower}</li>
			</ul>
		</td>
		<td class="cell-post">
			<div class="author">
				{if $oPoster}
					<a href="{$oPoster->getUserWebPath()}"><img src="{$oPoster->getProfileAvatarPath(48)}" title="{$aLang.plugin.forum.post_writer}: {$oPoster->getLogin()|escape:'html'}" /></a>
				{else}
					<a href="{$oTopic->getUrlFull()}lastpost"><img src="{cfg name='path.static.skin'}/images/avatar_male_48x48.png" title="{$aLang.plugin.forum.post_writer}: {$aLang.plugin.forum.guest_prefix}{$oPost->getGuestName()|escape:'html'}" /></a>
				{/if}
			</div>
			<ul class="last-post">
				<li>
				{if $oPoster}
					<a href="{$oPoster->getUserWebPath()}">{$oPoster->getLogin()|escape:'html'}</a>
				{else}
					<a href="{$oTopic->getUrlFull()}lastpost">{$aLang.plugin.forum.guest_prefix}{$oPost->getGuestName()|escape:'html'}</a>
				{/if}
				</li>
				<li><a class="date" title="{$aLang.plugin.forum.post_last_view}" href="{$oTopic->getUrlFull()}lastpost">{date_format date=$oPost->getDateAdd() day="day H:i" format="j F Y, H:i"}</a></li>
			</ul>
		</td>
	</tr>
{/foreach}