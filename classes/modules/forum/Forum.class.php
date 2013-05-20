<?php
/*---------------------------------------------------------------------------
* @Module Name: Forum
* @Description: Forum for LiveStreet
* @Version: 1.0
* @Author: Chiffa
* @LiveStreet Version: 1.0
* @File Name: Forum.class.php
* @License: CC BY-NC, http://creativecommons.org/licenses/by-nc/3.0/
*----------------------------------------------------------------------------
*/

class PluginForum_ModuleForum extends ModuleORM {
	const TOPIC_STATE_OPEN		= 0;
	const TOPIC_STATE_CLOSE		= 1;
	const TOPIC_STATE_MOVED		= 2;
	/**
	 * Глобальные маски
	 */
	const MASK_PERM_GUEST		= 1;
	const MASK_PERM_USER		= 2;
	const MASK_PERM_ADMIN		= 3;
	/**
	 * Префикс подфорумов для дерева
	 */
	const DEPTH_GUIDE			= '--';
	/**
	 * Объект текущего пользователя
	 */
	protected $oUserCurrent=null;
	/**
	 * Объект маппера форума
	 */
	protected $oMapperForum=null;

	/**
	 * Инициализация модуля
	 */
	public function Init() {
		parent::Init();
		/**
		 * Получаем текущего пользователя
		 */
		$this->oUserCurrent=$this->User_GetUserCurrent();
		/**
		 * Получаем объект маппера
		 */
		$this->oMapperForum=Engine::GetMapper(__CLASS__);
	}

