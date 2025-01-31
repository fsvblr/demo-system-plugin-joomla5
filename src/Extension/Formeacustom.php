<?php

/**
 * @package     System.Plugin
 * @subpackage  System.formeacustom
 *
 * @copyright   (C) 2024 Belitsoft. <https://belitsoft.com>
 */

namespace Bis\Plugin\System\Formeacustom\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Bis\Plugin\System\Formeacustom\PluginTraits\AddAssets;
use Bis\Plugin\System\Formeacustom\PluginTraits\AdminAddButtonSendEmailToFormeaForm;
use Bis\Plugin\System\Formeacustom\PluginTraits\AdminAddFieldsToFormeaForm;
use Bis\Plugin\System\Formeacustom\PluginTraits\AdminFormeaRedirectSubmissions;
use Bis\Plugin\System\Formeacustom\PluginTraits\AdminRenderFieldsToFormeaForm;
use Bis\Plugin\System\Formeacustom\PluginTraits\CheckFormAccess;
use Bis\Plugin\System\Formeacustom\PluginTraits\CheckFormAccessByComponent;
use Bis\Plugin\System\Formeacustom\PluginTraits\CheckFormAccessByModule;
use Bis\Plugin\System\Formeacustom\PluginTraits\CheckFormAccessByShortCode;
use Bis\Plugin\System\Formeacustom\PluginTraits\DeleteFormDataFromFormeaForm;
use Bis\Plugin\System\Formeacustom\PluginTraits\DeleteUserDataFromFormeaForm;
use Bis\Plugin\System\Formeacustom\PluginTraits\SaveDataFormeaForm;
use Bis\Plugin\System\Formeacustom\PluginTraits\SavePageSubmission;
use Bis\Plugin\System\Formeacustom\PluginTraits\SaveSubmission;
use Bis\Plugin\System\Formeacustom\PluginTraits\SendInvite;
use Bis\Plugin\System\Formeacustom\PluginTraits\UpdateSubmission;
use Bis\Plugin\System\Formeacustom\PluginTraits\UpdateUserDataInFormeaForm;
use Bis\Plugin\System\Formeacustom\PluginTraits\UserToken;
use Joomla\CMS\Event\Application\AfterRenderEvent;
use Joomla\CMS\Event\Application\AfterRouteEvent;
use Joomla\CMS\Event\Application\BeforeRenderEvent;
use Joomla\CMS\Event\Module\RenderModuleEvent;
use Joomla\CMS\Event\Table\AfterDeleteEvent;
use Joomla\CMS\Event\Table\AfterStoreEvent;
use Joomla\CMS\Event\User;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

use Joomla\Component\Formea\Site\Libraries\FormeaCore;

final class Formeacustom extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use AddAssets;
	use AdminAddButtonSendEmailToFormeaForm;
	use AdminAddFieldsToFormeaForm;
	use AdminFormeaRedirectSubmissions;
	use AdminRenderFieldsToFormeaForm;
	use CheckFormAccess;
	use CheckFormAccessByComponent;
	use CheckFormAccessByModule;
	use CheckFormAccessByShortCode;
	use DeleteFormDataFromFormeaForm;
	use DeleteUserDataFromFormeaForm;
	use SaveDataFormeaForm;
	use SavePageSubmission;
	use SaveSubmission;
	use SendInvite;
	use UpdateSubmission;
	use UpdateUserDataInFormeaForm;
	use UserToken;

    /**
     * Autoload the language files
     *
     * @var    boolean
     * @since  4.2.0
     */
    protected $autoloadLanguage = true;

	/**
	 * Additional fields of the "Formea" form.
	 *
	 * @var array
	 */
	protected $formeacustomForm = [];

	private $tokenLength = 32;
	private $tokenalgorithm = 'sha256';

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   4.2.0
     */
    public static function getSubscribedEvents(): array
    {
        try {
            $app = Factory::getApplication();
        } catch (\Exception $e) {
            return [];
        }

        if (!$app->isClient('site') && !$app->isClient('administrator')) {
            return [];
        }

	    if (!file_exists(JPATH_ADMINISTRATOR . '/components/com_formea/src/Extension/FormeaComponent.php')) {
		    return [];
	    }

        return [
			'onAfterRender' => 'onAfterRender',
	        'onAfterRoute' => 'onAfterRoute',
	        'onBeforeRender' => 'onBeforeRender',
	        'onRenderModule' => 'onRenderModule',
            'onTableAfterDelete' => 'onTableAfterDelete',
            'onTableAfterStore' => 'onTableAfterStore',
	        'onUserAfterDelete' => 'onUserAfterDelete',
	        'onUserAfterSave' => 'onUserAfterSave',
        ];
    }

	/**
	 * onAfterRender event
	 *
	 * @param   AfterRenderEvent  $event
	 *
	 * @return void
	 */
	public function onAfterRender(AfterRenderEvent $event)
	{
		$this->AdminAddButtonSendEmailToFormeaForm($event);
		$this->AdminAddFieldsToFormeaForm($event);
		$this->CheckFormAccessByShortCode($event);
		$this->UpdateSubmission($event);
	}

	/**
	 * onAfterRoute event
	 *
	 * @param   AfterRouteEvent  $event
	 *
	 * @return void
	 */
	public function onAfterRoute(AfterRouteEvent $event)
	{
		$this->SaveSubmission($event);
		$this->SendInviteUserCompleteForm($event);
		$this->AdminFormeaRedirectSubmissions($event);
	}

	/**
	 * onBeforeRender event
	 *
	 * @param   BeforeRenderEvent  $event
	 *
	 * @return void
	 */
	public function onBeforeRender(BeforeRenderEvent $event)
	{
		$this->AddAssets($event);
		$this->AdminRenderFieldsToFormeaForm($event);
		$this->SavePageSubmission($event);
		$this->CheckFormAccessByComponent($event);
	}

	/**
	 * onRenderModule event
	 *
	 * @param   RenderModuleEvent  $event
	 *
	 * @return void
	 */
	public function onRenderModule(RenderModuleEvent $event)
	{
		$this->CheckFormAccessByModule($event);
	}

	/**
	 * Post-processor for $table->delete($pk)
	 *
	 * @param   AfterDeleteEvent  $event  The event to handle
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	public function onTableAfterDelete(AfterDeleteEvent $event)
	{
		$this->DeleteFormDataFromFormeaForm($event);
	}

	/**
	 * Post-processor for $table->store($updateNulls)
	 *
	 * @param   AfterStoreEvent  $event  The event to handle
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	 public function onTableAfterStore(AfterStoreEvent $event)
	 {
		 $this->SaveDataFormeaForm($event);
	 }

	/**
	 * On deleting user data logging method
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param   User\AfterDeleteEvent $event  The event instance.
	 *
	 * @return  void
	 *
	 * @since   3.9.0
	 */
	public function onUserAfterDelete(User\AfterDeleteEvent $event)
	{
		$this->DeleteUserDataFromFormeaForm($event);
	}

	/**
	 * On saving user data logging method
	 *
	 * Method is called after user data is stored in the database.
	 * This method logs who created/edited any user's data
	 *
	 * @param   User\AfterSaveEvent $event  The event instance.
	 *
	 * @return  void
	 *
	 * @since   3.9.0
	 */
	public function onUserAfterSave(User\AfterSaveEvent $event)
	{
		$this->UpdateUserDataInFormeaForm($event);
	}
}
