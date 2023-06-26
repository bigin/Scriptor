<?php

namespace Scriptor\Core\Modules;

require_once __DIR__.('/vendor/autoload.php');

use Imanager\Util;
use Scriptor\Core\Helper;
use Scriptor\Core\Module;
use Scriptor\Core\Scriptor;
use Brick\VarExporter\VarExporter;
use Brick\VarExporter\ExportException;
use Imanager\TemplateParser;

/**
 * This class handles the installation, uninstallation, and configuration of modules in Scriptor.
 * It provides methods for installing, uninstalling, and configuring modules, as well as retrieving a list of installed modules.
 * 
 * @package Scriptor
 */
class Install extends Module
{

	/**
	 * Default configuration for the module manager.
	 * @var array
	 */
	private static array $defaultConfig = [
		'modules' => [],
		'hooks'   => []
	];

	/**
	 * Keys that are common to all module entries.
	 * @var array
	 */
	private static array $genericEntryKeys = [
		'name',
		'position',
		'menu',
		'display_type',
		'icon',
		'active',
		'auth',
		'autoinit',
		'version',
		'description',
		'author',
		'author_website',
		'author_email_address'
	];

	/**
	 * Target modules for the module manager.
	 * @var array
	 */
	private $targetModules = [];

	/**
	 * Template parser for the module manager.
	 * @var object
	 */
	private $templateParser;

	/**
	 * Custom configuration path for the module manager.
	 * @var string
	 */
	private $customConfigPath;

	/**
 	 * Initializes the module by setting up necessary dependencies and configurations.
 	 */
	public function init()
	{
		parent::init();
		$this->csrf = Scriptor::getCSRF();
		$this->templateParser = new TemplateParser();
		$this->customConfigPath = IM_DATAPATH . 'settings/custom.scriptor-config.php';
		if (Scriptor::execHook($this) && $this->event->replace) return;
	}

	/**
 	 * Executes the default action for the module.
 	 */
	public function execute()
	{
		$this->checkAction();

		// Profile editor section
		if($this->segments->get(0) == 'install' && !$this->segments->get(1)) {
			$this->pageTitle = 'Module Installation - Scriptor';
			$this->pageContent = $this->renderModuleInstallationList();
			$this->breadcrumbs .= '<li><span>'.$this->i18n['install_menu'].'</span></li>';
		}
	}

	/**
 	 * Checks user actions and performs corresponding operations.
 	 */
	public function ___checkAction()
	{
		// Just redirect to profile view
		if ($this->segments->get(0) == 'install' && $this->segments->get(1) && $this->input->get->action == 'install') {
			$this->installation();
			Util::redirect('./');
		}
		elseif ($this->segments->get(0) == 'install' && $this->segments->get(1) && $this->input->get->action == 'uninstall') {
			$this->uninstallation();
			Util::redirect('./');
		}
	}

	/**
	 * This function renders a list of modules available for installation.
	 * It retrieves the module list, generates HTML rows for each module,
	 * and returns the rendered HTML output.
	 * 
	 * @return string The rendered HTML output for the module installation list.
	 */
	public function ___renderModuleInstallationList()
	{
		$modules = $this->getModuleList();
		$config = $this->getCustomConfig();

		$rows = '';
		if (!empty($modules)) {
			$token = $this->csrf->renderUrl('&');
			foreach ($modules as $data) {
				if (empty($data['name'])) continue;

				$class = !empty($config['modules'][$data['name']]) ? ' class="active"' : ' class="inactive"';

				$rows .= '<tr>
					<td'.$class.'>'.$data['name'].' ('.$data['version'].')</td>
					<td'.$class.'>'.$data['description'].'</td>
					<td'.$class.'>'.
						(empty($config['modules'][$data['name']]) ? '<a href="'.$this->siteUrl.'/install/'.$data['name'].
						'?action=install'.$token.'" class="button-badge"><i class="gg-import"></i> '.$this->i18n['install_button'].'</a>' : '<a href="'.
						$this->siteUrl.'/install/'.$data['name'].'?action=uninstall'.$token.'" class="button-badge"><i class="gg-export"></i> '.
						$this->i18n['uninstall_button'].'</a>').'
					</td>
				</tr>';
			}
		}
		ob_start(); ?>
		<h1><?php echo $this->i18n['install_module_list_header']; ?></h1>
		<p>
			<?php if (!empty($modules)) {
				echo $this->i18n['install_info_text'];
			} else {
				echo $this->i18n['install_no_modules_found'];
			}
			?>
		</p>
		<table class="item-table">
			<thead>
				<tr>
					<th><b><?php echo $this->i18n['install_table_column_name']; ?></b></th>
					<th><b><?php echo $this->i18n['install_table_column_description']; ?></b></th>
					<th class="text-center"><b><?php echo $this->i18n['install_table_column_action']; ?></b></th>
				</tr>
			</thead>
			<tbody>
				<?php echo $rows ?>
			</tbody>
		</table>
		<?php return ob_get_clean();
	}

