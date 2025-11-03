<?php
/**
 * Course Dashboard Manager - Logger
 *
 * Centralized logging system for consistent error handling
 */

namespace CourseBoxManager;

class Logger {

    /**
     * Log levels
     */
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const INFO = 'INFO';
    const DEBUG = 'DEBUG';

    /**
     * Log a message
     *
     * @param string $message The message to log
     * @param string $level Log level (ERROR, WARNING, INFO, DEBUG)
     * @param array $context Additional context data
     */
    public static function log($message, $level = self::INFO, $context = []) {
        // Only log if WP_DEBUG is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            // In production, only log errors and warnings
            if ($level !== self::ERROR && $level !== self::WARNING) {
                return;
            }
        }

        $prefix = '[CBM ' . $level . ']';
        $log_message = $prefix . ' ' . $message;

        // Add context if provided
        if (!empty($context)) {
            $log_message .= ' | Context: ' . wp_json_encode($context);
        }

        error_log($log_message);
    }

    /**
     * Log an error
     */
    public static function error($message, $context = []) {
        self::log($message, self::ERROR, $context);
    }

    /**
     * Log a warning
     */
    public static function warning($message, $context = []) {
        self::log($message, self::WARNING, $context);
    }

    /**
     * Log info message
     */
    public static function info($message, $context = []) {
        self::log($message, self::INFO, $context);
    }

    /**
     * Log debug message (only in WP_DEBUG mode)
     */
    public static function debug($message, $context = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log($message, self::DEBUG, $context);
        }
    }
}
