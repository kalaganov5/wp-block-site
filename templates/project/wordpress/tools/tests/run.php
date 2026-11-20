<?php
/**
 * Тесты бизнес-правил: php wordpress/tools/tests/run.php (или npm test).
 *
 * Нужен только PHP: WordPress подменён минимальными заглушками (wp-stubs.php), поэтому тесты идут секунды
 * и запускаются перед каждым коммитом. Проверяются чистые функции — правила, которые получают значения и
 * возвращают значение. Вёрстку и интеграции проверяют установка с нуля и обход сайта.
 *
 * Новый тест — файл <тема>.test.php рядом: подключает нужный файл плагина и вызывает test().
 * Название теста — фраза на языке команды: тесты читаются как требования.
 */

define( 'ACME_TESTS', true );
require __DIR__ . '/wp-stubs.php';

$acme_test_failures = array();
$acme_test_count    = 0;

/** Один тест: название-требование и функция, которая бросает исключение, если требование нарушено. */
function test( $name, callable $body ) {
	global $acme_test_failures, $acme_test_count;
	++$acme_test_count;
	try {
		$body();
		echo "  ok  $name\n";
	} catch ( Throwable $error ) {
		$acme_test_failures[] = $name;
		echo "FAIL  $name\n      " . $error->getMessage() . "\n";
	}
}

function expect_equal( $expected, $actual ) {
	if ( $expected !== $actual ) {
		$message = 'ожидалось ' . var_export( $expected, true ) . ', получено ' . var_export( $actual, true );
		throw new RuntimeException( $message );
	}
}

function expect_true( $actual ) {
	expect_equal( true, (bool) $actual );
}

foreach ( glob( __DIR__ . '/*.test.php' ) as $acme_test_file ) {
	echo basename( $acme_test_file ) . "\n";
	require $acme_test_file;
}

$acme_failed = count( $acme_test_failures );
echo "\nТестов: $acme_test_count, не прошло: $acme_failed\n";
exit( $acme_failed ? 1 : 0 );
