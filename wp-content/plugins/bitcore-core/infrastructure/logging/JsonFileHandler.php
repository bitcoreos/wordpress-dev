<?php

declare(strict_types=1);

/**
 * BITCORE Logging Infrastructure - JSON File Handler
 *
 * @package BITCORE\Infrastructure\Logging
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Infrastructure\Logging;

use DateTimeImmutable;
use RuntimeException;

/**
 * JSON File Log Handler
 *
 * Writes log records as JSON lines to rotating files.
 * Supports automatic log rotation based on maxFiles configuration.
 *
 * @since 1.0.0
 */
class JsonFileHandler implements HandlerInterface
{
    /** @var array<string, int> Log level priority mapping */
    private const LEVELS = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];

    /** @var string Base path for log files */
    private string $path;

    /** @var int Maximum number of log files to retain */
    private int $maxFiles;

    /** @var int Minimum level priority to handle */
    private int $level;

    /**
     * Constructor.
     *
     * @param string $path     Base directory for log files.
     * @param int    $maxFiles Maximum files to retain (default: 7).
     * @param string $level    Minimum level to handle (default: 'debug').
     * @since 1.0.0
     */
    public function __construct(string $path, int $maxFiles = 7, string $level = 'debug')
    {
        $this->path     = rtrim($path, '/\\');
        $this->maxFiles = max(1, $maxFiles);
        $this->level    = self::LEVELS[$level] ?? self::LEVELS['debug'];

        $this->ensureDirectoryExists();
    }

    /**
     * Check if this handler handles the given record level.
     *
     * @param array<string, mixed> $record The log record to check.
     * @return bool True if the handler should process this record.
     * @since 1.0.0
     */
    public function isHandling(array $record): bool
    {
        $recordLevel = self::LEVELS[$record['level'] ?? 'debug'] ?? self::LEVELS['debug'];
        return $recordLevel >= $this->level;
    }

    /**
     * Handle a log record.
     *
     * @param array<string, mixed> $record The log record to handle.
     * @return bool True if the record was handled successfully.
     * @since 1.0.0
     */
    public function handle(array $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        try {
            $this->rotateLogs();
            $this->writeRecord($record);
            return true;
        } catch (RuntimeException $e) {
            // Fallback to error_log on failure
            error_log('BITCORE Logger: Failed to write to file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ensure the log directory exists.
     *
     * @throws RuntimeException If directory cannot be created.
     * @since 1.0.0
     */
    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->path)) {
            if (!mkdir($this->path, 0750, true) && !is_dir($this->path)) {
                throw new RuntimeException("Failed to create log directory: {$this->path}");
            }
        }

        if (!is_writable($this->path)) {
            throw new RuntimeException("Log directory is not writable: {$this->path}");
        }
    }

    /**
     * Rotate log files, keeping only maxFiles.
     *
     * @since 1.0.0
     */
    private function rotateLogs(): void
    {
        $files = glob($this->path . '/bitcore-*.log');
        
        if ($files === false || count($files) <= $this->maxFiles) {
            return;
        }

        // Sort by modification time (oldest first)
        usort($files, static function (string $a, string $b): int {
            return filemtime($a) <=> filemtime($b);
        });

        // Delete oldest files exceeding maxFiles
        $toDelete = array_slice($files, 0, -$this->maxFiles);
        foreach ($toDelete as $file) {
            if (is_writable($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Write a log record to the current log file.
     *
     * @param array<string, mixed> $record The log record.
     * @throws RuntimeException If writing fails.
     * @since 1.0.0
     */
    private function writeRecord(array $record): void
    {
        $filename = $this->getCurrentFilename();
        $jsonLine = $this->formatRecord($record);

        $result = file_put_contents(
            $filename,
            $jsonLine . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        if ($result === false) {
            throw new RuntimeException("Failed to write to log file: {$filename}");
        }
    }

    /**
     * Get the current log filename based on date.
     *
     * @return string Full path to log file.
     * @since 1.0.0
     */
    private function getCurrentFilename(): string
    {
        $date = (new DateTimeImmutable())->format('Y-m-d');
        return $this->path . '/bitcore-' . $date . '.log';
    }

    /**
     * Format a log record as JSON.
     *
     * @param array<string, mixed> $record The log record.
     * @return string JSON string.
     * @since 1.0.0
     */
    private function formatRecord(array $record): string
    {
        $data = [
            'datetime' => ($record['datetime'] instanceof DateTimeImmutable)
                ? $record['datetime']->format('c')
                : (new DateTimeImmutable())->format('c'),
            'channel'  => $record['channel'] ?? 'bitcore',
            'level'    => $record['level'] ?? 'info',
            'message'  => $record['message'] ?? '',
            'context'  => $record['context'] ?? [],
        ];

        if (!empty($record['tenant_id'])) {
            $data['tenant_id'] = $record['tenant_id'];
        }

        if (!empty($record['extra'])) {
            $data['extra'] = $record['extra'];
        }

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
