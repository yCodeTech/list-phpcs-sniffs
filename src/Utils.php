<?php

namespace yCodeTech\List_PHPCS_Sniffs;

class Utils {
	/**
	 * Find the operating system type from PHP's global constant: PHP_OS.
	 *
	 * @return string
	 */
	public function findOs() {
		// Check the first three characters of PHP_OS to determine the OS type.
		$os = substr(PHP_OS, 0, 3);

		return strtoupper($os) === 'WIN' ? 'Win' : 'Unix';
	}

	/**
	 * Find the phpcs path on the system's PATH using 'where phpcs' or 'which phpcs'
	 * depending on the OS.
	 *
	 * @param string $os The operating system type ('Win' or 'Unix').
	 *
	 * @return string
	 * @throws \RuntimeException If phpcs executable is not found.
	 */
	public function wherePhpcs($os) {
		// Use the 'where' command on Windows and 'which' on Unix-like systems
		// to find the phpcs executable.
		$command = $os === 'Win' ? 'where' : 'which';
		$output = $this->shellExec("$command phpcs");

		if ($output === null || $output === false) {
			throw new \RuntimeException("Could not find phpcs executable using '$command phpcs'.\n Please either install `squizlabs/php_codesniffer` globally via Composer or manually add the phpcs path into the system's environment PATH.\n\n Exception generated");
		}

		// Split the output into lines and find the first line that ends with 'phpcs'.
		// This is to ensure we get the correct path to the phpcs executable if there
		// are multiple paths.
		foreach (explode("\n", $output) as $phpcs) {
			$phpcs = trim($phpcs);
			if (str_ends_with($phpcs, 'phpcs')) {
				return addslashes($phpcs);
			}
		}
		throw new \RuntimeException("Could not find phpcs.");
	}

	/**
	 * Execute command via shell and return the complete output as a string
	 *
	 * @param string $command
	 *
	 * @return boolean|string|null The output.
	 */
	public function shellExec($command) {
		return shell_exec($command);
	}

	/**
	 * Check if a sniff is deprecated.
	 * This checks if the sniff name contains a trailing '*',
	 * which indicates that it is deprecated.
	 *
	 * @param string $sniff
	 *
	 * @return boolean
	 */
	public function isSniffDeprecated(string $sniff) {
		return substr($sniff, -1) === '*';
	}
}
