<?php
/*---------------------------------------------------------------------------
* @Module Name: Forum
* @Description: Forum for LiveStreet
* @Version: 1.0
* @Author: Chiffa
* @LiveStreet Version: 1.0
* @File Name: forum.php
* @License: CC BY-NC, http://creativecommons.org/licenses/by-nc/3.0/
*----------------------------------------------------------------------------
*/

/**
 * Строит дерево форумов
 *
 * @param    array $aForums
 * @param    array $aList
 * @param    string $sDepthGuide
 * @param    integer $iLevel
 * @return    array
 */
function forum_create_list($aForums = array(), $aList = array(), $sDepthGuide = "", $iLevel = 0)
{
    if (is_array($aForums) && !empty($aForums)) {
        foreach ($aForums as $oForum) {
            $aList[] = array(
                'id' => $oForum->getId(),
                'title' => $sDepthGuide . $oForum->getTitle(),
                'level' => $iLevel
            );

            if ($aSubForums = $oForum->getChildren()) {
                $aList = forum_create_list($aSubForums, $aList, $sDepthGuide . PluginForum_ModuleForum::DEPTH_GUIDE, $iLevel + 1);
            }
        }
    }
    return $aList;
}

/**
 * Устанавливает роли (первый, последний) в своем дереве
 *
 * @param    array $aForums
 * @return    array
 */
function forums_set_role($aForums)
{
    /**
     * Первый элемент ветки
     */
    $oFirst = current($aForums);
    $oFirst->setFirst(1);
    /**
     * Последний элемент ветки
     */
    $oLast = end($aForums);
    $oLast->setLast(1);
    /**
     * Устанавливаем роли для дочерних элементов
     */
    foreach ($aForums as $oForum) {
        $aChildrens = $oForum->getChildren();
        $bHasChildren = !empty($aChildrens);
        if ($bHasChildren) {
            $aChildrens = forums_set_role($aChildrens);
            $oForum->setChildren($aChildrens);
        }
    }
    return $aForums;
}

/**
 * Проверяет введен ли пароль
 *
 * @param    object $oForum
 * @return    boolean
 */
function forum_compare_password($oForum)
{
    $sCookiePass = fGetCookie("CfFP{$oForum->getId()}");
    return (bool)($sCookiePass == md5($oForum->getPassword()));
}

/**
 * Обработчик заголовков
 *
 * @param    string $sTitle
 * @return    string
 */
function forum_parse_title($sTitle)
{
    if (Config::Get('plugin.forum.title_format')) {
        $sTitle = preg_replace('#(\.|\?|!|\(|\)){3,}#', '\1\1\1', $sTitle);
    }
    //$sTitle = rtrim($sTitle, '(^A-Za-z0-9!?)');
    return strip_tags($sTitle);
}

/**
 * Проверяет права доступа
 *
 * @param    array $aPermissions
 * @param    object $oUser
 * @param    boolean $bGuestDef
 * @return    boolean
 */
function forum_check_perms($aPermissions, $oUser = null, $bGuestDef = false)
{
    $sPermId = is_null($oUser)
        ? PluginForum_ModuleForum::MASK_PERM_GUEST
        : ($oUser->isAdministrator() ? PluginForum_ModuleForum::MASK_PERM_ADMIN : PluginForum_ModuleForum::MASK_PERM_USER);
    if (!is_array($aPermissions)) {
        if (is_null($aPermissions)) {
            return PluginForum_ModuleForum::MASK_PERM_GUEST === $sPermId ? $bGuestDef : true;
        }
        if ((string)$aPermissions === '*') {
            return true;
        }
    }
    $aGroupPermArray = explode(',', (string)$sPermId);
    foreach ($aGroupPermArray as $sUid) {
        if (isset($aPermissions[$sUid])) {
            return true;
        }
    }
    return false;
}

if (!function_exists('fSetCookie')) {
    /**
     * Сохраняет куку
     *
     * @param    string $sName
     * @param    string $sValue
     * @param    boolean $bSticky
     * @param    integer $iExpiresDays
     * @param    integer $iExpiresMinutes
     * @param    integer $iExpiresSeconds
     */
    function fSetCookie($sName = null, $sValue = '', $bSticky = 1, $iExpiresDays = 0, $iExpiresMinutes = 0, $iExpiresSeconds = 0)
    {
        if (!($sName)) return;

        $iExpires = time() + (60 * 60 * 24 * 365);
        if (!$bSticky) {
            $iExpires = time() + ($iExpiresDays * 86400) + ($iExpiresMinutes * 60) + $iExpiresSeconds;
            //	if ($iExpires <= time()) $iExpires = false;
        }
        @setcookie($sName, $sValue, $iExpires, Config::Get('sys.cookie.path'), Config::Get('sys.cookie.host'));
    }
}
if (!function_exists('fGetCookie')) {
    /**
     * Возвращает значение куки
     *
     * @param    string $sName
     * @return    (string|boolean)
     */
    function fGetCookie($sName)
    {
        if (isset($_COOKIE[$sName])) {
            return htmlspecialchars(urldecode(trim($_COOKIE[$sName])));
        }
        return false;
    }
}

/**
 * Логгер
 *
 * @param (string|array)    $info        Список информации
 * @param string $message Заголовок
 */
function forumLogger($info, $title = 'Error')
{
    /**
     * Записываем информацию об ошибке в переменную $msg
     */
    $msg = "Forum message: $title<br>\n";
    $msg .= print_r($info, true);
    /**
     * Пишем ошибку в лог
     */
    $oEngine = Engine::getInstance();
    $sOldName = $oEngine->Logger_GetFileName();
    $oEngine->Logger_SetFileName('forum_log.log');
    $oEngine->Logger_Error($msg);
    $oEngine->Logger_SetFileName($sOldName);
    /**
     * Если стоит вывод ошибок то выводим ошибку на экран(браузер)
     */
    if (error_reporting() && ini_get('display_errors')) {
        exit($msg);
    }
}

/**
 * Совместимость с версиями < 1.0.3
 */
if (!function_exists('getRequestStr')) {
    /**
     * функция доступа к GET POST параметрам, которая значение принудительно приводит к строке
     *
     * @param string $sName
     * @param mixed $default
     * @param string $sType
     *
     * @return string
     */
    function getRequestStr($sName, $default = null, $sType = null)
    {
        return (string)getRequest($sName, $default, $sType);
    }
}
?>