	/**
	 * Performs the installation process for a module.
	 * It retrieves the module name, checks if it exists in the module list,
	 * prepares the module entry for configuration, loads the custom configuration file if present,
	 * adds module hooks to the configuration, creates a backup of the configuration file,
	 * updates the configuration with the module entry, and writes the updated configuration file.
	 * Finally, it generates success messages and returns true if the installation is successful,
	 * or generates an error message and returns false if the module is not found.
	 *
	 * @return bool True if the module installation is successful, false otherwise.
	 */
	private function installation(): bool
	{
		if ($this->config['protectCSRF'] && !$this->csrf->isTokenValid(
			$this->input->get->tokenName,
			$this->input->get->tokenValue, true)) {
				$this->addMsg('error', $this->i18n['error_csrf_token_mismatch']);
			return false;
		}

		$moduleName = $this->segments->get(1);
		$moduleList = $this->getModuleList();

		foreach ($moduleList as $module) {
			if ($module['name'] !== $moduleName) continue;

			$moduleEntry = $this->prepareConfigEntry($module);
			$config = $this->getCustomConfig();

			foreach ($this->targetModules as $moduleKey => $targetModule) {
				if ($moduleKey === $moduleName) {
					$hookEntries = $targetModule::moduleHooks();

					foreach ($hookEntries as $key => $entry) {
						foreach ($entry as $hookMethod) {
							$hookMethodExists = false;
							
							if (!empty($config['hooks'][$key])) {
								foreach ($config['hooks'][$key] as $existingHookMethod) {
									if ($this->consistsOfTheSameValues([$existingHookMethod], [$hookMethod])) {
										$hookMethodExists = true;
										break;
									}
								}
							}

							if (!$hookMethodExists) {
								$config['hooks'][$key][] = $hookMethod;
							}
						}
					}
				}
			}

			$backupCreated = $this->createConfigFileBackup($this->customConfigPath);
			$config['modules'][$moduleName] = $moduleEntry[$moduleName];
			$this->writeConfigFile($config);

			$this->addMsg('success', $this->templateParser->render($this->i18n['install_backup_message'], [
					'module_name' => $moduleName,
					'custom_config_path' => $this->customConfigPath
				])
			);
		
			if ($backupCreated) {
				$backupDir = IM_BACKUPPATH . 'configs/';
				$this->addMsg('success', $this->templateParser->render($this->i18n['install_backup_copy_message'], [
						'backup_dir' => $backupDir,
						'custom_config_path' => $this->customConfigPath
					])
				);
			}

			include_once IM_ROOTPATH.'site/'.$moduleEntry[$moduleName]['path'].'.php';
			$loadedModule = new $config['modules'][$moduleName]['class']();
			$modLang = IM_ROOTPATH.'site/'.dirname($moduleEntry[$moduleName]['path']).'/lang/'.$this->config['lang'].'.php';
			if (file_exists($modLang)) {
				Scriptor::setProperty('i18n', array_merge(Scriptor::getProperty('i18n'), include $modLang));
			}
			if ($loadedModule && $loadedModule->install()) {
				return true;
			}

			$this->addMsg('error', $this->templateParser->render(
					$this->i18n['install_module_error'], [
						'module_name' => $moduleName
					]
				)
			);
			return false;
		}

		$this->addMsg('error', $this->templateParser->render($this->i18n['install_module_name_not_found']));

		return false;
	}

