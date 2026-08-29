<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

use Cluion\Moduark\Exceptions\ModuleActivationMutationFailed;
use Throwable;

final class NativeAtomicFileWriter implements AtomicFileWriter
{
    public function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw ModuleActivationMutationFailed::directory($directory);
        }

        $temporary = tempnam($directory, '.moduark-activation-');

        if ($temporary === false) {
            throw ModuleActivationMutationFailed::write($path);
        }

        $handle = null;

        try {
            $handle = @fopen($temporary, 'wb');

            if ($handle === false
                || fwrite($handle, $contents) !== strlen($contents)
                || ! fflush($handle)
                || (function_exists('fsync') && ! fsync($handle))
                || ! @chmod($temporary, 0666 & ~umask())) {
                throw ModuleActivationMutationFailed::write($path);
            }

            fclose($handle);
            $handle = null;

            if (! @rename($temporary, $path)) {
                throw ModuleActivationMutationFailed::write($path);
            }
        } catch (ModuleActivationMutationFailed $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ModuleActivationMutationFailed::write($path, $exception);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }

            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
