<?php
/**
 * BITCORE shared PHPUnit base class.
 *
 * Exists to satisfy the historical `require_once __DIR__ . '/TestCase.php'` in
 * tests/bootstrap.php. Evidence gathered 2026-11-23 from the only three files
 * that extend a `TestCase` (connectors/AbstractConnectorTest.php,
 * container/ContainerTest.php, rest/RestSchemaTest.php): they call nothing but
 * PHPUnit assertions, expectException/expectExceptionMessage and reflection on
 * plugin classes. None calls a WordPress helper or a base-class helper of its
 * own, so this class must stay a thin PHPUnit extension.
 *
 * @package BITCORE\Tests
 * @since 1.4.0
 */

declare(strict_types=1);

namespace BITCORE\Tests;

abstract class TestCase extends \PHPUnit\Framework\TestCase {
}
