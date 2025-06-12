<?php

use Illuminate\Support\Collection;

class ListPhpCsSniffs {
	/**
	 * Path to the phpcs executable.
	 * @var string
	 */
	private string $phpcsPath;

	/**
	 * Set the path to the phpcs executable.
	 *
	 * @param string $phpcsPath
	 *
	 * @return void
	 */
	public function setPhpcsPath($phpcsPath) {
		$this->phpcsPath = $phpcsPath;
	}

	/**
	 * Get the path to the phpcs executable.
	 *
	 * @return string
	 */
	public function getPhpcsPath() {
		return $this->phpcsPath;
	}

	/**
	 * Summary of run
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
	 * @return Collection
	 */
	private function parseSniffs($phpcsOutput, $standards) {
		$lines = explode("\n", $phpcsOutput);

		// Collect and filter the lines to extract only valid sniff names.
		return collect($lines)
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
				
			})
			->unique() // Remove duplicate sniffs.
			->values(); // Re-index the collection.
	}

	private function groupSniffsByStandard(Collection $sniffs, $standards) {
		// Group sniffs by their standards name.
		return $sniffs
			->groupBy(function ($sniff) use ($standards) {
				// Determine which standard the sniff belongs to by matching the prefix.
				foreach ($standards as $standard) {
					// Check if the sniff starts with the standard name.
					// This is important for correctly grouping sniffs.
					if (str_starts_with($sniff, $standard)) {
						return $standard;
					}
				}
				return null;
			})
			->map(function ($sniffs) {
				$result = [];

				// Group any of the standards deprecated sniffs separately.
				$deprecated = $this->groupDeprecatedSniffs($sniffs);
				
				if ($deprecated->isNotEmpty()) {
					$result['deprecated'] = $deprecated->all();
				}

				// Remove the deprecated sniffs from the active sniffs.
				$sniffs->each(function ($sniff) use ($deprecated, &$result) {
					// If the sniff is not deprecated, add it to the result.
					if (!$this->isSniffDeprecated($sniff, $deprecated)) {
						$result[] = $sniff;
					}
				});

				return $result;
			})
			->toArray();
	}

	/**
	 * Group deprecated sniffs separate to active sniffs.
	 *
	 * @param Collection $sniffs
	 *
	 * @return Collection The deprecated sniffs.
	 */
	private function groupDeprecatedSniffs(Collection $sniffs) {
		// Identify deprecated sniffs (ending with '*').
		return $sniffs
			->filter(function ($sniff) {
				return substr($sniff, -1) === '*';
			})
			->map(function ($sniff) {
				// Now we know which ones are deprecated, we can remove the '*'
				// from the end of the sniff name.
				return str_replace(' *', '', $sniff);
			})
			->values();
	}

	/**
	 * Check if a sniff is deprecated.
	 * This checks if the sniff name contains any of the deprecated sniff names.
	 *
	 * @param string $sniff
	 * @param Illuminate\Support\Collection $deprecated
	 *
	 * @return boolean
	 */
	private function isSniffDeprecated(string $sniff, Collection $deprecated) {
		return $deprecated->contains(function ($deprecatedSniff) use ($sniff) {
			return str_contains($sniff, $deprecatedSniff);
		});
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
}
