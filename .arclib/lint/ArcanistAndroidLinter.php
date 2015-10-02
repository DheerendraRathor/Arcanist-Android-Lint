<?php

/*

Copyright 2012-2015 iMobile3, LLC. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, is permitted provided that adherence to the following
conditions is maintained. If you do not agree with these terms,
please do not use, install, modify or redistribute this software.

1. Redistributions of source code must retain the above copyright notice, this
list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY IMOBILE3, LLC "AS IS" AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO
EVENT SHALL IMOBILE3, LLC OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

/**
 * Uses Android Lint to detect various errors in Java code. To use this linter,
 * you must install the Android SDK and configure which codes you want to be
 * reported as errors, warnings and advice.
 *
 * @group linter
 */
final class ArcanistAndroidLinter extends ArcanistLinter
{

    private $gradleModules = array('app');
    private $findBugsEnabled = true;
    private $checkStyleEnabled = true;
    private $pmdEnabled = true;

    public function getInfoName()
    {
        return "Android";
    }

    public function getLinterConfigurationName()
    {
        return 'android';
    }

    public function getLinterConfigurationOptions()
    {

        $options = parent::getLinterConfigurationOptions();

        $options['modules'] = array(
            'type' => 'optional list<string>',
            'help' => pht('List of all gradle modules. Default is [\'app\']')
        );

        $options['findbugs'] = array(
            'type' => 'optional bool',
            'help' => pht('Enable findBugs. Enabled by default')
        );

        $options['checkstyle'] = array(
            'type' => 'optional bool',
            'help' => pht('Enable Checkstyle. Enabled by default')
        );

        $options['pmd'] = array(
            'type' => 'optional bool',
            'help' => pht('Enable pmd. Enabled by default')
        );

        return $options;
    }

    public function setLinterConfigurationValue($key, $value)
    {
        switch ($key) {
            case 'modules':
                $this->gradleModules = $value;
                return;
            case 'findbugs':
                $this->findBugsEnabled = $value;
                return;
            case 'checkstyle':
                $this->checkStyleEnabled = $value;
                return;
            case 'pmd':
                $this->pmdEnabled = $value;
                return;
        }
        parent::setLinterConfigurationValue($key, $value);
    }

    public function willLintPaths(array $paths)
    {
        return;
    }

    public function getLinterName()
    {
        return 'AndroidLint';
    }

    public function getLintSeverityMap()
    {
        return array();
    }

    public function getLintNameMap()
    {
        return array();
    }

    protected function shouldLintDirectories()
    {
        return true;
    }

    public function lintPath($path)
    {

        $lint_xml_files = $this->runGradle($path);

        $lint_files       = $lint_xml_files[0];
        $findbugs_files   = $lint_xml_files[1];
        $checkstyle_files = $lint_xml_files[2];
        $pmd_files        = $lint_xml_files[3];

        $absolute_path = $this->getEngine()->getFilePathOnDisk($path);

        $lintMessages       = $this->getGradleLintMessages($lint_files, $absolute_path);
        $findbugsMessages   = $this->getFindbugsMessages($findbugs_files, $absolute_path);
        $pmdMessages        = $this->getPMDMessages($pmd_files, $absolute_path);
        $checkstyleMessages = $this->getCheckStyleMessages($checkstyle_files, $absolute_path);

        foreach ($lintMessages as $message) {
            $this->addLintMessage($message);
        }
        foreach ($findbugsMessages as $message) {
            $this->addLintMessage($message);
        }
        foreach ($pmdMessages as $message) {
            $this->addLintMessage($message);
        }
        foreach ($checkstyleMessages as $message) {
            $this->addLintMessage($message);
        }

        putenv('_JAVA_OPTIONS');
    }

    private function getGradlePath()
    {
        $gradle_bin = "gradle";

        list($err, $stdout) = exec_manual('which %s', $gradle_bin);
        if ($err) {
            throw new ArcanistUsageException("Gradle does not appear to be " . 'available on the path.');
        }

        return trim($stdout);
    }

