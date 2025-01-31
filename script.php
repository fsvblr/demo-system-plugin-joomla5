<?php

/**
 * @package     System.Plugin
 * @subpackage  System.formeacustom
 *
 * @copyright   (C) 2024 Belitsoft. <https://belitsoft.com>
 */

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

return new class () implements ServiceProviderInterface {
	public function register(Container $container)
	{
		$container->set(
			InstallerScriptInterface::class,
			new class (
				$container->get(AdministratorApplication::class),
				$container->get(DatabaseInterface::class)
			) implements InstallerScriptInterface {
				private AdministratorApplication $app;
				private DatabaseInterface $db;

				public function __construct(AdministratorApplication $app, DatabaseInterface $db)
				{
					$this->app = $app;
					$this->db  = $db;
				}

				public function install(InstallerAdapter $adapter): bool
				{
					//$this->app->enqueueMessage('Successful installed.');

					return true;
				}

				public function update(InstallerAdapter $adapter): bool
				{
					//$this->app->enqueueMessage('Successful updated.');

					return true;
				}

				public function uninstall(InstallerAdapter $adapter): bool
				{
					//$this->app->enqueueMessage('Successful uninstalled.');

					return true;
				}

				public function preflight(string $type, InstallerAdapter $adapter): bool
				{
					return true;
				}

				public function postflight(string $type, InstallerAdapter $adapter): bool
				{
					if($type == 'install') {
						//Publish plugin
						$this->publishExtension();
					}

					return true;
				}

				private function publishExtension()
				{
					$db = $this->db;

					$db->setQuery(
						$db->getQuery(true)
							->update($db->qn('#__extensions'))
							->set($db->qn('enabled') . ' = ' . $db->q('1'))
							->where($db->qn('type') . ' = ' . $db->q('plugin'))
							->where($db->qn('folder') . ' = ' . $db->q('system'))
							->where($db->qn('element') . ' = ' . $db->q('formeacustom'))
					);

					try {
						$db->execute();
					} catch (\RuntimeException $e) {
						$this->app->enqueueMessage($e->getMessage(), 'error');
					}
				}
			}
		);
	}
};
