<?php
/**
 * PHPStan stubs for WP-CLI runtime classes.
 *
 * @package blocky
 */

namespace {

    /**
     * WP-CLI main facade.
     */
    class WP_CLI {
        /**
         * Register a CLI command.
         *
         * @param string $name    Command name.
         * @param mixed  $callable Callable.
         * @param array  $args    Args.
         */
        public static function add_command( string $name, $callable, array $args = array() ): void {}
        public static function log(string $message): void {}
        public static function success(string $message): void {}
        public static function warning(string $message): void {}
        public static function error(string $message): never {}
    }

    /**
     * WP-CLI base command class.
     */
    class WP_CLI_Command {}
}

namespace WP_CLI\Utils {

    /**
     * Format rows for CLI output.
     *
     * @param string              $format Output format.
     * @param array<int, mixed>   $items  Rows.
     * @param array<int, string>  $fields Columns.
     */
    function format_items( string $format, array $items, array $fields ): void {}
}

