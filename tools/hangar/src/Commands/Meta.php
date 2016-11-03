<?php
declare(strict_types=1);
namespace Airship\Hangar\Commands;

use Airship\Hangar\SessionCommand;

/**
 * Class Meta
 * @package Airship\Hangar\Commands
 */
class Meta extends SessionCommand
{
    /**
     * @var bool
     */
    public $essential = false;

    /**
     * @var int
     */
    public $display = 5;

    /**
     * @var string
     */
    public $name = 'Meta';

    /**
     * @var string
     */
    public $description = 'Add metadata to an update bundle.';

    /**
     * Fire the add command to add files and directories to this update pack.
     *
     * @param array $args
     * @return bool
     * @throws \Error
     */
    public function fire(array $args = []): bool
    {
        try {
            $this->getSession();
        } catch (\Error $e) {
            echo $e->getMessage(), "\n";
            return false;
        }

        if (\count($args) === 0) {
            echo 'No file passed.', "\n";
            return false;
        }
        $file = $args[0];
        if (\is_readable($file)) {
            $meta = \json_decode(
                \file_get_contents($file),
                true
            );
            if ($meta === false) {
                throw new \Error(
                    \json_last_error_msg(),
                    \json_last_error()
                );
            }
            $this->session['metadata'] = $meta;
            echo 'Metadata loaded.', "\n";
            return true;
        }
        throw new \Error('Could not read '. $file);
    }
}