	/**
	 * Перемещает топики в другой форум
	 *
	 * @param	integer	$sForumId
	 * @param	integer	$sForumIdNew
	 * @return	bool
	 */
	public function MoveTopics($sForumId,$sForumIdNew) {
		if ($res=$this->oMapperForum->MoveTopics($sForumId,$sForumIdNew)) {
			//чистим кеш
			$this->Cache_Clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG,array('PluginForum_ModuleForum_EntityTopic_save'));
			return $res;
		}
		return false;
	}

	/**
	 * Перемещает подфорумы в другой форум
	 *
	 * @param	integer	$sForumId
	 * @param	integer	$sForumIdNew
	 * @return	bool
	 */
	public function MoveForums($sForumId,$sForumIdNew) {
		if ($res=$this->oMapperForum->MoveForums($sForumId,$sForumIdNew)) {
			//чистим кеш
			$this->Cache_Clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG,array('PluginForum_ModuleForum_EntityForum_save'));
			return $res;
		}
		return false;
	}

	/**
	 * Получает слудующий по сортировке форум
	 *
	 * @param	integer	$iSort
	 * @param	integer	$sPid
	 * @param	string	$sWay
	 * @return	object
	 */
	public function GetNextForumBySort($iSort,$sPid,$sWay='up') {
		$sForumId=$this->oMapperForum->GetNextForumBySort($iSort,$sPid,$sWay);
		return $this->GetForumById($sForumId);
	}

	/**
	 * Получает значение максимальной сортировки
	 *
	 * @param	integer	$sPid
	 * @return	integer
	 */
	public function GetMaxSortByPid($sPid) {
		return $this->oMapperForum->GetMaxSortByPid($sPid);
	}

	/**
	 * Получает статистику форума
	 *
	* @return	array
	 */
	public function GetForumStats() {
		$aStats=array();
		/**
		 * Посетители
		 */
		if (class_exists('PluginAcewidgetmanager_ModuleVisitors') && Config::Get('plugin.forum.stats.online')) {
			$aStats['online']=array();
			$nCountVisitors=$this->PluginAceBlockManager_Visitors_GetVisitorsCount(300);
			$nCountGuest=$nCountVisitors;
			$nCountUsers=0;
			if ($aUsersLast=$this->User_GetUsersByDateLast(Config::Get('plugin.forum.stats.users_count'))) {
				$aStats['online']['users']=array();
				foreach ($aUsersLast as $oUser) {
					if ($oUser->isOnline()) {
						$aStats['online']['users'][]=$oUser;
						$nCountUsers++;
						$nCountGuest--;
					}
				}
				shuffle($aStats['online']['users']);
			}
			if ($nCountUsers > $nCountVisitors) $nCountVisitors=$nCountUsers;
			$aStats['online']['count_visitors']=$nCountVisitors;
			$aStats['online']['count_users']=$nCountUsers;
			$aStats['online']['count_quest']=$nCountGuest;
		}
		/**
		 * Дни рождения
		 * TODO:
		 * Написать запрос, возвращающих пользователей, дата рождения которых
		 * совпадает с текущей...
		 */
		if (Config::Get('plugin.forum.stats.bdays')) {
			if ($aUsers=$this->User_GetUsersByFilter(array('activate'=>1),array(),1,999)) {
				$aStats['bdays']=array();

				foreach ($aUsers['collection'] as $oUser) {
					if ($sProfileBirthday=$oUser->getProfileBirthday()) {
						if (date('m-d')==date('m-d',strtotime($sProfileBirthday))) {
							$aStats['bdays'][]=$oUser;
						}
					}
				}
			}
		}
		/**
		 * Получаем количество всех постов
		 */
		$aStats['count_all_posts']=$this->oMapperForum->GetCountPosts();
		/**
		 * Получаем количество всех топиков
		 */
		$aStats['count_all_topics']=$this->oMapperForum->GetCountTopics();
		/**
		 * Получаем количество всех юзеров
		 */
		$aStats['count_all_users']=$this->oMapperForum->GetCountUsers();
		/**
		 * Получаем последнего зарегистрировавшегося
		 */
		if (Config::Get('plugin.forum.stats.last_user')) {
			$aLastUsers=$this->User_GetUsersByDateRegister(1);
			$aStats['last_user']=end($aLastUsers);
		}

		return $aStats;
	}

	/**
	 * Считает инфу по количеству постов и топиков в подфорумах
	 *
	 * @param	object	$oRoot
	 * @return	object
	 */
	public function CalcChildren($oRoot) {
		$aChildren=$oRoot->getChildren();

		if (!empty($aChildren)) {
			foreach ($aChildren as $oForum) {
				$oForum=$this->CalcChildren($oForum);

				if ($oForum->getLastPostId() > $oRoot->getLastPostId()) {
					$oRoot->setLastPostId($oForum->getLastPostId());
				}

				$oRoot->setCountTopic($oRoot->getCountTopic() + $oForum->getCountTopic());
				$oRoot->setCountPost($oRoot->getCountPost() + $oForum->getCountPost());
			}
		}

		return $this->BuildPerms($oRoot,true);
	}

	/**
	 * Удаляет посты по массиву объектов
	 *
	 * @param	array	$aPosts
	 * @return	boolean
	 */
	public function DeletePosts($aPosts) {
		if (!is_array($aPosts)) {
			$aPosts = array($aPosts);
		}
		$aTopics=array();

		foreach ($aPosts as $oPost) {
			$aTopics[$oPost->getTopicId()] = 1;
			$oPost->Delete();
		}
		foreach (array_keys($aTopics) as $sTopicId) {
			$this->RecountTopic($sTopicId);
		}
		return true;
	}

	/**
	 * Пересчет счетчиков форума
	 *
	 * @param	object	$oForum
	 * @return	object
	 */
	public function RecountForum($oForum) {
		if (!($oForum instanceof Entity)) {
			$oForum = $this->GetForumById($oForum);
		}

		$iCountTopic=$this->oMapperForum->GetCountTopicByForumId($oForum->getId());
		$iCountPost=$this->oMapperForum->GetCountPostByForumId($oForum->getId());
		$iLastPostId=$this->oMapperForum->GetLastPostByForumId($oForum->getId());

		$oForum->setCountTopic((int)$iCountTopic);
		$oForum->setCountPost((int)$iCountPost);
		$oForum->setLastPostId((int)$iLastPostId);
		return $oForum->Save();
	}

	/**
	 * Пересчет счетчиков топика
	 *
	 * @param	object	$oForum
	 * @return	object
	 */
	public function RecountTopic($oTopic) {
		if (!($oTopic instanceof Entity)) {
			$oTopic = $this->GetTopicById($oTopic);
		}

		$iCountPost=$this->oMapperForum->GetCountPostByTopicId($oTopic->getId());
		$iLastPostId=$this->oMapperForum->GetLastPostByTopicId($oTopic->getId());

		$oTopic->setCountPost((int)$iCountPost);
		$oTopic->setLastPostId((int)$iLastPostId);
		return $oTopic->Save();
	}

	/**
	 * Формируем права доступа
	 *
	 * @param	object	$oForum
	 * @return	object
	 */
	public function BuildPerms($oForum,$bNoModers=false) {
		$oUser = LS::CurUsr();
		$oParent = $oForum->getParentId() ? $this->BuildPerms($oForum->getParent(),true) : null;
		/**
		 * Права модератора
		 */
		if (!$bNoModers) {
			$sId = $oUser ? $oUser->getId() : 0;
			$oModerator = $this->PluginForum_Forum_GetModeratorByUserIdAndForumId($sId,$oForum->getId());

			$oForum->setIsModerator(LS::Adm() || $oModerator);
			$oForum->setModViewIP(LS::Adm() || ($oModerator && $oModerator->getViewIp()));
			$oForum->setModDeletePost(LS::Adm() || ($oModerator && $oModerator->getAllowDeletePost()));
			$oForum->setModDeleteTopic(LS::Adm() || ($oModerator && $oModerator->getAllowDeleteTopic()));
			$oForum->setModMoveTopic(LS::Adm() || ($oModerator && $oModerator->getAllowMoveTopic()));
			$oForum->setModOpencloseTopic(LS::Adm() || ($oModerator && $oModerator->getAllowOpencloseTopic()));
			$oForum->setModPinTopic(LS::Adm() || ($oModerator && $oModerator->getAllowPinTopic()));
		}
		$aPermissions=unserialize(stripslashes($oForum->getPermissions()));

		$oForum->setAllowShow(check_perms($aPermissions['show_perms'],$oUser,true));
		$oForum->setAllowRead(check_perms($aPermissions['read_perms'],$oUser,true));
		$oForum->setAllowReply(check_perms($aPermissions['reply_perms'],$oUser));
		$oForum->setAllowStart(check_perms($aPermissions['start_perms'],$oUser));
		/**
		 * Авторизован ли текущий пользователь в данном форуме, при условии что форум запоролен
		 */
		$oForum->setAutorization($this->isForumAuthorization($oForum));
		/**
		 * Если у нас нет прав для просмотра родителя данного форума, запрещаем просмотр
		 */
		if ($oParent && !($oParent->getAllowShow())) {
			$oForum->setAllowShow($oParent->getAllowShow());
		}
		/**
		 * Маркер прочитанности
		 */
		$oForum->setMarker($this->GetMarker($oForum->getId()));
		return $oForum;
	}

	/**
	 * Проверяем, нужно ли юзеру вводить пароль
	 *
	 * @param	object	$oForum
	 * @return	boolean
	 */
	public function isForumAuthorization($oForum) {
		$bAccess=true;
		if ($oForum->getPassword()) {
			$bAccess=false;
			if (LS::CurUsr()) {
				if (forum_compare_password($oForum)) {
					$bAccess=true;
				}
			}
		}
		return $bAccess;
	}

	/**
	 * Возвращает список форумов, открытых для пользователя в виде дерева
	 *
	 * @param	object	$oForum
	 * @param	boolean	$bIdOnly
	 * @return	array
	 */
	public function GetOpenForumsTree($bCalc=true) {
		$oUserCurrent=$this->User_GetUserCurrent();
		/**
		 * Строит дерево
		 */
		$aForums=$this->LoadTreeOfForum(
			array(
				'#order'=>array('forum_sort'=>'asc')
			)
		);
		/**
		 * Калькулирует инфу о счетчиках и последнем сообщении из подфорумов
		 */
		if (!empty($aForums) && $bCalc) {
			foreach ($aForums as $oForum) {
				$oForum=$this->CalcChildren($oForum);
			}
		}
		return $aForums;
	}

	/**
	 * Возвращает список форумов, открытых для пользователя
	 *
	 * @param	object	$oForum
	 * @param	boolean	$bIdOnly
	 * @return	array
	 */
	public function GetOpenForumsUser($oUser=null,$bIdOnly=false) {
		$aForums=$this->GetForumItemsAll();
		/**
		 * Фильтруем список форумов
		 */
		$aRes=array();
		if (!empty($aForums)) {
			foreach ($aForums as $oForum) {
				$aPermissions=unserialize(stripslashes($oForum->getPermissions()));
				if (check_perms($aPermissions['show_perms'],$oUser,true)
				and check_perms($aPermissions['read_perms'],$oUser,true)
				and $this->isForumAuthorization($oForum)) {
					$aRes[$oForum->getId()]=$oForum;
				}
			}
		}
		return $bIdOnly ? array_keys($aRes) : $aRes;
	}

	/**
	 * Обновление просмотров топика
	 * Данные в БД обновляются раз в 10 минут
	 * @param	PluginForum_ModuleForum_EntityTopic	$oTopic
	 */
	public function UpdateTopicViews(PluginForum_ModuleForum_EntityTopic $oTopic) {
		if (false === ($data = $this->Cache_Get("topic_views_{$oTopic->getId()}"))) {
			$oView = $this->PluginForum_Forum_GetTopicViewByTopicId($oTopic->getId());
			if (!$oView) {
				$oView = LS::ENT('PluginForum_Forum_TopicView');
				$oView->setTopicId($oTopic->getId());
			}
			$oView->setTopicViews($oView->getTopicViews()+1);
			$data = array(
				'obj' => $oView,
				'time' => time()
			);
		} else {
			$data['obj']->setTopicViews($data['obj']->getTopicViews()+1);
		}
		if (!Config::Get('sys.cache.use') or $data['time']<time()-60*10) {
			$data['time'] = time();
			$data['obj']->Save();
		}
		$this->Cache_Set($data, "topic_views_{$oTopic->getId()}", array(), 60*60*24);
	}

	/**
	 * Отправка уведомления на отвеченные посты
	 *
	 * @param	PluginForum_ModuleForum_EntityPost	$oReply
	 * @param	array	$aExcludeMail
	 */
	public function SendNotifyReply(PluginForum_ModuleForum_EntityPost $oReply,$aExcludeMail=array()) {
		if ($oReply) {
			if (preg_match_all("@(<blockquote reply=(?:\"|')(.*)(?:\"|').*>)@Ui",$oReply->getTextSource(),$aMatch)) {
				$aIds = array_values($aMatch[2]);
				/**
				 * Получаем список постов
				 */
				$aPosts = $this->GetPostItemsByArrayPostId((array)$aIds);
				/**
				 * Отправка
				 */
				$sTemplate = 'notify.reply.tpl';
				$sSendTitle = $this->Lang_Get('plugin.forum.notify_subject_reply');
				$aSendContent = array(
					'oUser' => $oReply->getUser(),
					'oTopic' => $oReply->getTopic(),
					'oPost' => $oReply
				);
				foreach ($aPosts as $oPost) {
					if ($oUser = $oPost->getUser()) {
						$sMail = $oUser->getMail();
						if (!$sMail || in_array($sMail, (array)$aExcludeMail)) continue;
						$this->Notify_Send($sMail, $sTemplate, $sSendTitle, $aSendContent, __CLASS__);
					}
				}
			}
		}
	}

	/**
	 * Парсер текста
	 *
	 * @param	string	$sText
	 * @return	stiing
	 */
	public function TextParse($sText=null) {
		$this->Text_LoadJevixConfig('forum');
		/**
		 * @username
		 */
		if (preg_match_all('/@\w+/u',$sText,$aMatch)) {
			foreach ($aMatch as $aPart){
				foreach ($aPart as $str){
					$sText=str_replace($str, '<ls user="'.substr(trim($str), 1).'" />', $sText);
				}
			}
		}
		return $this->Text_Parser($sText);
	}

	/**
	 * Загружает иконку для форума
	 *
	 * @param array $aFile	Массив $_FILES при загрузке аватара
	 * @param PluginForum_ModuleForum_EntityForum $oForum
	 * @return bool
	 */
	public function UploadIcon($aFile, $oForum) {
		if(!is_array($aFile) || !isset($aFile['tmp_name'])) {
			return false;
		}

		$sFileTmp = Config::Get('sys.cache.dir').func_generator();
		if (!move_uploaded_file($aFile['tmp_name'], $sFileTmp)) {
			return false;
		}
		$sPath = Config::Get('plugin.forum.path_uploads_forum');
		$sPath = str_replace(Config::Get('path.root.server'), '', "/{$sPath}/{$oForum->getId()}/");
		$aParams = $this->Image_BuildParams('avatar');

		$oImage = $this->Image_CreateImageObject($sFileTmp);
		/**
		 * Если объект изображения не создан,
		 * возвращаем ошибку
		 */
		if($sError=$oImage->get_last_error()) {
			// Вывод сообщения об ошибки, произошедшей при создании объекта изображения
			// $this->Message_AddError($sError,$this->Lang_Get('error'));
			@unlink($sFileTmp);
			return false;
		}
		/**
		 * Срезаем квадрат
		 */
		$oImage = $this->Image_CropSquare($oImage);

		$aSize = Config::Get('plugin.forum.icon_size');
		rsort($aSize, SORT_NUMERIC);
		$sSizeBig = array_shift($aSize);
		if ($oImage && $sFileAvatar = $this->Image_Resize($sFileTmp, $sPath, "forum_icon_{$oForum->getId()}_{$sSizeBig}x{$sSizeBig}", Config::Get('view.img_max_width'), Config::Get('view.img_max_height'), $sSizeBig, $sSizeBig, false, $aParams, $oImage)) {
			foreach ($aSize as $iSize) {
				if ($iSize == 0) {
					$this->Image_Resize($sFileTmp, $sPath, "forum_icon_{$oForum->getId()}", Config::Get('view.img_max_width'), Config::Get('view.img_max_height'), null, null, false, $aParams, $oImage);
				} else {
					$this->Image_Resize($sFileTmp, $sPath, "forum_icon_{$oForum->getId()}_{$iSize}x{$iSize}", Config::Get('view.img_max_width'), Config::Get('view.img_max_height'), $iSize, $iSize, false, $aParams, $oImage);
				}
			}
			@unlink($sFileTmp);
			/**
			 * Если все нормально, возвращаем расширение загруженного аватара
			 */
			return $this->Image_GetWebPath($sFileAvatar);
		}
		@unlink($sFileTmp);
		/**
		 * В случае ошибки, возвращаем false
		 */
		return false;
	}
	/**
	 * Удаляет иконку форума с сервера
	 *
	 * @param PluginForum_ModuleForum_EntityForum $oForum
	 */
	public function DeleteIcon($oForum) {
		/**
		 * Если иконка есть, удаляем ее и ее рейсайзы
		 */
		if($oForum->getIcon()) {
			$aSize = Config::Get('plugin.forum.icon_size');
			foreach ($aSize as $iSize) {
				$this->Image_RemoveFile($this->Image_GetServerPath($oForum->getIconPath($iSize)));
			}
		}
	}

	/**
	 * Сортировка таблицы маркеров
	 *
	 * @param	array	$aData
	 * @param	string	$sFindId
	 * @return	array
	 */
	private function sortMarker($aData=array(),$sFindId=null) {
		$aSort = array();
		if ($oUser = $this->User_GetUserCurrent()) {
			$aMarkTopics = isset($aData['t']) ? $aData['t'] : array();
			$aMarkForums = isset($aData['f']) ? $aData['f'] : array();
			foreach ((array)$aMarkTopics as $sTopicId => $aTopicData) {
				$sForumId = $aTopicData['forum_id'];
				if ($sFindId && $sFindId != $sForumId) {
					continue;
				}
				if (!isset($aMarkForums[$sForumId])) {
					if (!isset($aSort[$sForumId])) {
						$aSort[$sForumId]['user_id'] = $oUser->getId();
						$aSort[$sForumId]['marker_read_array'] = array();
						$aSort[$sForumId]['marker_read_item'] = 0;
					}
					$aSort[$sForumId]['marker_read_array'][$sTopicId] = array(
						'i' => $aTopicData['marker_data']['i'],
						'p' => $aTopicData['marker_data']['p']
					);
					$aSort[$sForumId]['marker_read_item'] += $aTopicData['marker_data']['i'];
				}
			}
			foreach ((array)$aMarkForums as $sForumId => $aForumData) {
				if ($sFindId && $sFindId != $sForumId) {
					continue;
				}
				$aSort[$sForumId]['user_id'] = $oUser->getId();
				$aSort[$sForumId]['marker_read_array'] = '*';
				$aSort[$sForumId]['marker_read_item'] = $aForumData['mark_item'];
				$aSort[$sForumId]['marker_date'] = $aForumData['mark_date'];
			}
		}
		return $aSort;
	}

	public function MarkAll() {
		if (!$oUserForum=$this->GetUserById($this->oUserCurrent->getId())) {
			$oUserForum=LS::Ent('PluginForum_Forum_User');
		}
		$oUserForum->setLastMark(date('Y-m-d H:i:s'));
		$oUserForum->Save();
	}

	/**
	 * Маркируем форум как прочитанный
	 *
	 * @param PluginForum_ModuleForum_EntityForum $oForum
	 */
	public function MarkForum(PluginForum_ModuleForum_EntityForum $oForum) {
		if ($oUser = $this->User_GetUserCurrent()) {
			$sUserId = $oUser->getId();
			$sForumId = $oForum->getId();
			/**
			 * Запрашиваем таблицу маркировки
			 */
			$aMarkData = $this->Session_Get("mark{$sUserId}");
			$aMarkData = unserialize(stripslashes($aMarkData));
			$aMarkData['f'][$sForumId]['mark_item'] = $oForum->getCountPost();
			$aMarkData['f'][$sForumId]['mark_date'] = date('Y-m-d H:i:s');
			foreach ((array)$aMarkData['t'] as $sTopicId => $aTopicData) {
				if ($aTopicData['forum_id'] == $oForum->getId()) {
					unset($aMarkData['t'][$sTopicId]);
				}
			}
			/**
			 * Сохраняем
			 */
			$this->Session_Set("mark{$sUserId}", addslashes(serialize($aMarkData)));
		}
	}

	/**
	 * Маркируем тему как прочитанную
	 *
	 * @param PluginForum_ModuleForum_EntityTopic $oTopic
	 */
	public function MarkTopic(PluginForum_ModuleForum_EntityTopic $oTopic,$oLastPost=null) {
		if ($oUser = $this->User_GetUserCurrent()) {
			$sUserId = $oUser->getId();
			$sTopicId = $oTopic->getId();
			$sForumId = $oTopic->getForumId();
			/**
			 * Запрашиваем таблицу маркировки
			 */
			$aMarkData = $this->Session_Get("mark{$sUserId}");
			$aMarkData = unserialize(stripslashes($aMarkData));
			$aMarkTopics = $aMarkData['t'];
			/**
			 * Маркер топика
			 */
			$aMarkTopic = array();
			$bRewrite = false;
			/**
			 * Топик уже был прочитан, сверяем данные
			 */
			if (isset($aMarkTopics[$sTopicId])) {
				$aMarkTopic = $aMarkTopics[$sTopicId]['marker_data'];
				/**
				 * Сменился форум
				 */
				if ($aMarkTopics[$sTopicId]['forum_id'] != $sForumId) {
					$bRewrite = true;
				}
				/**
				 * Последний отмеченный пост не существует
				 */
				if (!$this->GetPostById($aMarkTopic['p'])) {
					$bRewrite = true;
				}
				/**
				 * Последний пост новее или количество сообщений больше
				 */
				if ($oLastPost) {
					if (($oLastPost->getId() > $aMarkTopic['p']) || ($oLastPost->getNumber() > $aMarkTopic['i'])) {
						$bRewrite = true;
					}
				} else {
					if (($oTopic->getLastPostId() > $aMarkTopic['p']) || ($oTopic->getCountPost() > $aMarkTopic['i'])) {
						$bRewrite = true;
					}
				}
				/*
				 * Топик прочитан полностью
				 */
				if ($oTopic->getLastPostId() == $aMarkTopic['p']) {
					/**
					 * Количество сообщений не сходится
					 */
					if ($oTopic->getCountPost() <> $aMarkTopic['i']) {
						$bRewrite = true;
					}
				}
			} else {
				$bRewrite = true;
			}
			/**
			 * Обновляем информацию о маркере
			 */
			if ($bRewrite) {
				if ($oLastPost) {
					$aMarkTopic['i'] = $oLastPost->getNumber();
					$aMarkTopic['p'] = $oLastPost->getId();
				} else {
					$aMarkTopic['i'] = $oTopic->getCountPost();
					$aMarkTopic['p'] = $oTopic->getLastPostId();
				}
				$aMarkTopics[$sTopicId]['forum_id'] = $sForumId;
				$aMarkTopics[$sTopicId]['marker_data'] = $aMarkTopic;
				/**
				 * Сохраняем
				 */
				$aMarkData['t'] = $aMarkTopics;
				$this->Session_Set("mark{$sUserId}", addslashes(serialize($aMarkData)));
			}
		}
	}

	/**
	 * Получить маркер форума по ID
	 *
	 * @param	string	$sForumId
	 * @return	object
	 */
	public function GetMarker($sForumId) {
		if ($oUser = $this->User_GetUserCurrent()) {
			$aData = $this->Session_Get("mark{$oUser->getId()}");
			$aData = unserialize(stripslashes($aData));
			$aData = $this->sortMarker($aData, $sForumId);
			if (isset($aData[$sForumId])) {
				$oMarker = LS::Ent('PluginForum_Forum_Marker', $aData[$sForumId]);
				return $oMarker;
			}
		}
		return null;
	}

	/**
	 * Сохраняем маркер пользователя в БД
	 *
	 */
	public function SaveMarkers() {
		if ($oUser = $this->User_GetUserCurrent()) {
			$aData = $this->Session_Get("mark{$oUser->getId()}");
			$aData = unserialize(stripslashes($aData));
			$aData = $this->sortMarker($aData);
			foreach ((array)$aData as $sForumId => $aForumData) {
				if (is_array($aForumData['marker_read_array'])) {
					$aForumData['marker_read_array'] = addslashes(serialize($aForumData['marker_read_array']));
				}
				if ($oMarker = $this->GetMarkerByUserIdAndForumId($oUser->getId(), $sForumId)) {
					$oMarker->setReadArray($aForumData['marker_read_array']);
					$oMarker->setReadItem($aForumData['marker_read_item']);
					$oMarker->setDate(isset($aForumData['marker_date']) ? $aForumData['marker_date'] : null);
					$oMarker->Update();
				} else {
					$oMarker = LS::Ent('PluginForum_Forum_Marker', $aForumData);
					$oMarker->Add();
				}
			}
			$this->Session_Drop("mark{$oUser->getId()}");
		}
	}

	/**
	 * Загружаем маркер пользователя в БД
	 *
	 */
	public function LoadMarkers() {
		if ($oUser = $this->User_GetUserCurrent()) {
			$aData = array('t'=>array(),'f'=>array());
			$aMarkers = $this->GetMarkerItemsByUserId($oUser->getId());
			foreach ((array)$aMarkers as $oMarker) {
				$aReadArray = $oMarker->getReadArray();
				if ((string)$aReadArray != '*') {
					$aReadArray = unserialize(stripslashes($aReadArray));
				}
				if ((string)$aReadArray == '*') {
					$aData['f'][$oMarker->getForumId()]['mark_item'] = $oMarker->getReadItem();
					$aData['f'][$oMarker->getForumId()]['mark_date'] = $oMarker->getDate();
				} else {
					foreach ($aReadArray as $sTopicId => $aTopicData) {
						$aData['t'][$sTopicId]['forum_id'] = $oMarker->getForumId();
						$aData['t'][$sTopicId]['marker_data'] = $aTopicData;
					}
				}
			}
			$this->Session_Set("mark{$oUser->getId()}", addslashes(serialize($aData)));
		}
	}
}

?>
