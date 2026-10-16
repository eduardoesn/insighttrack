<?php
declare(strict_types=1);

/** Valida entradas e coordena criação, edição e remoção de clientes. */
final class CustomerController
{
    public function __construct(private Customer $customers) {}

    public function handle(string $action, array $input): string
    {
        if ($action === 'customer.create') {
            $this->customers->create(InputValidator::customer($input));
            return 'Cliente cadastrado.';
        }
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            throw new InvalidArgumentException('Cliente inválido.');
        }
        if ($action === 'customer.update') {
            if (!$this->customers->update($id, InputValidator::customer($input))) {
                throw new DomainException('Cliente não encontrado.');
            }
            return 'Cliente atualizado.';
        }
        if ($action === 'customer.delete') {
            if (!$this->customers->delete($id)) {
                throw new DomainException('Cliente não encontrado.');
            }
            return 'Cliente removido.';
        }
        throw new InvalidArgumentException('Ação de cliente inválida.');
    }
}
