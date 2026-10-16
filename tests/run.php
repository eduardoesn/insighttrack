<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/validation/InputValidator.php';

$passed = 0;
$failed = 0;

function check(string $label, callable $assertion): void
{
    global $passed, $failed;
    try {
        $assertion();
        $passed++;
        echo "PASS {$label}\n";
    } catch (Throwable $exception) {
        $failed++;
        fwrite(STDERR, "FAIL {$label}: {$exception->getMessage()}\n");
    }
}

function same(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function rejects(callable $operation): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Expected input validation to reject this value.');
}

check('accepts a valid product and casts numeric input', static function (): void {
    same(['name' => 'Caderno', 'price' => 12.5, 'stock' => 4], InputValidator::product(['name' => ' Caderno ', 'price' => '12.50', 'stock' => '4']));
});
check('rejects a negative product price', static function (): void {
    rejects(static fn() => InputValidator::product(['name' => 'Produto', 'price' => '-1', 'stock' => '1']));
});
check('rejects a fractional stock quantity', static function (): void {
    rejects(static fn() => InputValidator::product(['name' => 'Produto', 'price' => '1', 'stock' => '1.5']));
});
check('accepts an optional empty customer email', static function (): void {
    same(['name' => 'Ana', 'email' => null], InputValidator::customer(['name' => 'Ana', 'email' => '']));
});
check('rejects an invalid customer email', static function (): void {
    rejects(static fn() => InputValidator::customer(['name' => 'Ana', 'email' => 'not-an-email']));
});
check('accepts a sale with optional walk-in customer', static function (): void {
    same(['product_id' => 3, 'customer_id' => null, 'quantity' => 2], InputValidator::sale(['product_id' => '3', 'customer_id' => '', 'quantity' => '2']));
});
check('rejects zero quantity', static function (): void {
    rejects(static fn() => InputValidator::sale(['product_id' => '3', 'customer_id' => '', 'quantity' => '0']));
});

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