    private function runGradle($path)
    {
        $root = $this->getEngine()->getWorkingCopy()->getProjectRoot();

        $gradle_bin = join('/', array(
            rtrim($path, '/'),
            "gradlew"
        ));
        if (!file_exists($gradle_bin)) {
            $gradle_bin = $this->getGradlePath();
        }

        $cwd = getcwd();
        chdir($root);
        $lint_command     = '';
        $output_paths     = array();
        $findbugs_paths   = array();
        $checkStyle_paths = array();
        $pmd_paths        = array();

        foreach ($this->gradleModules as $module) {
            $lint_command .= ':' . $module . ':lint ';
            if ($this->findBugsEnabled) {
                $lint_command .= ':' . $module . ':findbugs ';
                $findbugs_output_path = $root . '/' . str_replace(':', '/', $module);
                $findbugs_output_path .= '/build/reports/findbugs/findbugs.xml';
                $findbugs_paths[] = $findbugs_output_path;
            }
            if ($this->checkStyleEnabled) {
                $lint_command .= ':' . $module . ':checkstyle ';
                $checkStyle_output_path = $root . '/' . str_replace(':', '/', $module);
                $checkStyle_output_path .= '/build/reports/checkstyle/checkstyle.xml';
                $checkStyle_paths[] = $checkStyle_output_path;
            }
            if ($this->pmdEnabled) {
                $lint_command .= ':' . $module . ':pmd ';
                $pmd_output_path = $root . '/' . str_replace(':', '/', $module);
                $pmd_output_path .= '/build/reports/pmd/pmd.xml';
                $pmd_paths[] = $pmd_output_path;
            }

            $output_path = $root . '/' . str_replace(':', '/', $module);
            $output_path .= '/build/outputs/lint-results.xml';
            $output_paths[] = $output_path;
            $shouldLint     = False;
            if (file_exists($output_path)) {
                $output_accessed_time = fileatime($output_path);
                $path_modified_time   = Filesystem::getModifiedTime($path);

                if ($path_modified_time > $output_accessed_time) {
                    unlink($output_path);
                    $shouldLint = True;
                }
            } else {
                $shouldLint = True;
            }
            if ($shouldLint) {
                $final_lint_command = $gradle_bin . ' ' . $lint_command;
                echo "Linting Project...\n";
                echo "Executing: $final_lint_command \n";
                exec_manual($final_lint_command);
            }
        }

        chdir($cwd);


        foreach ($output_paths as $output_path) {
            if (!file_exists($output_path)) {
                throw new ArcanistUsageException('Error executing gradle command');
            }
        }

        return array(
            $output_paths,
            $findbugs_paths,
            $checkStyle_paths,
            $pmd_paths
        );
    }

    private function getGradleLintMessages($lint_files, $absolute_path)
    {
        $messages = array();
        foreach ($lint_files as $file) {
            $filexml = simplexml_load_string(file_get_contents($file));

            foreach ($filexml as $issue) {
                $loc_attrs = $issue->location->attributes();
                $filename  = (string) $loc_attrs->file;

                if ($filename != $absolute_path) {
                    continue;
                }

                $issue_attrs = $issue->attributes();

                $message = new ArcanistLintMessage();
                $message->setPath($filename);
                // Line number and column are irrelevant for
                // artwork and other assets
                if (isset($loc_attrs->line)) {
                    $message->setLine(intval($loc_attrs->line));
                }
                if (isset($loc_attrs->column)) {
                    $message->setChar(intval($loc_attrs->column));
                }
                $message->setName((string) $issue_attrs->id);
                $message->setCode((string) $issue_attrs->category);
                $message->setDescription(preg_replace('/^\[.*?\]\s*/', '', $issue_attrs->message));

                // Setting Severity
                if ($issue_attrs->severity == 'Error' || $issue_attrs->severity == 'Fatal') {
                    $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
                } else if ($issue_attrs->severity == 'Warning') {
                    $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
                } else {
                    $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
                }

                $messages[$message->getPath() . ':' . $message->getLine() . ':' . $message->getChar() . ':' . $message->getName() . ':' . $message->getDescription()] = $message;
            }
        }

        return $messages;
    }

