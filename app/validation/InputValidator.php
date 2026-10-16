<?php
declare(strict_types=1);

/** Valida e normaliza dados de formulários antes de chamar a camada SQL. */
final class InputValidator
{
    private static function length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }
        // Mantém os testes locais funcionando mesmo sem a extensão mbstring.
        return preg_match_all('/./us', $value) ?: 0;
    }

    private static function name(array $input): string
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || self::length($name) > 120) {
            throw new InvalidArgumentException('Informe um nome com até 120 caracteres.');
        }
        return $name;
    }

    public static function product(array $input): array
    {
        $price = filter_var($input['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $stock = filter_var($input['stock'] ?? null, FILTER_VALIDATE_INT);
        if ($price === false || !is_finite((float) $price) || $price < 0) {
            throw new InvalidArgumentException('Informe um preço válido, igual ou maior que zero.');
        }
        if ($stock === false || $stock < 0) {
            throw new InvalidArgumentException('Informe um estoque inteiro, igual ou maior que zero.');
        }
        return ['name' => self::name($input), 'price' => $price, 'stock' => $stock];
    }

    public static function customer(array $input): array
    {
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || self::length($email) > 254)) {
            throw new InvalidArgumentException('Informe um e-mail válido.');
        }
        return ['name' => self::name($input), 'email' => $email !== '' ? $email : null];
    }

    public static function sale(array $input): array
    {
        $productId = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
        $customerId = ($input['customer_id'] ?? '') === '' ? null : filter_var($input['customer_id'], FILTER_VALIDATE_INT);
        if (!$productId || $quantity === false || $quantity < 1 || ($customerId !== null && !$customerId)) {
            throw new InvalidArgumentException('Selecione um produto e informe uma quantidade válida.');
        }
        return ['product_id' => $productId, 'customer_id' => $customerId, 'quantity' => $quantity];
    }
}
