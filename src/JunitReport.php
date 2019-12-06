<?php

declare(strict_types=1);

namespace DQ5Studios\PsalmJunit;

use Psalm\Codebase;
use Psalm\Plugin\Hook\AfterAnalysisInterface;
use Psalm\SourceControl\SourceControlInfo;

use const PSALM_VERSION;

/**
 * @psalm-type  IssueData = array{
 *     severity: string,
 *     line_from: int,
 *     line_to: int,
 *     type: string,
 *     message: string,
 *     file_name: string,
 *     file_path: string,
 *     snippet: string,
 *     from: int,
 *     to: int,
 *     snippet_from: int,
 *     snippet_to: int,
 *     column_from: int,
 *     column_to: int
 * }
 */

class JunitReport implements AfterAnalysisInterface
{
    /** @var string $file Output filename */
    public static $file = "psalm_junit_report.xml";
    /** @var string $path Output folder */
    public static $path = __DIR__;
    /** @var float $start_time Close enough of a start time */
    public static $start_time = 0.0;

    /**
     * {@inheritDoc}
     */
    public static function afterAnalysis(
        Codebase $codebase,
        array $issues,
        array $build_info,
        SourceControlInfo $source_control_info = null
    ) {
        // Global totals
        $test_count = 0;
        $failure_count = 0;
        if (!empty($issues)) {
            $severity_list = array_count_values(array_column($issues, "severity"));
            $test_count = count($issues);
            if (isset($severity_list["error"])) {
                $failure_count = $severity_list["error"];
            }
        }
        $suite_name = "Psalm " . PSALM_VERSION;
        $time_taken = number_format(microtime(true) - self::$start_time, 2);

        // Reformat the data to group by file
        /** @var array<string,IssueData[]> */
        $processed_file_list = array_fill_keys(array_keys($codebase->analyzer->getMixedCounts()), []);
        foreach ($issues as $issue_detail) {
            $key = $issue_detail["file_path"];
            array_push($processed_file_list[$key], $issue_detail);
        }

        // <testsuites> parent element
        $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $output .= "<testsuites name=\"{$suite_name}\" failures=\"{$failure_count}\" ";
        $output .= "tests=\"{$test_count}\" errors=\"0\" time=\"{$time_taken}\">\n";

        foreach ($processed_file_list as $file_path => $issue_list) {
            $file_failure_count = 0;
            $file_test_count = count($issue_list);
            $tc_list = "";

            // Build <testcase> elements

            // No errors in this file
            if (empty($issue_list)) {
                $file_test_count = 1;
                $tc_list = "\t\t<testcase name=\"{$file_path}\" />\n";
            }

            // Lots of errors in this file
            foreach ($issue_list as $issue) {
                $tc_list .= "\t\t<testcase name=\"{$issue["type"]} at {$file_path} ";
                $tc_list .= "({$issue["line_from"]}:{$issue["column_from"]})\">\n";
                if ($issue["severity"] == "error") {
                    $file_failure_count++;
                    $tc_list .= "\t\t\t<failure type=\"{$issue["severity"]}\" message=\"{$issue["message"]}\" />\n";
                } else {
                    $tc_list .= "\t\t\t<skipped message=\"{$issue["message"]}\" />\n";
                }
                $tc_list .= "\t\t</testcase>\n";
            }

            // <testsuite> file report element
            $output .= "\t<testsuite name=\"{$file_path}\" failures=\"{$file_failure_count}\" ";
            $output .= "tests=\"{$file_test_count}\" errors=\"0\">\n";
            $output .= $tc_list;
            $output .= "\t</testsuite>\n";
        }

        $output .= "</testsuites>\n";

        $filepath = rtrim(self::$path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::$file;

        file_put_contents($filepath, $output);
    }
}