	/**
	 * Performs the uninstallation process for a module.
	 *
	 * @return bool Indicates whether the uninstallation was successful or not.
	 */
	private function uninstallation(): bool
	{
		if ($this->config['protectCSRF'] && !$this->csrf->isTokenValid(
			$this->input->get->tokenName,
			$this->input->get->tokenValue, true)) {
				$this->addMsg('error', $this->i18n['error_csrf_token_mismatch']);
			return false;
		}

		$moduleName = $this->segments->get(1);
		$moduleList = $this->getModuleList();

		foreach ($moduleList as $module) {
			if ($module['name'] === $moduleName) {
				//$moduleEntry = $this->prepareConfigEntry($module);
				$config = $this->getCustomConfig();
				// Uninstall the module
				$loadedModule = $this->loadModule($module['name'], ['autoinit' => false]);
				if (!$loadedModule || !$loadedModule->uninstall()) {
					$this->addMsg('error', $this->templateParser->render(
						$this->i18n['uninstall_module_error'], [
							'module_name' => $module['name']
						]
					));
					return false;
				}

				$backupCreated = $this->createConfigFileBackup($this->customConfigPath);
				if ($backupCreated) {
					$backupDir = IM_BACKUPPATH . 'configs/';
					$this->addMsg('success', $this->templateParser->render($this->i18n['install_backup_copy_message'], [
							'backup_dir' => $backupDir,
							'custom_config_path' => $this->customConfigPath
						])
					);
				}

				unset($config['modules'][$moduleName]);

				// Remove the hooks associated with the module
				foreach ($this->targetModules as $moduleKey => $targetModule) {
					if ($moduleKey !== $moduleName) continue;

					$hookEntries = $targetModule::moduleHooks();

					foreach ($hookEntries as $key => $entry) {
						foreach ($entry as $hookMethod) {
							if (!empty($config['hooks'][$key])) {
								foreach ($config['hooks'][$key] as $index => $existingHookMethod) {
									if ($this->consistsOfTheSameValues([$existingHookMethod], [$hookMethod])) {
										unset($config['hooks'][$key][$index]);
										// check if the hook name is empty and then remove it completely.
										if (empty($config['hooks'][$key])) {
											unset($config['hooks'][$key]);
										}
									}
								}
							}
						}
					}
				}

				$this->writeConfigFile($config);

				$this->addMsg('success', $this->templateParser->render(
					$this->i18n['uninstall_module_successful'], [
						'module_name' => $module['name']
					]
				));
				return true;
			}
		}

		$this->addMsg('error', $this->templateParser->render($this->i18n['install_module_name_not_found']));
		return false;
	}


	/**
	 * Retrieves a list of modules installed in the system.
	 * 
	 * @return array An array of module information, including 
	 * the path, file name, namespace, and install and uninstall status.
	 */
	private function getModuleList()
	{
		$moduleList = [];
		$base = dirname(__DIR__, 3);
		$modulesPath = $base . "/site/modules/*";

		foreach (glob($modulesPath) as $path) {
			$file = basename($path);
			$moduleFilePath = "$path/$file.php";

			if (file_exists($moduleFilePath)) {
				$ns = Helper::extractNamespace($moduleFilePath);
				require_once($moduleFilePath);

				if (class_exists("$ns\\$file")) {
					$moduleInfo = array_merge(Module::moduleInfo(), "$ns\\$file"::moduleInfo());

					$moduleEntry = [
						//'full_path' => $path,
						'name' => $moduleInfo['name'],
						'file_name' => "$file.php",
						'path' => $this->extractRelativeModulePath("$path/$moduleInfo[name]"),
						'namespace' => $ns,
						'class' => (!empty($ns) ? $ns.'\\' : '').$moduleInfo['name'],
						'position' => $moduleInfo['position'],
						'menu' => $moduleInfo['menu'],
						'display_type' => $moduleInfo['display_type'],
						'icon' => $moduleInfo['icon'],
						'active' => $moduleInfo['active'],
						'auth' => $moduleInfo['auth'],
						'autoinit' => $moduleInfo['autoinit'],
						'version' => $moduleInfo['version'],
						'description' => $moduleInfo['description'],
						'author' => $moduleInfo['author'],
						'author_website' => $moduleInfo['author_website'],
						'author_email_address' => $moduleInfo['author_email_address']
					];

					$this->targetModules[$moduleInfo['name']] = "$ns\\$file";
					$moduleList[] = $moduleEntry + array_diff_key($moduleInfo, array_flip(self::$genericEntryKeys));
					//$moduleList[] = $moduleEntry;
				}
			}
		}

		usort($moduleList, function($a, $b) {
			$positionA = (int) $a['position'];
			$positionB = (int) $b['position'];
			if ($positionA === $positionB) {
				return 0;
			}
			return ($positionA < $positionB) ? -1 : 1;
		});

		return $moduleList;
	}

	/**
	 * Prepares a configuration entry for the custom.scriptor-config.php file.
	 *
	 * @param array $entry The configuration entry to prepare.
	 *
	 * @return array The prepared configuration entry.
	 */
	private function prepareConfigEntry(array $entry) :array
	{
		return [$entry['name'] => $entry];
	}

