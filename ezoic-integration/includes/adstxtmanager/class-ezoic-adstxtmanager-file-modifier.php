<?php

namespace Ezoic_Namespace;

/**
 * Class Ezoic_AdsTxtManager_File_Modifier
 * @package Ezoic_Namespace
 */
class Ezoic_AdsTxtManager_File_Modifier implements iAdsTxtManager_Solution
{

	private function determineRootPath()
	{
		return Ezoic_Integration_Path_Sanitizer::get_home_path();
	}

	private function modifiedAdsTxtFileName()
	{
		$rootPath = $this->determineRootPath();
		return Ezoic_Integration_Path_Sanitizer::construct_file_path($rootPath, "ads-txt-orig.txt");
	}

	private function origAdsTxtFileName()
	{
		$rootPath = $this->determineRootPath();
		return Ezoic_Integration_Path_Sanitizer::construct_file_path($rootPath, "ads.txt");
	}

	public function SetupSolution()
	{
		global $wp_filesystem;
		$filePath = $this->origAdsTxtFileName();
		$newFilePath = $this->modifiedAdsTxtFileName();
		$message = null;

		// Check if paths could be determined
		if ($filePath === false || $newFilePath === false) {
			$redirect_result = array('status' => false, 'error' => 'path_error', 'message' => 'Cannot determine file paths for ads.txt setup.');
			update_option('ezoic_adstxtmanager_status', $redirect_result);
			return;
		}

		if (empty($filePath) || !$wp_filesystem->exists($filePath)) {
			// No physical ads.txt file exists - check if redirect is working
			$redirect_result = Ezoic_AdsTxtManager::ezoic_verify_adstxt_redirect();
			update_option('ezoic_adstxtmanager_status', $redirect_result);
			return;
		}

		$redirect_result = Ezoic_AdsTxtManager::ezoic_verify_adstxt_redirect();

		if ($redirect_result['status'] === false) {
			// Don't rename file if it's an invalid ATM ID issue
			if (isset($redirect_result['error']) && $redirect_result['error'] === 'invalid_atm_id') {
				update_option('ezoic_adstxtmanager_status', $redirect_result);
				return;
			}

			// Try to rename existing ads.txt file to allow redirect to work
			$renameSuccess = $wp_filesystem->move($filePath, $newFilePath);
			if ($renameSuccess == false) {
				$is_writable = $wp_filesystem->is_writable($filePath);
				$dir_writable = $wp_filesystem->is_writable(dirname($filePath));

				$message = "<strong>Unable to set up ads.txt redirect:</strong> Your existing ads.txt file cannot be renamed/removed.<br><br>";

				if (!$is_writable && !$dir_writable) {
					$message .= "<strong>Issue:</strong> Both the file and directory need write permissions.<br><br>";
				} elseif (!$is_writable) {
					$message .= "<strong>Issue:</strong> The ads.txt file needs write permissions.<br><br>";
				} elseif (!$dir_writable) {
					$message .= "<strong>Issue:</strong> The directory needs write permissions.<br><br>";
				}

				$message .= "<strong>Next steps:</strong>";
				$message .= "<ul style='list-style: disc; margin-left: 20px;'>";
				$message .= "<li>Manually rename/remove " . basename($filePath) . " and try again</li>";
				$message .= "<li>OR contact your hosting provider to fix file permissions</li>";
				$message .= "</ul>";
			} else {
				// File was renamed, test redirect again
				$redirect_result = Ezoic_AdsTxtManager::ezoic_verify_adstxt_redirect();
			}
		}

		if (!empty($message)) {
			$redirect_result['message'] = $message;
		}

		update_option('ezoic_adstxtmanager_status', $redirect_result);
	}

	public function TearDownSolution()
	{
		global $wp_filesystem;
		$modifiedFilePath = $this->modifiedAdsTxtFileName();
		$origFilePath = $this->origAdsTxtFileName();

		// Check if paths could be determined
		if ($modifiedFilePath === false || $origFilePath === false) {
			Ezoic_Integration_Logger::log_error("Cannot determine file paths for ads.txt teardown", 'AdsTxtManager');
			return;
		}

		if (empty($modifiedFilePath) || !$wp_filesystem->exists($modifiedFilePath)) {
			return;
		}

		$renameSuccess = $wp_filesystem->move($modifiedFilePath, $origFilePath);
		if ($renameSuccess === false) {
			Ezoic_Integration_Logger::log_error("Failed to restore ads.txt from backup file '{$modifiedFilePath}' to '{$origFilePath}'", 'AdsTxtManager');
			// Optionally, you could add more error handling here, such as notifying the user or admin.
		}

		if (get_option('ezoic_adstxtmanager_status') !== false) {
			delete_option('ezoic_adstxtmanager_status');
		}
	}
}
