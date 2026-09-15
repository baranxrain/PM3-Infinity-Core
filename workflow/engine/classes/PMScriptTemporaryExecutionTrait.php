<?php

/**
 * Executes trusted legacy trigger source through an OS-owned temporary include.
 * The include runs in the consuming object's method scope, preserving $this.
 */
trait PMScriptTemporaryExecutionTrait
{
    private function runTemporaryTriggerScript($sScript, $returnResult, $sCode = null)
    {
        $temporaryScript = tmpfile();
        if ($temporaryScript === false) {
            throw new RuntimeException('Unable to create temporary trigger script.');
        }
        $metadata = stream_get_meta_data($temporaryScript);
        if (!is_array($metadata) || empty($metadata['uri'])) {
            fclose($temporaryScript);
            throw new RuntimeException('Unable to resolve temporary trigger script.');
        }
        try {
            $source = "<?php\n" . (string) $sScript;
            if ($returnResult) {
                $source .= "\nreturn isset(\$bResult) ? (bool) \$bResult : false;\n";
            }
            $length = strlen($source);
            $offset = 0;
            while ($offset < $length) {
                $written = fwrite($temporaryScript, substr($source, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Unable to write temporary trigger script.');
                }
                $offset += $written;
            }
            if (!fflush($temporaryScript)) {
                throw new RuntimeException('Unable to flush temporary trigger script.');
            }
            $result = include $metadata['uri'];
            return $returnResult ? (bool) $result : null;
        } finally {
            fclose($temporaryScript);
        }
    }

    protected function executeTriggerScript($sScript, $sCode)
    {
        $this->runTemporaryTriggerScript($sScript, false, $sCode);
    }

    protected function evaluateTriggerConditionScript($sScript)
    {
        return $this->runTemporaryTriggerScript($sScript, true);
    }
}
