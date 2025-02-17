<?php
/**
 * @package     YouTubeGallery
 * @subpackage  YouTubeGallery Content Plugin
 * @author      Ivan Komlev <support@joomlaboat.com>
 * @copyright   (C) 2025 Ivan Komlev. <https://www.joomlaboat.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 **/

namespace YoutubeGallery\Plugin\Content\YoutubeGallery\Extension;

defined('_JEXEC') or die();

use CustomTables\Environment;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseInterface;
use YouTubeGallery\Helper;
use YouTubeGalleryDB;
use YouTubeGalleryRenderer;

final class YoutubeGallery extends CMSPlugin
{
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        $count = 0;
        $count += self::plgYoutubeGallery($article->text, true);
        $count += self::plgYoutubeGallery($article->text, false);
    }

    /**
     * @throws \Exception
     */
    public static function plgYoutubeGallery(&$text_original, $byId): int
    {
        $path = JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_youtubegallery' . DIRECTORY_SEPARATOR . 'libraries' . DIRECTORY_SEPARATOR . 'youtubegallery' . DIRECTORY_SEPARATOR;
        require_once($path . 'loader.php');
        YGLoadClasses();

        $text = self::strip_html_tags_textarea($text_original);

        $options = array();
        if ($byId)
            $fList = self::getListToReplace('youtubegalleryid', $options, $text);
        else
            $fList = self::getListToReplace('youtubegallery', $options, $text);

        if (count($fList) == 0)
            return 0;

        for ($i = 0; $i < count($fList); $i++) {
            $replaceWith = self::getYoutubeGallery($options[$i], $i, $byId);
            $text_original = str_replace($fList[$i], $replaceWith, $text_original);
        }

        return count($fList);
    }

    public static function strip_html_tags_textarea($text): string
    {
        return preg_replace(
            array(
                // Remove invisible content
                '@<textarea[^>]*?>.*?</textarea>@siu',
            ),
            array(
                ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', "$0", "$0", "$0", "$0", "$0", "$0", "$0", "$0",), $text);
    }

    public static function getListToReplace($par, &$options, $text): array
    {
        $temp_text = preg_replace("/<textarea\b[^>]*>(.*?)<\/textarea>/i", "", $text);

        $fList = array();
        $l = strlen($par) + 2;

        $offset = 0;
        do {
            if ($offset >= strlen($temp_text))
                break;

            $ps = strpos($text, '{' . $par . '=', $offset);
            if ($ps === false)
                break;

            if ($ps + $l >= strlen($temp_text))
                break;

            $pe = strpos($text, '}', $ps + $l);

            if ($pe === false)
                break;

            $notestr = substr($temp_text, $ps, $pe - $ps + 1);
            $fList[] = $notestr;

            $opt_string = substr($temp_text, $ps + $l, $pe - $ps - $l);
            $options[] = Helper::html2txt($opt_string);

            $offset = $ps + $l;

        } while (!($pe === false));

        return $fList;
    }

    /**
     * @throws Exception
     */
    public static function getYoutubeGallery($galleryparams, $count, $byId)
    {
        $result = '';

        $opt = explode(',', $galleryparams);
        if (count($opt) < 2) {
            Factory::getApplication()->enqueueMessage(Text::_('PLG_CONTENT_YOUTUBEGALLERY_ERROR_THEME_NOT_SET'), 'error');
            return '';
        }

        $version_object = new Version;
        $version = (int)$version_object->getShortVersion();

        if ($version < 4)
            $db =  Factory::getDbo();
        else
            $db = Factory::getContainer()->get(DatabaseInterface::class);

        if ($byId) {
            $listid = (int)$opt[0];
            if (isset($opt[3]) and (int)$opt[3] != 0) {
                $isMobile = Environment::check_user_agent('mobile');
                if ($isMobile)
                    $themeid = (int)$opt[3];
                else
                    $themeid = (int)$opt[1];
            } else
                $themeid = (int)$opt[1];

            $query_list = 'SELECT * FROM #__customtables_table_youtubegalleryvideolists WHERE id=' . $listid . ' LIMIT 1';
            $query_theme = 'SELECT * FROM #__customtables_table_youtubegallerythemes WHERE id=' . $themeid . ' LIMIT 1';
        } else {
            $listname = trim($opt[0]);
            if (isset($opt[3]) and trim($opt[3]) != '') {
                $isMobile = Environment::check_user_agent('mobile');
                if ($isMobile)
                    $themename = trim($opt[3]);
                else
                    $themename = trim($opt[1]);
            } else
                $themename = trim($opt[1]);

            $query_list = 'SELECT * FROM #__customtables_table_youtubegalleryvideolists WHERE es_listname="' . $listname . '" LIMIT 1';
            $query_theme = 'SELECT * FROM #__customtables_table_youtubegallerythemes WHERE es_themename="' . $themename . '" LIMIT 1';
        }

        //Video List data
        $db->setQuery($query_list);

        $videoListRows = $db->loadObjectList();
        if (count($videoListRows) == 0) {
            Factory::getApplication()->enqueueMessage(Text::_('PLG_CONTENT_YOUTUBEGALLERY_ERROR_VIDEOLIST_NOT_SET'), 'error');
            return '';
        }
        $videoListRow = $videoListRows[0];

        //Theme data
        $db->setQuery($query_theme);

        $theme_rows = $db->loadObjectList();
        if (count($theme_rows) == 0) {
            Factory::getApplication()->enqueueMessage(Text::_('PLG_CONTENT_YOUTUBEGALLERY_ERROR_THEME_NOT_SET'), 'error');
            return '';
        }

        $theme_row = $theme_rows[0];
        $custom_itemid = 0;
        if (isset($opt[2]))
            $custom_itemid = (int)$opt[2];

        $ygDB = new YouTubeGalleryDB;
        $ygDB->videoListRow = $videoListRow;
        $ygDB->theme_row = $theme_row;
        $total_number_of_rows = 0;
        $ygDB->update_playlist();

        $videoid = Factory::getApplication()->input->getCmd('videoid', '');
        if (!isset($videoid) or $videoid == '') {
            $video = Factory::getApplication()->input->getString('video', '');
            $video = preg_replace('/[^a-zA-Z0-9-_]+/', '', $video);

            if ($video != '') {
                $videoid = YouTubeGalleryDB::getVideoIDbyAlias($video);
                Factory::getApplication()->input->set('videoid', $videoid);
            }
        }

        if ($theme_row->es_playvideo == 1 and $videoid != '')
            $theme_row->es_autoplay = 1;

        $videoid_new = $videoid;
        $jinput = Factory::getApplication()->input;
        $videolist = $ygDB->getVideoList_FromCache_From_Table($videoid_new, $total_number_of_rows);

        if ($jinput->getInt('yg_api') == 1) {
            $result = json_encode($videolist);

            if (ob_get_contents())
                ob_end_clean();

            header('Content-Disposition: attachment; filename="youtubegallery_api.json"');
            header('Content-Type: application/json; charset=utf-8');
            header("Pragma: no-cache");
            header("Expires: 0");

            echo $result;
            die;
        }

        if ($videoid == '') {
            if ($theme_row->es_playvideo == 1 and $videoid_new != '')
                $videoid = $videoid_new;
        }

        $renderer = new YouTubeGalleryRenderer;

        $result .= $renderer->render(
            $videolist,
            $videoListRow,
            $theme_row,
            $total_number_of_rows,
            $videoid,
            $custom_itemid
        );

        return $result;
    }
}