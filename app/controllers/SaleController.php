<?php
declare(strict_types=1);

/** Valida filtros de consulta e coordena operações de venda. */
final class SaleController
{
    public function __construct(private Sale $sales, private Customer $customers) {}

    public function create(array $input): string
    {
        $this->sales->create(InputValidator::sale($input), $this->customers);
        return 'Venda registrada e estoque atualizado.';
    }

    public function list(array $query): array
    {
        $search = trim((string) ($query['q'] ?? ''));
        $from = $this->date($query['from'] ?? null, 'Data inicial');
        $to = $this->date($query['to'] ?? null, 'Data final');
        if ($from !== null && $to !== null && $from > $to) {
            throw new InvalidArgumentException('A data inicial deve ser anterior à data final.');
        }
        $page = filter_var($query['p'] ?? 1, FILTER_VALIDATE_INT);
        if ($page === false || $page < 1) {
            $page = 1;
        }
        return $this->sales->paginate($search, $from, $to, $page);
    }

    private function date(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        // A comparação de ida e volta rejeita datas inválidas que o PHP tentaria ajustar.
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($label . ' inválida.');
        }
        return $date->format('Y-m-d');
    }
}