	/**
	 * This function checks if two arrays, $a and $b, consist of the same values.
	 * It iterates over the elements of $b and compares them with the corresponding elements in $a.
	 * If any value in $b is not found in $a or if the occurrence of a value differs between the two arrays,
	 * the function returns false. Otherwise, it returns true.
	 * 
	 * @param array $a The first array to compare.
	 * @param array $b The second array to compare.
	 * 
	 * @return bool True if the arrays consist of the same values, false otherwise.
	 */
	public function consistsOfTheSameValues(array $a, array $b) : bool
	{
		foreach ($b as $bValue) {
			if (isset($bValue['method']) && is_callable($bValue['method'])) {
				$aCallableValues = [
					'modules' => [],
					'methods' => [],
				];
				foreach ($a as $aValue) {
					if (isset($aValue['method']) && is_callable($aValue['method'])) {
						$aCallableValues['modules'][] = $aValue['module'];
						$aCallableValues['methods'][] = $this->closureToStr($aValue['method']);
					}
				}
				if (
					isset($bValue['module'])
					&& !in_array($bValue['module'], $aCallableValues['modules'], true)
					|| !in_array($this->closureToStr($bValue['method']), $aCallableValues['methods'], true)
				) {
					return false;
				}
			} else {
				if (!in_array($bValue, $a, true)) {
					return false;
				}
			}
		}

		return true;
	}

	public function closureToStr($func)
	{
		$refl = new \ReflectionFunction($func); // get reflection object
		$path = $refl->getFileName();  // absolute path of php file
		$begn = $refl->getStartLine(); // have to `-1` for array index
		$endn = $refl->getEndLine();
		$dlim = PHP_EOL;
		$list = explode($dlim, file_get_contents($path));         // lines of php-file source
		$list = array_slice($list, ($begn-1), ($endn-($begn-1))); // lines of closure definition
		$last = (count($list)-1); // last line number

		if((substr_count($list[0],'function')>1)|| (substr_count($list[0],'{')>1) || (substr_count($list[$last],'}')>1))
		{ throw new \Exception("Too complex context definition in: `$path`. Check lines: $begn & $endn."); }

		$list[0] = ('function'.explode('function',$list[0])[1]);
		$list[$last] = (explode('}',$list[$last])[0].'}');

		return preg_replace('/\s+/', '', implode($dlim, $list));
	}

	/**
	 * Extracts the relative module path from a given path.
	 *
	 * @param string $path The path to extract the relative module path from.
	 *
	 * @return string|false The extracted relative module path, or false if not found.
	 */
	private function extractRelativeModulePath(string $path) : string|false
	{
		return strstr($path, 'modules/');
	}

	/**
	 * Writes the configuration array to the custom.scriptor-config.php file.
	 *
	 * @param array $config The configuration array to write.
	 *
	 * @return bool|int The number of bytes written to the file on success, false on failure.
	 */
	private function writeConfigFile(array $config)
	{
		try {
			return file_put_contents(
				IM_DATAPATH.'settings/custom.scriptor-config.php', 
				"<?php defined('IS_IM') or die('You cannot access this page directly');". 
				$this->getConfigFileComment()." return ".VarExporter::export($config).';'
			);
		} catch (ExportException $e) {
			// Type "resource" is not supported.
			Util::preformat($e);
			return false;
		}
	}

	/**
	 * Creates a backup of a configuration file.
	 *
	 * @param string $configFile The path to the configuration file.
	 *
	 * @return bool True if the backup was created successfully, false otherwise.
	 */
	public static function createConfigFileBackup(string $configFile)
	{
		$maxFiles = Scriptor::getConfig()['maxConfigBackupFiles'] ?? 0;

		if ($maxFiles == 0) return;

		$dirname = IM_BACKUPPATH.'configs';
		if (!file_exists($dirname) || !is_dir($dirname)) {
			mkdir ("$dirname/", 0755, true);
		}

		$files = glob("$dirname/*_*.backup");
		$numFiles = count($files);
		if ($numFiles >= $maxFiles) {
			usort($files, function ($a, $b) {
				return filemtime($a) - filemtime($b);
			});
	
			$filesToDelete = array_slice($files, 0, $numFiles - ($maxFiles-1));
	
			foreach ($filesToDelete as $file) {
				@unlink($file);
			}
		}

		return copy($configFile, $dirname .'/'. time() .'_custom.scriptor-config.php.backup');
	}

	/**
	 * This function is a private method that returns a string containing a comment about 
	 * the configuration file for Scriptor.
	 * 
	 * @return string
	 */
	private function getConfigFileComment() : string
	{
		return "\n/* This file contains your individual Scriptor settings.

//////////////////////////////////////////////////////////////////////
– NOTE: Please avoid overwriting this file during Scriptor upgrades! –
//////////////////////////////////////////////////////////////////////

You can modify the options in this file and add any additional
settings as required. The default configuration parameters are located
in the scriptor-config.php file.
*/\n";
	}

	/**
	 * The function returns the $config array, which contains the default configuration values, 
	 * updated with any custom values from the custom configuration file.
	 * 
	 * @return array
	 */
	private function getCustomConfig() : array
	{
		$config = self::$defaultConfig;
		if (file_exists($this->customConfigPath)) {
			$config = include $this->customConfigPath;
		}
		return $config;
	}

}
