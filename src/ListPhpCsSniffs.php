<?php

namespace yCodeTech\List_PHPCS_Sniffs;

/**
 * List PHP CodeSniffer (PHPCS) sniffs for all installed standards.
 *
 * For use with squizlabs/php_codesniffer composer package.
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
	 * @var Utils
	 */
	private Utils $utils;

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
		$this->utils = new Utils();

		$this->OS = $this->utils->findOs();

		$this->phpcsPath = $this->utils->wherePhpcs($this->getOs());
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
		$standards = $this->utils->shellExec("{$this->getPhpcsPath()} -i");

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
		$output = $this->utils->shellExec("{$this->getPhpcsPath()} --standard=$standards -e");

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
				throw new \Exception("Sniff '$sniff' doesn't seem to belong to any standards: '" . implode(', ', $standards) . "'");
			})
			->map(function ($item) {
				$result = ["deprecated" => []];

				// Separate deprecated sniffs in the item array from the rest of the sniffs.
				$item->each(function ($sniff) use (&$result) {
					// If the sniff is deprecated...
					if ($this->utils->isSniffDeprecated($sniff)) {
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

				if (empty($result['deprecated'])) {
					unset($result['deprecated']);
				}

				return $result;
			})->toArray();
	}
}
