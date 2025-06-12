<?php

namespace yCodeTech\List_PHPCS_Sniffs;

/**
 * List PHP CodeSniffer sniffs for all installed standards.
 *
 * For use with SquizLabs PHP_CodeSniffer (PHPCS).
 * @link https://github.com/PHPCSStandards/PHP_CodeSniffer
 *
 * Licensed under The MIT License
 *
 * @author     Stuart Norman - @yCodeTech <stuart-norman@hotmail.com>
 * @copyright  2025 yCodeTech
 * @license    The MIT License
 * @version    1.0.0
 */

use Illuminate\Support\Collection;

class ListPhpCsSniffs {
	/**
	 * @var string Path to the phpcs executable.
	 */
	private string $phpcsPath;

	/**
	 * @var string Operating System type.
	 */
	private string $OS;

	/**
	 * Constructor to initialize the class.
	 * It determines the operating system type and finds the path to the phpcs executable.
	 */
	public function __construct() {
		$this->OS = $this->findOS();

		$this->phpcsPath = $this->wherePhpcs();
	}

	/**
	 * Find the operating system type from PHP's global constant: PHP_OS.
	 *
	 * @return string
	 */
	private function findOs() {
		// Check the first three characters of PHP_OS to determine the OS type.
		$os = substr(PHP_OS, 0, 3);

		return strtoupper($os) === 'WIN' ? 'Win' : 'Unix';
	}

	/**
	 * Find the phpcs path on the system's PATH using 'where phpcs' or 'which phpcs'
	 * depending on the OS.
	 *
	 * @return string
	 * @throws RuntimeException If phpcs executable is not found.
	 */
	private function wherePhpcs() {
		// Use the 'where' command on Windows and 'which' on Unix-like systems
		// to find the phpcs executable.
		$command = $this->getOs() === 'Win' ? 'where' : 'which';
		$output = $this->shellExec("$command phpcs");

		if ($output === null || $output === false) {
			throw new RuntimeException("Could not find phpcs executable using '$command phpcs'.\n Please either install `squizlabs/php_codesniffer` globally via Composer or manually add the phpcs path into the system's environment PATH.\n\n Exception generated");
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
		throw new RuntimeException("Could not find phpcs.");
	}

	/**
	 * Get the operating system type.
	 * This is used to determine the correct command to run.
	 *
	 * @return string
	 */
	private function getOs() {
		return $this->OS;
	}

	/**
	 * Get the path to the phpcs executable.
	 *
	 * @return string
	 */
	private function getPhpcsPath() {
		return $this->phpcsPath;
	}

	/**
	 * Run the sniff listing process.
	 *
	 * @return void
	 */
	public function run() {
		$standards = $this->getInstalledStandards();

		if ($standards === false) {
			echo "No installed standards found.\n";
			exit();
		}

		$output = $this->getSniffsFromStandards($standards);

		if ($output === false) {
			echo "Failed to retrieve sniffs from standards.\n";
			exit();
		}

		$standardsArray = explode(',', $standards);

		$sniffsCollection = $this->parseSniffs($output, $standardsArray);

		$sniffs = $this->groupSniffsByStandard($sniffsCollection, $standardsArray);

		var_dump($sniffs);
	}

	/**
	 * Get the installed standards from phpcs.
	 *
	 * @return boolean|string
	 */
	private function getInstalledStandards() {
		// Execute the phpcs.exe command to get the installed standards.
		$standards = $this->shellExec("{$this->getPhpcsPath()} -i");

		if ($standards === null || $standards === false) {
			// Command failed, so just return false.
			return false;
		}

		// Extract the standards list from the output string
		if (preg_match('/are (.+)$/', $standards, $matches)) {
			$standards = $matches[1];
			// Replace ' and ' with ',' to unify the delimiter, then split into array and rejoin to string
			$standardsArray = preg_split('/, | and /', $standards);
			$standards = implode(',', array_map('trim', $standardsArray));
		}

		return $standards;
	}

	/**
	 * Get the sniffs from all the installed standards.
	 *
	 * @param string $standards
	 *
	 * @return boolean|string
	 */
	private function getSniffsFromStandards($standards) {

		// Execute the phpcs command to list sniffs for the given standards.
		$output = $this->shellExec("{$this->getPhpcsPath()} --standard=$standards -e");

		if ($output === null || $output === false) {
			return false;
		}

		return $output;
	}

	/**
	 * Parse the output from phpcs to extract the sniffs.
	 *
	 * @param string $phpcsOutput
	 * @param array  $standards
	 *
	 * @return Collection The collection of sniffs.
	 */
	private function parseSniffs($phpcsOutput, $standards) {
		// Collect and filter the lines to extract only valid sniff names, and make
		// sure they are unique, discarding of any duplicates.
		return collect(explode("\n", $phpcsOutput))
			->map(function ($line) {
				// Trim whitespace from each line.
				return trim($line);
			})
			->filter(function ($line) use ($standards) {
				return collect($standards)->contains(function ($standard) use ($line) {
					// Check if the line starts with a standard name followed by a dot.
					// The dot is important to distinguish sniffs from other lines that may
					// contain the standard name and ensures we only include the actual sniffs.
					// For example, "PSR2.Classes.ClassDeclaration".
					return str_starts_with($line, "$standard.");
				});

			})->unique()->values();
	}

	/**
	 * Group sniffs by their standards.
	 *
	 * @param Collection $sniffs
	 * @param array      $standards
	 *
	 * @return array The grouped sniffs as an associative array.
	 * @throws Exception If a sniff does not belong to any of the standards.
	 */
	private function groupSniffsByStandard(Collection $sniffs, $standards) {
		// Group sniffs by their standards name.
		return $sniffs
			->groupBy(function ($sniff) use ($standards) {
				// Determine which standard the sniff belongs to by matching the prefix.
				foreach ($standards as $standard) {
					// Check if the sniff starts with the standard name.
					// This is important for correctly grouping sniffs.
					// The dot is important to match the full standard name, instead of
					// partially if 2 standards have the same beginning, like PRS1 and PRS12.
					if (str_starts_with($sniff, "$standard.")) {
						return $standard;
					}
				}
				throw new Exception("Sniff '$sniff' doesn't seem to belong to any standards: '" . implode(', ', $standards) . "'");
			})
			->map(function ($item) {
				$result = [];

				// Separate deprecated sniffs in the item array from the rest of the sniffs.
				$item->each(function ($sniff) use (&$result) {
					// If the sniff is deprecated...
					if ($this->isSniffDeprecated($sniff)) {
						// Remove the trailing '*' from the end of the sniff name.
						$sniff = str_replace(' *', '', $sniff);
						// Add it to the deprecated sniffs array of the result.
						$result['deprecated'][] = $sniff;
					}
					// Otherwise, add it to the result array.
					else {
						$result[] = $sniff;
					}
				});

				return $result;
			})->toArray();
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
	private function isSniffDeprecated(string $sniff) {
		return substr($sniff, -1) === '*';
	}

	/**
	 * Execute command via shell and return the complete output as a string
	 *
	 * @param string $command
	 *
	 * @return boolean|string|null The output.
	 */
	private function shellExec($command) {
		return shell_exec($command);
	}
}
