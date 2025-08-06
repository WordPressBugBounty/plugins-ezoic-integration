<?php

namespace Ezoic_Namespace;

/**
 * Class Ezoic_AdsTxtManager_Htaccess_Modifier
 * @package Ezoic_Namespace
 */
class Ezoic_AdstxtManager_Htaccess_Modifier implements iAdsTxtManager_Solution
{

	public function SetupSolution()
	{
		$this->GenerateHTACCESSFile();

		$fileModifier = new Ezoic_AdsTxtManager_File_Modifier();
		$fileModifier->SetupSolution();

		$redirect_result = Ezoic_AdsTxtManager::ezoic_verify_adstxt_redirect();
		update_option('ezoic_adstxtmanager_status', $redirect_result);
	}

	public function TearDownSolution()
	{
		$this->RemoveHTACCESSFile();

		$fileModifier = new Ezoic_AdsTxtManager_File_Modifier();
		$fileModifier->TearDownSolution();

		if (get_option('ezoic_adstxtmanager_status') !== false) {
			delete_option('ezoic_adstxtmanager_status');
		}
	}

	private function determineHTACCESSRootPath()
	{
		return Ezoic_Integration_Path_Sanitizer::get_home_path();
	}

	public function GenerateHTACCESSFile()
	{
		global $wp, $wp_filesystem;
		$message = '';
		$rootPath = $this->determineHTACCESSRootPath();
		
		if ($rootPath === false) {
			$message = "Cannot determine website root path for .htaccess file.";
			$adstxtmanager_status = Ezoic_AdsTxtManager::ezoic_adstxtmanager_status(true);
			$adstxtmanager_status['message'] = $message;
			update_option('ezoic_adstxtmanager_status', $adstxtmanager_status);
			return;
		}
		
		$filePath = $rootPath . ".htaccess";
		if (empty($filePath) || !$wp_filesystem->exists($filePath) || !$wp_filesystem->is_readable($filePath) || !$wp_filesystem->is_writable($filePath)) {
			$message = "Cannot access your .htaccess file. Please check that the file exists and has proper write permissions.";
			$adstxtmanager_status = Ezoic_AdsTxtManager::ezoic_adstxtmanager_status(true);
			$adstxtmanager_status['message'] = $message;
			update_option('ezoic_adstxtmanager_status', $adstxtmanager_status);
			return;
		}

		self::RemoveHTACCESSFile();

		$adstxtmanager_id = Ezoic_AdsTxtManager::ezoic_adstxtmanager_id(true);

		if (empty($adstxtmanager_id) || !is_int($adstxtmanager_id) || $adstxtmanager_id <= 0) {
			return;
		}

		// Sanitize domain extraction
		$domain = home_url($wp->request);
		$parsed_url = parse_url($domain);
		if (!$parsed_url || !isset($parsed_url['host'])) {
			$message = "Cannot determine domain for ads.txt redirect.";
			$adstxtmanager_status = Ezoic_AdsTxtManager::ezoic_adstxtmanager_status(true);
			$adstxtmanager_status['message'] = $message;
			update_option('ezoic_adstxtmanager_status', $adstxtmanager_status);
			return;
		}
		$domain = Ezoic_Integration_Path_Sanitizer::sanitize_domain($parsed_url['host']);
		if ($domain === false) {
			$message = "Invalid domain for ads.txt redirect.";
			$adstxtmanager_status = Ezoic_AdsTxtManager::ezoic_adstxtmanager_status(true);
			$adstxtmanager_status['message'] = $message;
			update_option('ezoic_adstxtmanager_status', $adstxtmanager_status);
			return;
		}

		$content = $wp_filesystem->get_contents($filePath);

		// Create safe redirect URL
		$redirect_url = Ezoic_Integration_Path_Sanitizer::create_adstxt_redirect_url($adstxtmanager_id, $domain);
		if ($redirect_url === false) {
			$message = "Cannot create valid ads.txt redirect URL.";
			$adstxtmanager_status = Ezoic_AdsTxtManager::ezoic_adstxtmanager_status(true);
			$adstxtmanager_status['message'] = $message;
			update_option('ezoic_adstxtmanager_status', $adstxtmanager_status);
			return;
		}
		
		$atmContent = array(
			"#BEGIN_ADSTXTMANAGER_HTACCESS_HANDLER",
			'<IfModule mod_rewrite.c>',
			'Redirect 301 /ads.txt ' . $redirect_url,
			'</IfModule>',
			"#END_ADSTXTMANAGER_HTACCESS_HANDLER"
		);

		$atmFinalContent = implode("\n", $atmContent);
		$modifiedContent = $atmFinalContent . "\n" . $content;

		$success = $wp_filesystem->put_contents($filePath, $modifiedContent);
		@clearstatcache();

		if (!$success) {
			$message = "Unable to update your .htaccess file for ads.txt redirect. Please check file permissions or contact your hosting provider.";
		}

		if (!empty($message)) {
			$adstxtmanager_status = Ezoic_AdsTxtManager::ezoic_adstxtmanager_status(true);
			$adstxtmanager_status['message'] = $message;
			update_option('ezoic_adstxtmanager_status', $adstxtmanager_status);
		}
	}

	public function RemoveHTACCESSFile()
	{
		global $wp_filesystem;
		$rootPath = $this->determineHTACCESSRootPath();
		
		if ($rootPath === false) {
			return;
		}
		
		$filePath = $rootPath . ".htaccess";

		if (empty($filePath) || !$wp_filesystem->exists($filePath) || !$wp_filesystem->is_writable($filePath)) {
			return;
		}

		$content = $wp_filesystem->get_contents($filePath);
		if ($content === false) {
			return;
		}

		$lineContent = preg_split("/\r\n|\n|\r/", $content);
		$beginAtmContent = 0;
		$endAtmContent = 0;
		foreach ($lineContent as $key => $value) {
			if ($value == "#BEGIN_ADSTXTMANAGER_HTACCESS_HANDLER") {
				$beginAtmContent = $key;
			} elseif ($value == "#END_ADSTXTMANAGER_HTACCESS_HANDLER") {
				$endAtmContent = $key;
			}
		}

		if ($endAtmContent == 0) {
			return;
		}

		for ($i = $beginAtmContent; $i <= $endAtmContent; $i++) {
			unset($lineContent[$i]);
		}

		$modifiedContent = implode("\n", $lineContent);
		$wp_filesystem->put_contents($filePath, $modifiedContent);
	}
}
