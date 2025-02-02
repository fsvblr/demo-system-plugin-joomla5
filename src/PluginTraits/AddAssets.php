<?php

/**
 * @package     System.Plugin
 * @subpackage  System.formeacustom
 *
 * @copyright   (C) 2024 Belitsoft. <https://belitsoft.com>
 */

namespace Bis\Plugin\System\Formeacustom\PluginTraits;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Application\BeforeRenderEvent;
use Joomla\CMS\Language\Text;

/**
 * Loading styles and scripts.
 *
 * @since   4.0.0
 */
trait AddAssets
{
    /**
     * Injects CSS and Javascript
     *
     * @param   BeforeRenderEvent  $event
     *
     * @return void
     */
    public function AddAssets(BeforeRenderEvent $event)
    {
        $app = $this->getApplication();

        if (!($app instanceof CMSApplication)) {
            return;
        }

        if(!$app->isClient('administrator')
            //&& !$app->isClient('site')
        ) {
            return;
        }

        try {
            $document = $app->getDocument();
        } catch (Exception $e) {
            $document = null;
        }

        if (!($document instanceof HtmlDocument)) {
            return;
        }

        $option = $app->getInput()->get('option');
        $view = $app->getInput()->get('view');
        $layout = $app->getInput()->get('layout');
        $id = $app->getInput()->getInt('id');

        /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addRegistryFile('media/plg_system_formeacustom/joomla.asset.json');

        if($app->isClient('administrator')) {
            if (!$wa->isAssetActive('style', 'plg_system_formeacustom.admin.formeacustom')) {
                $wa->useStyle('plg_system_formeacustom.admin.formeacustom');
            }
            if (!$wa->isAssetActive('script', 'plg_system_formeacustom.admin.formeacustom')) {
                $wa->useScript('plg_system_formeacustom.admin.formeacustom');
            }

            if ($option == 'com_formea' && $view == 'formea' && $layout == 'edit' && !empty($id)) {
                $document->addScriptOptions(
                    'plg_system_formeacustom.dialog.invite.popupContent',
                    array('value' => $this->renderInviteForm($id))
                );

                Text::script('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_HEADER_TEXT');
                Text::script('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_BTN_INVITE');
                Text::script('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_BTN_CLOSE');
                Text::script('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_WARNING_SELECT_USER');
                Text::script('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_TYPE_MESSAGE_WARNING');

                if (!$wa->isAssetActive('script', 'plg_system_formeacustom.admin.dialog.invite')) {
                    $wa->useScript('plg_system_formeacustom.admin.dialog.invite');
                }
            }
        }

        $event->setArgument('result', true);
    }
}