    private function getFindbugsMessages($findbugs_files, $absolute_path)
    {
        $messages = array();
        foreach ($findbugs_files as $file) {
            $filexml = simplexml_load_string(file_get_contents($file));

            $bugInstances = $filexml->xpath("//BugInstance");
            foreach ($bugInstances as $BugInstance) {
                $sourceLine      = $BugInstance->SourceLine;
                $sourceLineAttrs = $sourceLine->attributes();
                $path            = (string) $sourceLineAttrs->sourcepath;
                if (strpos($absolute_path, $path) === false) {
                    continue;
                }

                $BugInstanceAttrs = $BugInstance->attributes();

                $message = new ArcanistLintMessage();
                $message->setPath($absolute_path);
                if (isset($sourceLineAttrs->start)) {
                    $message->setLine(intval($sourceLineAttrs->start));
                }
                $message->setName((string) $BugInstanceAttrs->type);
                $message->setCode((string) $BugInstanceAttrs->category);
                $message->setDescription(preg_replace('/^\[.*?\]\s*/', '', (string) $BugInstance->LongMessage));

                // Setting Severity
                $rank = intval($BugInstanceAttrs->rank);
                if ($rank >= 1 && $rank <= 4) {
                    $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
                } else if ($rank > 4 && $rank < 15) {
                    $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
                } else {
                    $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
                }

                $messages[$message->getPath() . ':' . $message->getLine() . ':' . $message->getName() . ':' . $message->getDescription()] = $message;
            }
        }

        return $messages;
    }

    private function getPMDMessages($pmd_files, $absolute_path)
    {
        $messages = array();
        foreach ($pmd_files as $file) {
            $filexml = simplexml_load_string(file_get_contents($file));

            $violationsNodes = $filexml->xpath("//file[@name=\"" . $absolute_path . "\"]");
            foreach ($violationsNodes as $violationsNode) {
                foreach ($violationsNode->children() as $violation) {
                    $text       = (string) $violation;
                    $attributes = $violation->attributes();

                    $message = new ArcanistLintMessage();
                    $message->setPath($absolute_path);
                    if (isset($attributes->beginline)) {
                        $message->setLine(intval($attributes->beginline));
                    }
                    if (isset($attributes->begincolumn)) {
                        $message->setChar(intval($attributes->begincolumn));
                    }
                    $message->setName((string) $attributes->rule);
                    $message->setCode((string) $attributes->ruleset);
                    $message->setDescription(trim(preg_replace('/^\[.*?\]\s*/', '', $text)));

                    $priority = intval($attributes->priority);

                    if ($priority == 1 or $priority == 2) {
                        $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
                    } else if ($priority == 3 or $priority == 4) {
                        $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
                    } else {
                        $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
                    }

                    $messages[$message->getPath() . ':' . $message->getLine() . ':' . $message->getChar() . ':' . $message->getDescription()] = $message;
                }
            }
        }

        return $messages;
    }

    private function getCheckStyleMessages($checkstyle_files, $absolute_path)
    {
        $messages = array();
        foreach ($checkstyle_files as $file) {
            $filexml = simplexml_load_string(file_get_contents($file));

            $errorNodes = $filexml->xpath("//file[@name=\"" . $absolute_path . "\"]");
            foreach ($errorNodes as $errorNode) {
                foreach ($errorNode->children() as $error) {
                    $attributes = $error->attributes();

                    $message = new ArcanistLintMessage();
                    $message->setPath($absolute_path);
                    if (isset($attributes->line)) {
                        $message->setLine(intval($attributes->line));
                    }
                    if (isset($attributes->column)) {
                        $message->setChar(intval($attributes->column));
                    }
                    $message->setName('Checkstyle');
                    $source          = (string) $attributes->source;
                    $source_packaage = explode('.', $source);
                    $code            = end($source_packaage);

                    $message->setCode($code);
                    $message->setDescription(trim(preg_replace('/^\[.*?\]\s*/', '', $attributes->message)));

                    $priority = (string) $attributes->severity;

                    if ($priority == "error") {
                        $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
                    } else if ($priority == "warning") {
                        $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
                    } else if ($priority == "info") {
                        $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
                    } else {
                        $message->setSeverity(ArcanistLintSeverity::SEVERITY_DISABLED);
                    }

                    $messages[$message->getPath() . ':' . $message->getLine() . ':' . $message->getChar() . ':' . $message->getName() . ':' . $message->getDescription()] = $message;
                }
            }
        }

        return $messages;
    }
}
