<?php
declare(strict_types=1);

/** Valida entradas e coordena criação, edição e remoção de produtos. */
final class ProductController
{
    public function __construct(private Product $products) {}

    public function handle(string $action, array $input): string
    {
        if ($action === 'product.create') {
            $this->products->create(InputValidator::product($input));
            return 'Produto cadastrado.';
        }
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            throw new InvalidArgumentException('Produto inválido.');
        }
        if ($action === 'product.update') {
            if (!$this->products->update($id, InputValidator::product($input))) {
                throw new DomainException('Produto não encontrado.');
            }
            return 'Produto atualizado.';
        }
        if ($action === 'product.delete') {
            if (!$this->products->delete($id)) {
                throw new DomainException('Produto não encontrado.');
            }
            return 'Produto removido.';
        }
        throw new InvalidArgumentException('Ação de produto inválida.');
    }
}
